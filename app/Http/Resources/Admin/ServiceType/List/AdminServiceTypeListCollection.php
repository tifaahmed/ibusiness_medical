<?php

namespace App\Http\Resources\Admin\ServiceType\List;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminServiceTypeListCollection
{
    public function __construct(private LengthAwarePaginator $serviceTypes) {}

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->serviceTypes->map(function ($serviceType) {
                return [
                    'id' => $serviceType->id,
                    'name' => $serviceType->getTranslations('name'),
                    'description' => $serviceType->description,
                    'icon' => $serviceType->icon,
                    'color' => $serviceType->color,
                    'is_active' => $serviceType->is_active,
                    'image' => $serviceType->image,
                    'services_count' => $serviceType->services()->count(),
                    'creator' => $serviceType->creator,
                    'created_at' => $serviceType->created_at,
                    'updated_at' => $serviceType->updated_at,
                ];
            })->toArray(),
            'meta' => [
                'current_page' => $this->serviceTypes->currentPage(),
                'first_page_url' => $this->serviceTypes->url(1),
                'from' => $this->serviceTypes->firstItem(),
                'last_page' => $this->serviceTypes->lastPage(),
                'last_page_url' => $this->serviceTypes->url($this->serviceTypes->lastPage()),
                'links' => $this->serviceTypes->linkCollection()->toArray(),
                'next_page_url' => $this->serviceTypes->nextPageUrl(),
                'path' => $this->serviceTypes->path(),
                'per_page' => $this->serviceTypes->perPage(),
                'prev_page_url' => $this->serviceTypes->previousPageUrl(),
                'to' => $this->serviceTypes->lastItem(),
                'total' => $this->serviceTypes->total(),
            ],
        ];
    }
}
