<?php

namespace App\Http\Resources\Admin\ServiceType\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminServiceTypeShowResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(private $serviceType)
    {
        parent::__construct($serviceType);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->serviceType->id,
            'name' => $this->serviceType->getTranslations('name'),
            'description' => $this->serviceType->description,
            'icon' => $this->serviceType->icon,
            'color' => $this->serviceType->color,
            'is_active' => $this->serviceType->is_active,
            'image' => $this->serviceType->image,
            'services_count' => $this->serviceType->services()->count(),
            'creator' => $this->serviceType->creator,
            'created_at' => $this->serviceType->created_at,
            'updated_at' => $this->serviceType->updated_at,
        ];
    }
}
