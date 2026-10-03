<?php

namespace App\Http\Controllers\Admin\Store\Tags;

use App\Http\Controllers\Controller as BaseController;
use App\Models\StoreCategory;
use App\Services\StoreTagSuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * "Find tags with AI" on the store form. Works on the form as it stands and
 * writes nothing: it answers which existing tags fit and which new ones to add.
 */
class AdminStoreTagSuggestController extends BaseController
{
    public function __construct(private readonly StoreTagSuggester $suggester) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:10'],
            'title' => ['nullable', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'array'],
            'short_description.*' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:30000'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
        ]);

        $categories = StoreCategory::query()->whereIn('id', $data['category_ids'] ?? [])->get()
            ->map(fn ($c) => $c->getTranslation('name', 'en', false) ?: $c->getTranslation('name', 'ar', false))->filter()->implode(', ');

        $facts = [
            'Store (Arabic)' => (string) data_get($data, 'title.ar', ''),
            'Store (English)' => (string) data_get($data, 'title.en', ''),
            'Categories' => $categories,
            'Short description (Arabic)' => (string) data_get($data, 'short_description.ar', ''),
            'Short description (English)' => (string) data_get($data, 'short_description.en', ''),
            'Description (Arabic)' => (string) data_get($data, 'description.ar', ''),
            'Description (English)' => (string) data_get($data, 'description.en', ''),
        ];

        if (collect($facts)->except('Categories')->filter(fn ($v) => trim($v) !== '')->isEmpty()) {
            return response()->json(['message' => 'Write the store title or description first so the AI has something to go on.'], 422);
        }

        try {
            return response()->json($this->suggester->suggest($facts, (int) $data['count']));
        } catch (RuntimeException $e) {
            Log::warning('Store tag suggestion failed', ['error_message' => $e->getMessage(), 'ip_address' => $request->ip()]);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Store tag suggestion crashed', ['error_message' => $e->getMessage(), 'error_trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Could not find tags right now. Please try again.'], 500);
        }
    }
}
