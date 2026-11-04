<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\ContactMessage;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The contact inbox says, per message, whether the sender is a trial / paid member, a primary or a relative, and where to open them. */
class ContactMessageMemberBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_message_carries_the_members_state_type_and_page(): void
    {
        $boss = User::factory()->create();
        $boss->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        $trial = User::factory()->create(['phone' => '01011111111', 'name' => 'Trial Tina']);
        Membership::factory()->create(['user_id' => $trial->id, 'plan' => 'trial', 'expiration_date' => now()->addDays(5), 'is_active' => true]);

        $paid = User::factory()->create(['phone' => '01022222222', 'name' => 'Paid Pam']);
        $primary = Membership::factory()->create(['user_id' => $paid->id, 'plan' => 'full', 'expiration_date' => now()->addMonths(6), 'is_active' => true]);

        $rel = User::factory()->create(['phone' => '01033333333', 'name' => 'Rel Ron']);
        Membership::factory()->create(['user_id' => $rel->id, 'member_type' => 'relative', 'parent_membership_id' => $primary->id, 'relationship' => 'son', 'plan' => 'relative', 'is_active' => true]);

        foreach (['+201011111111' => 'a', '01022222222' => 'b', '201033333333' => 'c', '01099999999' => 'd'] as $phone => $n) {
            ContactMessage::query()->create(['name' => $n, 'phone' => $phone, 'message' => 'hi', 'source' => 'contact_form', 'status' => 'new']);
        }

        $rows = collect($this->actingAs($boss)->get(route('admin.contact-messages.index'))->assertOk()
            ->viewData('page')['props']['messages']['data'])->keyBy('name');

        $this->assertSame(['trial', 'primary', $trial->slug], [$rows['a']['member']['state'], $rows['a']['member']['type'], $rows['a']['member']['slug']]);
        $this->assertSame(['active', 'primary'], [$rows['b']['member']['state'], $rows['b']['member']['type']]);
        $this->assertSame('relative', $rows['c']['member']['type']);
        $this->assertNull($rows['d']['member']);
    }
}
