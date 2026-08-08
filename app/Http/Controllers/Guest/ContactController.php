<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends BaseController
{
    /**
     * Display the Contact Us page.
     */
    public function __invoke(Request $request): Response
    {
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

        return Inertia::render('Guest/ContactPage', [
            'pageTitle' => 'Contact Us - ASH Health Care',
            'contracts' => $contracts,
        ]);
    }
}

