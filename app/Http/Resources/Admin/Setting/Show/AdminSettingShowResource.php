<?php

namespace App\Http\Resources\Admin\Setting\Show;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSettingShowResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(private Setting $setting)
    {
        parent::__construct($setting);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->setting->id,
            'slug' => $this->setting->slug,
            // All translations, so the form can edit each language.
            'name' => $this->setting->getTranslations('name'),
            'value' => $this->setting->value,
            'value_type' => $this->setting->value_type,
            // What the value resolves to once read through its type: the link
            // for an image, the decoded flag for a boolean, and so on.
            'resolved' => $this->setting->castValue(),
            'image_url' => $this->setting->value_type === Setting::TYPE_IMAGE ? $this->setting->url() : null,
            'created_at' => $this->setting->created_at,
            'updated_at' => $this->setting->updated_at,
        ];
    }
}
