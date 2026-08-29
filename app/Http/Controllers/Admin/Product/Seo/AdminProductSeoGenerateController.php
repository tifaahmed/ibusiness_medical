<?php

namespace App\Http\Controllers\Admin\Product\Seo;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Product\GenerateProductSeoRequest;
use App\Services\ProductSeoGenerator;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs the "Generate with AI" button on the admin product SEO tab. Called over
 * axios, so it answers JSON rather than redirecting.
 */
class AdminProductSeoGenerateController extends BaseController
{
    public function __construct(private readonly ProductSeoGenerator $generator) {}

    public function __invoke(GenerateProductSeoRequest $request): JsonResponse
    {
        try {
            $seo = $this->generator->generate($request->validated());
        } catch (RuntimeException $e) {
            // Configuration and upstream-API problems are both the admin's to
            // act on, so surface the message instead of a bare 500.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['seo' => $seo]);
    }
}
