<?php

namespace App\Http\Controllers\Admin\CardTemplate\List;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCardTemplateListController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $templates = CardTemplate::query()
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
            )
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->value().'%'),
            )
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
            'statuses' => CardTemplateStatusEnum::options(),
        ]);
    }
}
