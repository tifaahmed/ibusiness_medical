<?php

namespace App\Http\Controllers\Admin\Service\Edit;

use App\Enums\Service\ServiceTagEnum;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Service\Show\AdminServiceShowResource;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_SERVICES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_SERVICES;
    }

    public function __invoke(Request $request, string $service): Response
    {
        $service = Service::where('slug', $service)->firstOrFail();
        $this->assertOwns($service);

        $serviceTypes = ServiceType::active()->get(['id', 'name'])->map(function ($serviceType) {
            return [
                'id' => $serviceType->id,
                'name' => $serviceType->name,
            ];
        });

        $governorates = Governorate::all()->map(function ($governorate) {
            return [
                'id' => $governorate->id,
                'name' => $governorate->name,
            ];
        });

        $cities = City::all()->map(function ($city) {
            return [
                'id' => $city->id,
                'governorate_id' => $city->governorate_id,
                'name' => $city->name,
            ];
        });

        return Inertia::render('Admin/Service/Edit/ServiceEditView', [
            'service' => new AdminServiceShowResource($service),
            'serviceTypes' => $serviceTypes,
            'governorates' => $governorates,
            'cities' => $cities,
            'tagOptions' => ServiceTagEnum::getOptions(),
            'tags' => Tag::forPicker(),
        ]);
    }
}
