<?php

namespace App\Http\Resources\Admin\Tag\List;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminTagListCollection
{
    public function __construct(private LengthAwarePaginator $tags) {}

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    // Both names, so the table can list them side by side.
                    'name' => $tag->getTranslations('name'),
                    'icon' => $tag->icon,
                    'color' => $tag->color,
                    'services_count' => $tag->services_count ?? $tag->services()->count(),
                    'facilities_count' => $tag->facilities_count ?? 0,
                    'products_count' => $tag->products_count ?? 0,
                    'usage_count' => ($tag->services_count ?? 0)
                        + ($tag->facilities_count ?? 0)
                        + ($tag->products_count ?? 0),
                    'creator' => $tag->creator,
                    'created_at' => $tag->created_at,
                    'updated_at' => $tag->updated_at,
                ];
            })->toArray(),
            'meta' => [
                'current_page' => $this->tags->currentPage(),
                'first_page_url' => $this->tags->url(1),
                'from' => $this->tags->firstItem(),
                'last_page' => $this->tags->lastPage(),
                'last_page_url' => $this->tags->url($this->tags->lastPage()),
                'links' => $this->tags->linkCollection()->toArray(),
                'next_page_url' => $this->tags->nextPageUrl(),
                'path' => $this->tags->path(),
                'per_page' => $this->tags->perPage(),
                'prev_page_url' => $this->tags->previousPageUrl(),
                'to' => $this->tags->lastItem(),
                'total' => $this->tags->total(),
            ],
        ];
    }
}
