<?php

namespace App\Http\Controllers\Admin\Setting\List;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Setting\List\AdminSettingListCollection;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The settings list.
 *
 * Settings are global rows with no `created_by`, so unlike most admin lists
 * this one has nothing to scope by creator — every admin who can reach the
 * screen sees every setting.
 */
class AdminSettingListController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        $search = $request->input('search', '');
        // Blank, or one of Setting::VALUE_TYPES.
        $type = $request->input('value_type', '');
        // newest / oldest / key.
        $sort = $request->input('sort', 'key');

        $type = in_array($type, Setting::VALUE_TYPES, true) ? $type : '';

        $settings = Setting::query()
            ->when($search, function ($q) use ($search) {
                // The key and both names are all visible in the table, so all
                // three are searchable.
                $q->where(function ($query) use ($search) {
                    $query->where('slug', 'like', "%{$search}%")
                        ->orWhere('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%")
                        ->orWhere('value', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($q) => $q->where('value_type', $type))
            ->when($sort === 'newest', fn ($q) => $q->latest())
            ->when($sort === 'oldest', fn ($q) => $q->oldest())
            // Alphabetical by key is the default: settings are looked up by
            // name far more often than they are browsed by age.
            ->when($sort !== 'newest' && $sort !== 'oldest', fn ($q) => $q->orderBy('slug'))
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Setting/List', [
            'settings' => (new AdminSettingListCollection($settings))->toArray($request),
            'filters' => ['search' => $search, 'value_type' => $type, 'sort' => $sort],
            'valueTypes' => Setting::VALUE_TYPES,
        ]);
    }
}
