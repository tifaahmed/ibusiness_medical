<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Actions\Membership\CreateRelative;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A member adding their own children.
 *
 * Only a named, male member may, only the first name is accepted, and the rest
 * of the child's name is the father's — built here, so nothing a client posts
 * can change it.
 */
class MemberFamilyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_father_adds_a_child_and_the_fathers_name_is_appended(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', ['first_name' => '  Omar ', 'relationship' => 'son', 'date_of_birth' => '2015-05-05'])
            ->assertCreated()
            ->assertJsonPath('data.family_member.name', 'Omar Ahmed Ali Hassan')
            ->assertJsonPath('data.family_member.relationship', 'son');

        $this->assertDatabaseHas('memberships', [
            'parent_membership_id' => $membership->id,
            'member_type' => 'relative',
            'relationship' => 'son',
            // Added from the app, so it waits for staff.
            'is_active' => false,
            'approval_status' => 'pending',
        ]);
        $this->assertDatabaseHas('users', ['name' => 'Omar Ahmed Ali Hassan']);
    }

    /** @test */
    public function a_member_adds_a_relative_with_id_scans_and_it_waits_for_approval(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        [$member, $membership] = $this->member('Mona Adel', 'female');
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/family/members', [
            'name' => 'Sara Adel',
            'relationship' => 'mother',
            'national_id' => '29801010102345',
            'id_front' => \Illuminate\Http\UploadedFile::fake()->image('front.jpg'),
            'id_back' => \Illuminate\Http\UploadedFile::fake()->image('back.jpg'),
            'photo' => \Illuminate\Http\UploadedFile::fake()->image('me.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.family_member.approval_status', 'pending')
            ->assertJsonPath('data.family_member.is_active', false);

        $this->assertDatabaseHas('memberships', [
            'parent_membership_id' => $membership->id,
            'national_id' => '29801010102345',
            'approval_status' => 'pending',
            'added_by' => 'member',
            'is_active' => false,
        ]);
    }

    /** @test */
    public function adding_a_relative_without_the_id_images_is_rejected(): void
    {
        [$member] = $this->member('Mona Adel', 'female');
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/family/members', [
            'name' => 'Sara Adel',
            'relationship' => 'mother',
            'national_id' => '29801010102345',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['id_front', 'id_back', 'photo']);
    }

    /** @test */
    public function a_surname_posted_by_the_client_is_ignored(): void
    {
        [$father] = $this->member('Ahmed Ali Hassan', 'male');
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', [
            'first_name' => 'Omar',
            'relationship' => 'son',
            'date_of_birth' => '2015-05-05',
            'name' => 'Omar Somebody Else',
        ])->assertCreated()
            ->assertJsonPath('data.family_member.name', 'Omar Ahmed Ali Hassan');
    }

    /** @test */
    public function only_a_male_member_may_add_a_child(): void
    {
        [$mother, $membership] = $this->member('Mona Adel', 'female');
        Sanctum::actingAs($mother);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son', 'date_of_birth' => '2015-05-05'])
            ->assertForbidden();

        $this->assertDatabaseMissing('memberships', ['parent_membership_id' => $membership->id]);
    }

    /** @test */
    public function a_father_with_no_name_on_file_cannot_add_a_child(): void
    {
        [$father] = $this->member('', 'male');
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son', 'date_of_birth' => '2015-05-05'])
            ->assertForbidden();
    }

    /** @test */
    public function only_a_son_or_a_daughter_can_be_added(): void
    {
        [$father] = $this->member('Ahmed Ali Hassan', 'male');
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Mona', 'relationship' => 'wife'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('relationship');
    }

    /** @test */
    public function the_first_name_is_letters_only(): void
    {
        [$father] = $this->member('Ahmed Ali Hassan', 'male');
        Sanctum::actingAs($father);

        foreach (['', 'Omar2', '<b>Omar</b>', str_repeat('a', 61)] as $bad) {
            $this->postJson('/api/v1/family/children', ['first_name' => $bad, 'relationship' => 'son'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('first_name');
        }

        $this->postJson('/api/v1/family/children', ['first_name' => 'عبد الرحمن', 'relationship' => 'son', 'date_of_birth' => '2015-05-05'])
            ->assertCreated()
            ->assertJsonPath('data.family_member.name', 'عبد الرحمن Ahmed Ali Hassan');
    }

    /** @test */
    public function the_same_child_is_not_added_twice(): void
    {
        [$father] = $this->member('Ahmed Ali Hassan', 'male');
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son', 'date_of_birth' => '2015-05-05'])->assertCreated();
        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son', 'date_of_birth' => '2015-05-05'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');
    }

    /** @test */
    public function the_family_lists_only_the_members_own_and_says_whether_they_can_add(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        [, $otherMembership] = $this->member('Khaled Samir', 'male', '01055555555');

        $this->relative($membership, 'Mona Ahmed', 'wife');
        $this->relative($otherMembership, 'Not Mine', 'son');

        Sanctum::actingAs($father);

        $this->getJson('/api/v1/family')
            ->assertOk()
            ->assertJsonPath('data.can_add_children', true)
            ->assertJsonCount(1, 'data.family_members')
            ->assertJsonPath('data.family_members.0.name', 'Mona Ahmed');
    }

    /** @test */
    public function a_father_renames_a_child_and_the_fathers_name_is_appended_again(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        $child = $this->relative($membership, 'Omr Ahmed Ali Hassan', 'son');
        Sanctum::actingAs($father);

        $this->putJson("/api/v1/family/children/{$child->id}", [
            'first_name' => 'Omar',
            'relationship' => 'daughter',
            'name' => 'Omar Somebody Else',
        ])->assertOk()
            ->assertJsonPath('data.family_member.name', 'Omar Ahmed Ali Hassan')
            ->assertJsonPath('data.family_member.relationship', 'daughter');

        $this->assertDatabaseHas('memberships', ['id' => $child->id, 'relationship' => 'daughter']);
        $this->assertDatabaseHas('users', ['id' => $child->user_id, 'name' => 'Omar Ahmed Ali Hassan']);
    }

    /** @test */
    public function a_father_removes_a_child(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        $child = $this->relative($membership, 'Omar Ahmed Ali Hassan', 'son');
        Sanctum::actingAs($father);

        $this->deleteJson("/api/v1/family/children/{$child->id}")->assertOk();

        $this->assertSoftDeleted('memberships', ['id' => $child->id]);
    }

    /** @test */
    public function a_father_cannot_change_another_members_child_or_his_own_wife(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        [, $otherMembership] = $this->member('Khaled Samir', 'male', '01055555555');

        $stranger = $this->relative($otherMembership, 'Not Mine', 'son');
        $wife = $this->relative($membership, 'Mona Ahmed', 'wife');
        Sanctum::actingAs($father);

        $this->putJson("/api/v1/family/children/{$stranger->id}", ['first_name' => 'Hacked', 'relationship' => 'son'])->assertNotFound();
        $this->deleteJson("/api/v1/family/children/{$stranger->id}")->assertNotFound();
        $this->putJson("/api/v1/family/children/{$wife->id}", ['first_name' => 'Hacked', 'relationship' => 'son'])->assertNotFound();
        $this->deleteJson("/api/v1/family/children/{$wife->id}")->assertNotFound();

        $this->assertDatabaseHas('memberships', ['id' => $stranger->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('memberships', ['id' => $wife->id, 'deleted_at' => null]);
    }

    /** @test */
    public function only_a_male_member_may_change_children(): void
    {
        [$mother, $membership] = $this->member('Mona Adel', 'female');
        $child = $this->relative($membership, 'Omar Mona Adel', 'son');
        Sanctum::actingAs($mother);

        $this->putJson("/api/v1/family/children/{$child->id}", ['first_name' => 'Omar', 'relationship' => 'son'])->assertForbidden();
        $this->deleteJson("/api/v1/family/children/{$child->id}")->assertForbidden();
    }

    /** @test */
    public function renaming_a_child_onto_a_sibling_is_refused(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        $this->relative($membership, 'Omar Ahmed Ali Hassan', 'son');
        $other = $this->relative($membership, 'Ali Ahmed Ali Hassan', 'son');
        Sanctum::actingAs($father);

        $this->putJson("/api/v1/family/children/{$other->id}", ['first_name' => 'Omar', 'relationship' => 'son'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');
    }

    /** @test */
    public function the_family_flags_every_relative_as_editable_and_removable_by_the_owner(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        $this->relative($membership, 'Mona Ahmed', 'wife');
        $this->relative($membership, 'Omar Ahmed Ali Hassan', 'son');
        Sanctum::actingAs($father);

        $this->getJson('/api/v1/family')
            ->assertJsonPath('data.family_members.0.can_edit', true)
            ->assertJsonPath('data.family_members.0.can_delete', true)
            ->assertJsonPath('data.family_members.1.can_edit', true);
    }

    /** @test */
    public function a_member_sees_only_their_own_addresses(): void
    {
        [$member, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        [, $otherMembership] = $this->member('Khaled Samir', 'male', '01055555555');

        Address::create(['membership_id' => $membership->id, 'type' => 'home', 'address' => '12 Nile St', 'street' => 'Nile', 'floor_number' => '3']);
        Address::create(['membership_id' => $otherMembership->id, 'type' => 'work', 'address' => 'Not Mine']);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data.addresses')
            ->assertJsonPath('data.addresses.0.address', '12 Nile St')
            ->assertJsonPath('data.addresses.0.type', 'home')
            ->assertJsonMissingPath('data.addresses.0.membership_id');
    }

    /** @test */
    public function a_member_saves_a_checkout_address_by_type(): void
    {
        [$member, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        $governorate = \App\Models\Governorate::factory()->create();
        Sanctum::actingAs($member);

        $payload = ['type' => 'home', 'governorate_id' => $governorate->id, 'street' => 'Nile', 'floor_number' => '3'];

        $this->postJson('/api/v1/addresses', $payload)->assertOk()->assertJsonPath('data.address.street', 'Nile');
        $this->postJson('/api/v1/addresses', [...$payload, 'street' => 'Corniche'])->assertOk();
        $this->postJson('/api/v1/addresses', [...$payload, 'type' => 'work'])->assertOk();

        $this->assertSame(2, $membership->addresses()->count());
        $this->assertSame('Corniche', $membership->addresses()->where('type', 'home')->value('street'));
    }

    /** @test */
    public function a_guest_is_refused_the_addresses(): void
    {
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
        $this->postJson('/api/v1/addresses', [])->assertUnauthorized();
    }

    /** @test */
    public function a_guest_is_refused(): void
    {
        $this->getJson('/api/v1/family')->assertUnauthorized();
        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son', 'date_of_birth' => '2015-05-05'])->assertUnauthorized();
    }

    /**
     * @return array{0: User, 1: Membership}
     */
    private function member(string $name, string $gender, string $phone = '01062587475'): array
    {
        $user = User::factory()->create(['name' => $name, 'gender' => $gender, 'phone' => $phone]);

        $membership = Membership::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'is_visible' => true,
        ]);

        return [$user->refresh(), $membership];
    }

    /** A relative on this primary card, through the same action the app and the admin use. */
    private function relative(Membership $primary, string $name, string $relationship): Membership
    {
        return app(CreateRelative::class)->handle($primary, [
            'name' => $name,
            'relationship' => $relationship,
            'date_of_birth' => '1990-01-01',
            'is_active' => true,
        ]);
    }
}
