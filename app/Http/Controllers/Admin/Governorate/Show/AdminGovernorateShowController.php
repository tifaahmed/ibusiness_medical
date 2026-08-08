<?php

namespace App\Http\Controllers\Admin\Governorate\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Governorate\Show\AdminGovernorateShowResource;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminGovernorateShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_GOVERNORATES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_GOVERNORATES; }

    /**
     * Display the specified governorate.
     */
    public function __invoke(Request $request, string $governorate): Response
    {
        $governorate = Governorate::withCount('facilities')
            ->with('facilities.facilityType')
            ->where('slug', $governorate)
            ->firstOrFail();
        $this->assertOwns($governorate);

        $result = [
            'governorate' => (new AdminGovernorateShowResource($governorate))->toArray($request),
        ];

        return Inertia::render('Admin/Governorate/Show', $result);
    }
}

