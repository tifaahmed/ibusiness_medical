<?php

namespace App\Http\Resources\Admin\Service\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminServiceShowResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(private $service)
    {
        parent::__construct($service);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->service->id,
            'category_id' => $this->service->category_id,
            'title' => $this->service->title,
            'slug' => $this->service->slug,
            'short_subject' => $this->service->short_subject,
            'subject' => $this->service->subject,
            'old_price' => $this->service->old_price,
            'new_price' => $this->service->new_price,
            'discount_percent' => $this->service->discount_percent,
            'has_discount' => $this->service->hasDiscount(),
            'image' => $this->service->image,
            'gallery' => $this->service->gallery,
            'governorate_id' => $this->service->governorate_id,
            'city_id' => $this->service->city_id,
            'iframe_location' => $this->service->iframe_location,
            'lat' => $this->service->lat,
            'long' => $this->service->long,
            'tag' => $this->service->tag,
            'tags' => $this->service->tags()->get(['tags.id', 'tags.name', 'tags.icon', 'tags.color']),
            'category' => $this->service->category ? [
                'id' => $this->service->category->id,
                'name' => $this->service->category->name,
            ] : null,
            'governorate' => $this->service->governorate,
            'city' => $this->service->city,
            'creator' => $this->service->creator,
            'created_at' => $this->service->created_at,
            'updated_at' => $this->service->updated_at,
        ];
    }
}
