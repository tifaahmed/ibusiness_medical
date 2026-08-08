<?php

namespace App\Http\Resources\Admin\Service\List;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminServiceListCollection
{
    public function __construct(private LengthAwarePaginator $services) {}

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'slug' => $service->slug,
                    'category' => $service->category ? [
                        'id' => $service->category->id,
                        'name' => $service->category->name,
                    ] : null,
                    'short_subject' => $service->short_subject,
                    'subject' => $service->subject,
                    'old_price' => $service->old_price,
                    'new_price' => $service->new_price,
                    'discount_percent' => $service->discount_percent,
                    'has_discount' => $service->hasDiscount(),
                    'tag' => $service->tag,
                    'tags' => $service->tags,
                    'image' => $service->image,
                    'gallery' => $service->gallery,
                    'governorate' => $service->governorate,
                    'city' => $service->city,
                    'creator' => $service->creator,
                    'created_at' => $service->created_at,
                    'updated_at' => $service->updated_at,
                ];
            })->toArray(),
            'meta' => [
                'current_page' => $this->services->currentPage(),
                'first_page_url' => $this->services->url(1),
                'from' => $this->services->firstItem(),
                'last_page' => $this->services->lastPage(),
                'last_page_url' => $this->services->url($this->services->lastPage()),
                'links' => $this->services->linkCollection()->toArray(),
                'next_page_url' => $this->services->nextPageUrl(),
                'path' => $this->services->path(),
                'per_page' => $this->services->perPage(),
                'prev_page_url' => $this->services->previousPageUrl(),
                'to' => $this->services->lastItem(),
                'total' => $this->services->total(),
            ],
        ];
    }
}
