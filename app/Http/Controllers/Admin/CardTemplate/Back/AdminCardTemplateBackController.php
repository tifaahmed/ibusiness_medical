<?php

namespace App\Http\Controllers\Admin\CardTemplate\Back;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Saves a template's back side: which pieces show, what they say, and the two
 * uploads (background artwork, logo). Everything that shows a card back reads
 * it from the template, so one save reaches the guest page, the admin
 * membership page and the downloads alike.
 */
class AdminCardTemplateBackController extends Controller
{
    public function __invoke(Request $request, CardTemplate $cardTemplate): JsonResponse
    {
        $settings = json_decode((string) $request->input('settings', '[]'), true);
        if (! is_array($settings)) {
            return response()->json(['message' => 'The back settings could not be read.'], 422);
        }

        $rules = [
            'enabled' => ['required', 'boolean'],
            'back_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'back_logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'qrcode.value' => ['nullable', 'string', 'max:500'],
        ];
        foreach (['logo', 'slogan', 'title', 'website', 'qrcode'] as $key) {
            $rules["{$key}.visible"] = ['required', 'boolean'];
            foreach (['x', 'y'] as $axis) {
                $rules["{$key}.{$axis}"] = ['required', 'numeric', 'between:-1,2'];
            }
            foreach (['width', 'height'] as $size) {
                $rules["{$key}.{$size}"] = ['required', 'numeric', 'between:0.01,2'];
            }
        }
        foreach (['slogan', 'title', 'website'] as $key) {
            $rules["{$key}.text"] = ['nullable', 'string', 'max:120'];
            $rules["{$key}.color"] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
            $rules["{$key}.font_size"] = ['required', 'numeric', 'between:1,200'];
            $rules["{$key}.direction"] = ['required', 'in:ltr,rtl,center'];
        }

        $validator = Validator::make($settings + $request->only(['back_image', 'back_logo']), $rules);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $clean = $validator->validated();
            unset($clean['back_image'], $clean['back_logo']);

            $data = ['back_settings' => $clean];

            foreach (['back_image', 'back_logo'] as $column) {
                if ($request->hasFile($column)) {
                    $data[$column] = 'storage/'.$request->file($column)->store('card-templates', 'public');
                } elseif ($request->boolean("remove_{$column}")) {
                    $data[$column] = null;
                }
            }

            $cardTemplate->update($data);
        } catch (\Throwable $e) {
            Log::error('Saving a card template back failed', [
                'card_template_id' => $cardTemplate->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'The back side could not be saved. Please try again.'], 500);
        }

        return response()->json(['data' => $cardTemplate->fresh()]);
    }
}
