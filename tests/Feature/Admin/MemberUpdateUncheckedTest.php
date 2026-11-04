<?php

namespace Tests\Feature\Admin;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Editing a member with active / visible / paid / trial all unticked must save, or say exactly why not. */
class MemberUpdateUncheckedTest extends TestCase
{
    use RefreshDatabase;

    public function test_untick_all_four_saves(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('manage memberships', 'web'));

        $member = User::factory()->create(['phone' => '01067088271']);
        $card = Membership::create([
            'user_id' => $member->id, 'membership_number' => '02420093323', 'national_id' => '29906250300067',
            'is_active' => true, 'is_visible' => true, 'is_paid' => true, 'registration_date' => '2026-10-12',
            'expiration_date' => '2027-10-12',
        ]);

        $gov = \App\Models\Governorate::factory()->create();
        $city = \App\Models\City::factory()->create(['governorate_id' => $gov->id]);

        $res = $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->put(route('admin.user.membership.update', $member->slug), [
                'name' => $member->name, 'email' => '', 'phone' => '01067088271', 'membership_number' => '02420093323',
                'national_id' => '29906250300067', 'registration_date' => '2026-10-12', 'expiration_date' => '',
                'is_active' => false, 'is_visible' => false, 'is_paid' => false, 'is_trial' => false,
                'payment_type' => '', 'job_title' => [], 'governorate_id' => $gov->id, 'city_id' => $city->id, 'address_type' => 'home',
            ]);

        $res->assertSessionHasNoErrors();
        $this->assertFalse((bool) $card->fresh()->is_active);
    }
}
