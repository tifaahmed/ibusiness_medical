<?php

namespace Tests\Feature;

use App\Enums\Contact\ContactSourceEnum;
use App\Models\Membership;
use App\Models\NationalIdChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A national ID already on a card is never edited directly: the member files a change request
 * (new number + optional ID photos) that lands in the contact inbox, and only an admin's approval changes it.
 * Run on a throwaway database (see AGENTS.md).
 */
class NationalIdChangeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = '29001010101010';

    private const NEW = '29501010202020';

    private function member(): array
    {
        $user = User::factory()->create(['phone' => '01011112222', 'name' => 'Ali Hassan']);
        $membership = Membership::factory()->create(['user_id' => $user->id, 'national_id' => self::OLD]);
        Sanctum::actingAs($user);

        return [$user, $membership];
    }

    /** @test */
    public function a_national_id_already_on_the_card_cannot_be_edited_through_the_profile(): void
    {
        [, $membership] = $this->member();

        $this->putJson('/api/v1/profile', ['national_id' => self::NEW])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'national_id_locked');

        $this->assertSame(self::OLD, $membership->fresh()->national_id);

        // Saving the same number (the form posts it back) and clearing nothing is fine.
        $this->putJson('/api/v1/profile', ['name' => 'Ali Hassan', 'national_id' => self::OLD])->assertOk();
        $this->assertSame(self::OLD, $membership->fresh()->national_id);
    }

    /** @test */
    public function a_card_without_an_id_can_still_set_it_the_first_time(): void
    {
        $user = User::factory()->create(['phone' => '01033334444']);
        $membership = Membership::factory()->create(['user_id' => $user->id, 'national_id' => null]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['national_id' => self::NEW])->assertOk();

        $this->assertSame(self::NEW, $membership->fresh()->national_id);
    }

    /** @test */
    public function the_change_request_is_filed_with_its_photos_in_the_contact_inbox_and_changes_nothing_yet(): void
    {
        Storage::fake('local');
        [, $membership] = $this->member();

        $this->post('/api/v1/membership/national-id-change', [
            'national_id' => self::NEW,
            'front' => UploadedFile::fake()->image('front.jpg'),
            'back' => UploadedFile::fake()->image('back.jpg'),
            'note' => 'typo on my card',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.requested', true);

        $request = NationalIdChangeRequest::firstOrFail();
        $this->assertSame(NationalIdChangeRequest::PENDING, $request->status);
        $this->assertSame(self::OLD, $request->current_national_id);
        $this->assertSame(self::NEW, $request->new_national_id);
        Storage::disk('local')->assertExists($request->front_path);
        Storage::disk('local')->assertExists($request->back_path);

        $this->assertDatabaseHas('contact_messages', ['id' => $request->contact_message_id, 'source' => ContactSourceEnum::NATIONAL_ID_CHANGE->value]);
        $this->assertSame(self::OLD, $membership->fresh()->national_id);
    }

    /** @test */
    public function the_photos_are_optional_but_the_number_must_be_a_real_one(): void
    {
        $this->member();

        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => '12345'])->assertStatus(422);
        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => '19001010101010'])->assertStatus(422);
        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => self::NEW])->assertCreated();
    }

    /** @test */
    public function one_open_request_at_a_time_and_never_a_number_another_card_holds(): void
    {
        [, $membership] = $this->member();

        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => self::OLD])->assertStatus(422)->assertJsonPath('reason', 'same_national_id');

        Membership::factory()->create(['national_id' => self::NEW]);
        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => self::NEW])->assertStatus(422)->assertJsonPath('reason', 'national_id_taken');

        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => '29601010303030'])->assertCreated();
        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => '29701010404040'])->assertStatus(422)->assertJsonPath('reason', 'already_open');
    }

    /** @test */
    public function an_admin_approving_it_is_the_only_thing_that_changes_the_id(): void
    {
        [$user, $membership] = $this->member();
        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => self::NEW])->assertCreated();
        $request = NationalIdChangeRequest::firstOrFail();

        // A member cannot approve their own request.
        $this->post(route('admin.national-id-changes.approve', $request))->assertStatus(403);
        $this->assertSame(self::OLD, $membership->fresh()->national_id);

        $permission = Permission::findOrCreate('manage contact messages', 'web');
        $admin = User::factory()->create();
        $admin->givePermissionTo($permission);
        $this->actingAs($admin)->post(route('admin.national-id-changes.approve', $request), ['review_note' => 'checked the photos'])->assertRedirect();

        $this->assertSame(self::NEW, $membership->fresh()->national_id);
        $this->assertSame(NationalIdChangeRequest::APPROVED, $request->fresh()->status);
        $this->assertDatabaseHas('contact_messages', ['id' => $request->contact_message_id, 'status' => 'resolved']);

        // Answered once: pressing it again is refused.
        $this->actingAs($admin)->post(route('admin.national-id-changes.approve', $request))->assertStatus(409);
    }

    /** @test */
    public function rejecting_leaves_the_id_as_it_was(): void
    {
        [, $membership] = $this->member();
        $this->postJson('/api/v1/membership/national-id-change', ['national_id' => self::NEW])->assertCreated();
        $request = NationalIdChangeRequest::firstOrFail();

        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('manage contact messages', 'web'));
        $this->actingAs($admin)->post(route('admin.national-id-changes.reject', $request), ['review_note' => 'photo unreadable'])->assertRedirect();

        $this->assertSame(self::OLD, $membership->fresh()->national_id);
        $this->assertSame(NationalIdChangeRequest::REJECTED, $request->fresh()->status);
    }
}
