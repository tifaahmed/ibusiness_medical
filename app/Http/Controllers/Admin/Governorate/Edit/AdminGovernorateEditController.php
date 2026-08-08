<?php

namespace App\Http\Controllers\Admin\Governorate\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Governorate\Edit\AdminGovernorateEditResource;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminGovernorateEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_GOVERNORATES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_GOVERNORATES; }

    /**
     * Show the form for editing the specified governorate.
     */
    public function __invoke(Request $request, string $governorate): Response
    {
        $governorate = Governorate::with('cities')->where('slug', $governorate)->firstOrFail();
        $this->assertOwns($governorate);

        $resourceData = (new AdminGovernorateEditResource($governorate))->toArray($request);

        // Debug: Log the resource data being sent to Inertia
        \Log::info('Governorate Edit Resource Data', [
            'governorate_id' => $governorate->id,
            'name' => $resourceData['name'],
            'name_type' => gettype($resourceData['name']),
        ]);

        $result = [
            'governorate' => $resourceData,
        ];

        return Inertia::render('Admin/Governorate/Edit/GovernorateEditView', $result);
    }
}

