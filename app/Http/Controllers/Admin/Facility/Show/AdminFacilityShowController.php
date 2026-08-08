<?php

namespace App\Http\Controllers\Admin\Facility\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Facility\Show\AdminFacilityShowResource;
use App\Models\Facility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FACILITIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FACILITIES; }

    /**
     * Display the specified facility.
     */
    public function __invoke(Request $request, string $facility): Response
    {
        $facility = Facility::withCount('branches')
            ->with(['facilityType', 'branches', 'tags'])
            ->where('slug', $facility)
            ->firstOrFail();
        $this->assertOwns($facility);

        $result = [
            'facility' => (new AdminFacilityShowResource($facility))->toArray($request),
        ];

        return Inertia::render('Admin/Facility/Show', $result);
    }
}

