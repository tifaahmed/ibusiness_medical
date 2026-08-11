<?php

namespace App\Http\Controllers\Admin\Facility\Seo;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Facility\GenerateFacilitySeoRequest;
use App\Services\FacilitySeoGenerator;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Backs the "Generate SEO with AI" button on the admin facility form. Called
 * over axios from the SEO tab, so it answers JSON rather than redirecting.
 */
class AdminFacilitySeoGenerateController extends BaseController
{
    public function __construct(private readonly FacilitySeoGenerator $generator) {}

    public function __invoke(GenerateFacilitySeoRequest $request): JsonResponse
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
