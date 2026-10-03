<?php

namespace App\Http\Controllers\Admin\Store\Seo;

use App\Http\Controllers\Controller as BaseController;
use App\Services\StoreSeoGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * "Fill with AI" on the store SEO tab. Works on the form as it stands, answers
 * JSON and writes nothing.
 */
class AdminStoreSeoGenerateController extends BaseController
{
    public function __construct(private readonly StoreSeoGenerator $generator) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'array'],
            'short_description.*' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:65535'],
            'categories' => ['nullable', 'array', 'max:50'],
            'categories.*' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['nullable', 'string', 'max:255'],
            'offer_percent_from' => ['nullable', 'numeric', 'between:0,100'],
            'offer_percent_to' => ['nullable', 'numeric', 'between:0,100'],
        ], ['title.required' => 'Enter the store title first — the AI needs it to write the metadata.']);

        if (collect($data['title'])->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
            return response()->json(['message' => 'Enter the store title first — the AI needs it to write the metadata.'], 422);
        }

        try {
            return response()->json(['seo' => $this->generator->generate($data)]);
        } catch (RuntimeException $e) {
            Log::warning('Store SEO generation failed', ['error_message' => $e->getMessage(), 'ip_address' => $request->ip()]);

            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Store SEO generation crashed', ['error_message' => $e->getMessage(), 'error_trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Could not generate the SEO right now. Please try again.'], 500);
        }
    }
}
