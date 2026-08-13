<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A batch can dress its number up for the card — a display prefix in front of
 * it, dashes breaking it into readable groups. Neither mark is part of the
 * membership number: the database keeps the number whole, which is what the
 * barcode encodes and what every lookup resolves.
 */
class MembershipCardBatchNumberTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            ['manage memberships', 'create membership card patches', 'view membership card patches'],
        ));
        $user->assignRole($role);

        return $user;
    }

    public function test_decoration_stays_off_the_stored_number(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/admin/membership-card-patches', [
                'quantity' => 3,
                'start_number' => 123456789,
                'display_prefix' => 'ASH-',
                'display_groups' => '3-3-3',
            ])
            ->assertOk();

        $this->assertSame(
            ['123456789', '123456790', '123456791'],
            array_column($response->json('memberships'), 'membership_number'),
        );

        // Nothing dashed or prefixed reached the memberships table.
        $this->assertSame(0, Membership::where('membership_number', 'LIKE', '%-%')->count());
        $this->assertSame(0, Membership::where('membership_number', 'LIKE', 'ASH%')->count());

        // The batch remembers how to dress the number up when it prints.
        $card = MembershipCard::findOrFail($response->json('card.id'));
        $this->assertSame('ASH-', $card->display_prefix);
        $this->assertSame('3-3-3', $card->display_groups);
    }

    public function test_a_grouping_that_is_not_group_lengths_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/membership-card-patches', [
                'quantity' => 1,
                'start_number' => 1000,
                'display_groups' => '3-3-abc',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('display_groups');
    }

    public function test_a_batch_without_grouping_stores_none(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson('/admin/membership-card-patches', [
                'quantity' => 1,
                'start_number' => 2000,
            ])
            ->assertOk();

        $this->assertNull(MembershipCard::findOrFail($response->json('card.id'))->display_groups);
    }
}
