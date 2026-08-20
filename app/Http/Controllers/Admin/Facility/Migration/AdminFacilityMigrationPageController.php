<?php

namespace App\Http\Controllers\Admin\Facility\Migration;

use App\Http\Controllers\Admin\Facility\Migration\Concerns\LookupOptions;
use App\Http\Controllers\Controller as BaseController;
use App\Models\City;
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
    use LookupOptions;

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
            // The import preview matches what a package calls a lookup against
            // these, so every spelling it could be written under travels along.
            'facilityTypes' => FacilityType::orderBy('id')->get()->map(fn ($t) => $this->option($t))->values(),
            'governorates' => Governorate::orderBy('id')->get()->map(fn ($g) => $this->option($g))->values(),
            'cities' => City::orderBy('id')->get()->map(fn ($c) => $this->option($c) + [
                'governorate_id' => $c->governorate_id,
            ])->values(),
            'salesOptions' => Sales::orderBy('id')->get()->map(fn ($s) => [
                'value' => $s->id,
                'label' => $s->getRawOriginal('name'),
            ])->values(),
        ]);
    }
}
