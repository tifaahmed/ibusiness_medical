<?php

namespace App\Http\Resources\Admin\Tag\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTagShowResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(private $tag)
    {
        parent::__construct($tag);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->tag->id,
            'name' => $this->tag->name,
            'icon' => $this->tag->icon,
            'color' => $this->tag->color,
            'services_count' => $this->tag->services()->count(),
            'creator' => $this->tag->creator,
            'created_at' => $this->tag->created_at,
            'updated_at' => $this->tag->updated_at,
        ];
    }
}
