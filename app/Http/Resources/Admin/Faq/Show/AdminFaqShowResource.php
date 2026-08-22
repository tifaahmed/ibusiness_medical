<?php

namespace App\Http\Resources\Admin\Faq\Show;

use App\Http\Resources\Admin\Concerns\ResolvesTranslations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminFaqShowResource extends JsonResource
{
    use ResolvesTranslations;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->translationMap('question'),
            'answer' => $this->translationMap('answer'),
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'email' => $this->creator?->email,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
