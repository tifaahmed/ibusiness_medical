<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $contracts = Contract::active()->ordered()->get()->map(fn($contract) => [
            'id' => $contract->id,
            'name' => $contract->name,
            'description' => $contract->description,
            'phones' => $contract->phones,
            'slug' => $contract->slug,
            'image' => $contract->image,
        ]);

        $faqs = Faq::active()->ordered()->get()->map(fn($faq) => [
            'id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
        ]);

        return response()->json([
            'contracts' => $contracts,
            'faqs' => $faqs,
        ]);
    }
}
