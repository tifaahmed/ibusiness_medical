<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Contract;
use App\Models\Faq;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends BaseController
{
    /**
     * Display the About Us page.
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

        $faqs = Faq::active()->ordered()->get()->map(function ($faq) {
            return [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ];
        });

        return Inertia::render('Guest/AboutPage', [
            'pageTitle' => 'About Us - ASH Health Care',
            'contracts' => $contracts,
            'faqs' => $faqs,
        ]);
    }
}

