<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Sales;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityMigrationPageController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Facility/Migration/FacilityMigrationView', [
            'summary' => [
                'facilities' => Facility::count(),
                'branches' => FacilityBranch::count(),
                'media' => DB::table('media')
                    ->whereIn('model_type', [Facility::class, FacilityBranch::class])
                    ->count(),
                'upload_max' => ini_get('upload_max_filesize'),
                'post_max' => ini_get('post_max_size'),
            ],
            'facilityTypes' => FacilityType::orderBy('id')->get()->map(fn ($t) => [
                'value' => $t->id,
                'label' => $t->getTranslation('name', app()->getLocale()) ?: $t->getTranslation('name', 'en'),
            ])->values(),
            'governorates' => Governorate::orderBy('id')->get()->map(fn ($g) => [
                'value' => $g->id,
                'label' => $g->getTranslation('name', app()->getLocale()) ?: $g->getTranslation('name', 'en'),
            ])->values(),
            'salesOptions' => Sales::orderBy('id')->get()->map(fn ($s) => [
                'value' => $s->id,
                'label' => $s->getRawOriginal('name'),
            ])->values(),
        ]);
    }
}
