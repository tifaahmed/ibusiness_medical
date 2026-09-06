<?php

namespace App\Http\Resources\Admin\Setting\List;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminSettingListCollection
{
    public function __construct(private LengthAwarePaginator $settings) {}

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->settings->map(function (Setting $setting) {
                return [
                    'id' => $setting->id,
                    'slug' => $setting->slug,
                    // Both names, so the table can list them side by side.
                    'name' => $setting->getTranslations('name'),
                    'value' => $setting->value,
                    'value_type' => $setting->value_type,
                    // Image rows are shown as a thumbnail rather than a path.
                    'image_url' => $setting->value_type === Setting::TYPE_IMAGE ? $setting->url() : null,
                    'created_at' => $setting->created_at,
                    'updated_at' => $setting->updated_at,
                ];
            })->toArray(),
            'meta' => [
                'current_page' => $this->settings->currentPage(),
                'first_page_url' => $this->settings->url(1),
                'from' => $this->settings->firstItem(),
                'last_page' => $this->settings->lastPage(),
                'last_page_url' => $this->settings->url($this->settings->lastPage()),
                'links' => $this->settings->linkCollection()->toArray(),
                'next_page_url' => $this->settings->nextPageUrl(),
                'path' => $this->settings->path(),
                'per_page' => $this->settings->perPage(),
                'prev_page_url' => $this->settings->previousPageUrl(),
                'to' => $this->settings->lastItem(),
                'total' => $this->settings->total(),
            ],
        ];
    }
}
