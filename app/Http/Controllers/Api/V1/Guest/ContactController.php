<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
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

        return response()->json([
            'contracts' => $contracts,
        ]);
    }
}
