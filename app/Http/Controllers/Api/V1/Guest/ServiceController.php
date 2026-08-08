<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\ServiceResource;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'category_id' => $request->input('category_id'),
            'governorate_id' => $request->input('governorate_id'),
            'city_id' => $request->input('city_id'),
            'tag' => $request->input('tag'),
            'has_discount' => $request->input('has_discount'),
            'search' => $request->input('search', ''),
        ];

        $services = Service::with(['serviceType', 'governorate', 'city', 'tags'])
            ->when($filters['category_id'], fn($q) => $q->where('category_id', $filters['category_id']))
            ->when($filters['governorate_id'], fn($q) => $q->where('governorate_id', $filters['governorate_id']))
            ->when($filters['city_id'], fn($q) => $q->where('city_id', $filters['city_id']))
            ->when($filters['tag'], fn($q) => $q->where('tag', $filters['tag']))
            ->when($filters['has_discount'], fn($q) => $q->withDiscount())
            ->when($filters['search'], fn($q) => $q->where('title', 'like', '%' . $filters['search'] . '%'))
            ->ordered()
            ->paginate($request->input('per_page', 12))
            ->withQueryString();

        $categories = ServiceType::active()->get()->map(fn($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'icon' => $t->icon,
            'color' => $t->color,
        ]);

        $governorates = Governorate::all()->map(fn($g) => [
            'id' => $g->id,
            'name' => $g->name,
        ]);

        $servicesArr = ServiceResource::collection($services)->response()->getData(true);

        return response()->json([
            'services' => $servicesArr,
            'filters' => $filters,
            'categories' => $categories,
            'governorates' => $governorates,
        ]);
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $service->load(['serviceType', 'governorate', 'city', 'tags']);

        return response()->json([
            'service' => new ServiceResource($service),
        ]);
    }
}
