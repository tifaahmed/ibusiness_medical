<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Contract;
use App\Models\Offer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends BaseController
{
    /**
     * Display the home page with membership lookup form.
     */
    public function __invoke(Request $request): Response
    {
        // Fetch active offers with their relationships
        $offers = Offer::with(['offerable'])
            ->orderBy('created_at', 'desc')

            ->get()
            ->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'slug' => $offer->slug,
                    'short_description' => $offer->short_description,
                    'full_description' => $offer->full_description,
                    'phone' => $offer->phone,
                    'price' => $offer->price,
                    'old_price' => $offer->old_price,
                    'discount_percentage' => $offer->discount_percentage,
                    'has_discount' => $offer->hasDiscount(),
                    'image' => $offer->image ?: 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&h=400&fit=crop',
                    'thumbnail' => $offer->thumbnail ?: 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&h=400&fit=crop',
                    'offerable_type' => $offer->offerable_type,
                    'offerable_name' => $offer->offerable ? $offer->offerable->name : null,
                ];
            });

        $contracts = Contract::active()->ordered()->get()->map(function ($contract) {
            return [
                'id' => $contract->id,
                'name' => $contract->name,
                'description' => $contract->description,
                'phones' => $contract->phones,
                'slug' => $contract->slug,
                'image' => $contract->image,
            ];
        });

        return Inertia::render('Guest/HomePage', [
            'offers' => $offers,
            'contracts' => $contracts,
        ]);
    }
}

