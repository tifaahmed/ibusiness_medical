<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\FamilyMember;
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

        $this->postJson('/api/v1/family/children', ['first_name' => '  Omar ', 'relationship' => 'son'])
            ->assertCreated()
            ->assertJsonPath('data.family_member.name', 'Omar Ahmed Ali Hassan')
            ->assertJsonPath('data.family_member.relationship', 'son');

        $this->assertDatabaseHas('family_members', [
            'membership_id' => $membership->id,
            'name' => 'Omar Ahmed Ali Hassan',
            'relationship' => 'son',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function a_surname_posted_by_the_client_is_ignored(): void
    {
        [$father] = $this->member('Ahmed Ali Hassan', 'male');
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', [
            'first_name' => 'Omar',
            'relationship' => 'son',
            'name' => 'Omar Somebody Else',
        ])->assertCreated()
            ->assertJsonPath('data.family_member.name', 'Omar Ahmed Ali Hassan');
    }

    /** @test */
    public function only_a_male_member_may_add_a_child(): void
    {
        [$mother, $membership] = $this->member('Mona Adel', 'female');
        Sanctum::actingAs($mother);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son'])
            ->assertForbidden();

        $this->assertDatabaseMissing('family_members', ['membership_id' => $membership->id]);
    }

    /** @test */
    public function a_father_with_no_name_on_file_cannot_add_a_child(): void
    {
        [$father] = $this->member('', 'male');
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son'])
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

        $this->postJson('/api/v1/family/children', ['first_name' => 'عبد الرحمن', 'relationship' => 'son'])
            ->assertCreated()
            ->assertJsonPath('data.family_member.name', 'عبد الرحمن Ahmed Ali Hassan');
    }

    /** @test */
    public function the_same_child_is_not_added_twice(): void
    {
        [$father] = $this->member('Ahmed Ali Hassan', 'male');
        Sanctum::actingAs($father);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son'])->assertCreated();
        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');
    }

    /** @test */
    public function the_family_lists_only_the_members_own_and_says_whether_they_can_add(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        [, $otherMembership] = $this->member('Khaled Samir', 'male', '01055555555');

        FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Mona Ahmed', 'relationship' => 'wife', 'is_active' => true]);
        FamilyMember::create(['membership_id' => $otherMembership->id, 'name' => 'Not Mine', 'relationship' => 'son', 'is_active' => true]);

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
        $child = FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Omr Ahmed Ali Hassan', 'relationship' => 'son', 'is_active' => true]);
        Sanctum::actingAs($father);

        $this->putJson("/api/v1/family/children/{$child->id}", [
            'first_name' => 'Omar',
            'relationship' => 'daughter',
            'name' => 'Omar Somebody Else',
        ])->assertOk()
            ->assertJsonPath('data.family_member.name', 'Omar Ahmed Ali Hassan')
            ->assertJsonPath('data.family_member.relationship', 'daughter');

        $this->assertDatabaseHas('family_members', ['id' => $child->id, 'name' => 'Omar Ahmed Ali Hassan', 'relationship' => 'daughter']);
    }

    /** @test */
    public function a_father_removes_a_child(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        $child = FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Omar Ahmed Ali Hassan', 'relationship' => 'son', 'is_active' => true]);
        Sanctum::actingAs($father);

        $this->deleteJson("/api/v1/family/children/{$child->id}")->assertOk();

        $this->assertSoftDeleted('family_members', ['id' => $child->id]);
    }

    /** @test */
    public function a_father_cannot_change_another_members_child_or_his_own_wife(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        [, $otherMembership] = $this->member('Khaled Samir', 'male', '01055555555');

        $stranger = FamilyMember::create(['membership_id' => $otherMembership->id, 'name' => 'Not Mine', 'relationship' => 'son', 'is_active' => true]);
        $wife = FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Mona Ahmed', 'relationship' => 'wife', 'is_active' => true]);
        Sanctum::actingAs($father);

        $this->putJson("/api/v1/family/children/{$stranger->id}", ['first_name' => 'Hacked', 'relationship' => 'son'])->assertNotFound();
        $this->deleteJson("/api/v1/family/children/{$stranger->id}")->assertNotFound();
        $this->putJson("/api/v1/family/children/{$wife->id}", ['first_name' => 'Hacked', 'relationship' => 'son'])->assertNotFound();
        $this->deleteJson("/api/v1/family/children/{$wife->id}")->assertNotFound();

        $this->assertDatabaseHas('family_members', ['id' => $stranger->id, 'name' => 'Not Mine', 'deleted_at' => null]);
        $this->assertDatabaseHas('family_members', ['id' => $wife->id, 'name' => 'Mona Ahmed', 'deleted_at' => null]);
    }

    /** @test */
    public function only_a_male_member_may_change_children(): void
    {
        [$mother, $membership] = $this->member('Mona Adel', 'female');
        $child = FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Omar Mona Adel', 'relationship' => 'son', 'is_active' => true]);
        Sanctum::actingAs($mother);

        $this->putJson("/api/v1/family/children/{$child->id}", ['first_name' => 'Omar', 'relationship' => 'son'])->assertForbidden();
        $this->deleteJson("/api/v1/family/children/{$child->id}")->assertForbidden();
    }

    /** @test */
    public function renaming_a_child_onto_a_sibling_is_refused(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Omar Ahmed Ali Hassan', 'relationship' => 'son', 'is_active' => true]);
        $other = FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Ali Ahmed Ali Hassan', 'relationship' => 'son', 'is_active' => true]);
        Sanctum::actingAs($father);

        $this->putJson("/api/v1/family/children/{$other->id}", ['first_name' => 'Omar', 'relationship' => 'son'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');
    }

    /** @test */
    public function the_family_flags_which_people_the_father_may_edit(): void
    {
        [$father, $membership] = $this->member('Ahmed Ali Hassan', 'male');
        FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Mona Ahmed', 'relationship' => 'wife', 'is_active' => true]);
        FamilyMember::create(['membership_id' => $membership->id, 'name' => 'Omar Ahmed Ali Hassan', 'relationship' => 'son', 'is_active' => true]);
        Sanctum::actingAs($father);

        $this->getJson('/api/v1/family')
            ->assertJsonPath('data.family_members.0.can_edit', false)
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
    public function a_guest_is_refused_the_addresses(): void
    {
        $this->getJson('/api/v1/addresses')->assertUnauthorized();
    }

    /** @test */
    public function a_guest_is_refused(): void
    {
        $this->getJson('/api/v1/family')->assertUnauthorized();
        $this->postJson('/api/v1/family/children', ['first_name' => 'Omar', 'relationship' => 'son'])->assertUnauthorized();
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
}
