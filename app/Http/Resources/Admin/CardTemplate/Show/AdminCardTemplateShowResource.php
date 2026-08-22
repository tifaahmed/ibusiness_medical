<?php

namespace App\Http\Resources\Admin\CardTemplate\Show;

use App\Http\Resources\Admin\Concerns\ResolvesTranslations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCardTemplateShowResource extends JsonResource
{
    use ResolvesTranslations;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->translationMap('name'),
            'slug' => $this->slug,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'card_empty_url' => $this->card_empty_url,
            'sample_card_url' => $this->sample_card_url,
            // The stored layout still carries slots the status hides, so send
            // the effective one the generator actually draws with.
            'layout' => $this->effectiveLayout(),
            'hidden_fields' => $this->hidden_fields,
            'sample_data' => $this->sample_data,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
