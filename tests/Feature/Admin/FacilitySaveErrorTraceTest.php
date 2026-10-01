<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Admin\Facility\Actions\Update\UpdateFacilityAction;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A facility save that throws still answers with the polite "Failed to update
 * facility" (the normal tab of the error dialog), and flashes the exact
 * exception as `error_debug` for its "Advanced Error Track" tab.
 */
class FacilitySaveErrorTraceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_update_flashes_the_exact_exception(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage facilities', 'web'));
        $user->assignRole($role);

        $type = FacilityType::create(['name' => ['en' => 'Hospital', 'ar' => 'مستشفى']]);
        $facility = Facility::create(['name' => ['en' => 'Resala', 'ar' => 'رسالة'], 'facility_type_id' => $type->id]);

        $this->app->instance(UpdateFacilityAction::class, new class extends UpdateFacilityAction
        {
            public function __construct() {}

            public function execute(Facility $facility, array $validated): Facility
            {
                throw new \RuntimeException("Unknown column 'source' in 'INSERT INTO'");
            }
        });

        $response = $this->actingAs($user)
            ->from(route('admin.facility.edit', $facility->slug))
            ->put(route('admin.facility.update', $facility->slug), [
                'name' => ['en' => 'Resala', 'ar' => 'رسالة'],
                'facility_type_id' => $type->id,
            ]);

        $response->assertRedirect(route('admin.facility.edit', $facility->slug));
        $response->assertSessionHasErrors('error');

        $trace = session('error_debug');
        $this->assertSame(\RuntimeException::class, $trace['exception']);
        $this->assertSame("Unknown column 'source' in 'INSERT INTO'", $trace['message']);
        $this->assertStringStartsWith('tests/', $trace['file']);
        $this->assertIsArray($trace['trace']);
    }
}
