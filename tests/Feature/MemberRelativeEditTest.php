<?php

namespace Tests\Feature;

use App\Actions\Membership\CreateRelative;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The card owner completes and removes their own relatives; what staff approved stays put. */
class MemberRelativeEditTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_waiting_relative_can_be_completed_and_its_new_phone_is_unverified(): void
    {
        [$owner, $card] = $this->owner();
        $relative = $this->relative($card, Membership::ADDED_BY_MEMBER, ['date_of_birth' => '2015-05-05', 'name' => 'Omar Ahmed Ali', 'relationship' => 'son']);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/family/relatives/{$relative->id}", ['phone' => '01012345678', 'name' => 'Omar Mohamed Ali'])
            ->assertOk()
            ->assertJsonPath('data.family_member.phone', '01012345678')
            ->assertJsonPath('data.family_member.phone_verified', false)
            ->assertJsonPath('data.family_member.name', 'Omar Mohamed Ali');
    }

    /** @test */
    public function an_approved_relative_only_gets_its_empty_fields_filled(): void
    {
        [$owner, $card] = $this->owner();
        $relative = $this->relative($card, Membership::ADDED_BY_ADMIN, ['date_of_birth' => '1990-01-01', 'name' => 'Mona Hassan Ali', 'relationship' => 'wife']);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/family/relatives/{$relative->id}", ['phone' => '01022222222'])->assertOk()->assertJsonPath('data.family_member.phone', '01022222222');
        $this->patchJson("/api/v1/family/relatives/{$relative->id}", ['date_of_birth' => '1991-02-02'])->assertStatus(422)->assertJsonValidationErrors('date_of_birth');
        $this->patchJson("/api/v1/family/relatives/{$relative->id}", ['name' => 'Someone Else Entirely'])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    /** @test */
    public function the_owner_removes_a_relative_but_nobody_else_can_touch_it(): void
    {
        [$owner, $card] = $this->owner();
        $relative = $this->relative($card, Membership::ADDED_BY_ADMIN, ['date_of_birth' => '1990-01-01', 'name' => 'Mona Hassan Ali', 'relationship' => 'wife']);

        [$stranger] = $this->owner('01099999999');
        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/v1/family/relatives/{$relative->id}")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/family/relatives/{$relative->id}")->assertOk();
        $this->assertSoftDeleted('memberships', ['id' => $relative->id]);
    }

    /** @test */
    public function the_owner_uploads_a_photo_for_a_relative_but_not_for_a_stranger_relative(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        [$owner, $card] = $this->owner();
        $relative = $this->relative($card, Membership::ADDED_BY_ADMIN, ['date_of_birth' => '1990-01-01', 'name' => 'Mona Hassan Ali', 'relationship' => 'wife']);
        $photo = \Illuminate\Http\UploadedFile::fake()->image('mona.jpg', 200, 200);

        [$stranger] = $this->owner('01099999999');
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/family/relatives/{$relative->id}/avatar", ['avatar' => $photo])->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/family/relatives/{$relative->id}/avatar", ['avatar' => $photo])
            ->assertOk()
            ->assertJsonStructure(['data' => ['avatar_url']]);
        $this->assertCount(1, $relative->user->fresh()->getMedia('avatar'));
    }

    /** @return array{0: User, 1: Membership} */
    private function owner(string $phone = '01062587475'): array
    {
        $user = User::factory()->create(['name' => 'Ahmed Ali Hassan', 'gender' => 'male', 'phone' => $phone]);
        $card = Membership::factory()->create(['user_id' => $user->id, 'is_active' => true, 'is_visible' => true]);

        return [$user, $card];
    }

    private function relative(Membership $card, string $addedBy, array $data): Membership
    {
        return app(CreateRelative::class)->handle($card, $data, $addedBy);
    }
}
