<?php

namespace App\Http\Controllers\Admin\Facility\Phones;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Support\PhoneRepair;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every branch phone number that is not in the shape the directory expects,
 * with the correction beside it.
 *
 * Whether a number is wrong is a question about its digits, not something the
 * database can be asked, so the scan walks the branches that have a phone at
 * all and the page is built from what comes back. Branch phone lists are short
 * and there are only a few thousand branches, so this stays cheap.
 */
class AdminFacilityPhoneFixPageController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __invoke(Request $request): Response
    {
        // Which numbers the admin wants to work through: mobiles, landlines,
        // or both. A number of the other kind is left exactly as it is.
        $kind = $request->input('kind', 'all');
        $kind = in_array($kind, PhoneRepair::KINDS, true) ? $kind : 'all';
        $kinds = $kind === 'all' ? PhoneRepair::KINDS : [$kind];

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(5, min($perPage, 100));
        $page = max(1, (int) $request->input('page', 1));

        $problems = [];

        FacilityBranch::query()
            ->select(['id', 'facility_id', 'name', 'phone', 'slug'])
            ->whereNotNull('phone')
            ->with('facility:id,name,slug')
            ->when($this->scopesToCreator(), fn ($query) => $query->whereHas(
                'facility',
                fn ($facilityQuery) => $this->applyCreatorScope($facilityQuery),
            ))
            ->orderBy('facility_id')
            ->orderBy('id')
            ->chunkById(500, function ($branches) use (&$problems, $kinds) {
                foreach ($branches as $branch) {
                    // The raw column, not the model's tidied list: a packed cell is
                    // exactly the sort of thing this page exists to show.
                    $repair = PhoneRepair::repair(PhoneRepair::stored($branch), $kinds);

                    if (! $repair['has_problem']) {
                        continue;
                    }

                    $problems[] = [
                        'branch_id' => $branch->id,
                        'branch_name' => $this->readable($branch),
                        'facility_name' => $branch->facility ? $this->readable($branch->facility) : '',
                        'facility_slug' => $branch->facility?->slug,
                        'current' => $repair['current'],
                        'suggested' => $repair['suggested'],
                        'entries' => $repair['entries'],
                        'needs_review' => $repair['needs_review'],
                    ];
                }
            });

        $paginator = new LengthAwarePaginator(
            array_slice($problems, ($page - 1) * $perPage, $perPage),
            count($problems),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Admin/Facility/Phones/FacilityPhoneFixView', [
            'problems' => [
                'data' => array_values($paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'links' => $paginator->linkCollection()->toArray(),
                ],
            ],
            'filters' => [
                'kind' => $kind,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * The record's name in the admin's language, falling back to whichever
     * translation exists.
     */
    private function readable(mixed $model): string
    {
        return $model->getTranslation('name', app()->getLocale())
            ?: $model->getTranslation('name', 'ar')
            ?: $model->getTranslation('name', 'en')
            ?: '';
    }
}
