<?php

namespace App\Http\Controllers\Admin\MembershipUsage\List;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\MembershipUsage\List\AdminMembershipUsageListCollection;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\MembershipUsage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipUsageListController extends BaseController
{
    use ScopesByMembershipCreator;

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        $usages = MembershipUsage::with(['membership.user', 'membership.creator:id,name,email', 'facility', 'facilityBranch', 'facilityType'])
            ->when($restricted, fn($q) => $q->whereHas('membership', $scopeFilter))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->whereHas('membership', function ($mq) use ($filters) {
                    $mq->where('membership_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('user', function ($uq) use ($filters) {
                            $uq->where('name', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when(isset($filters['facility_id']) && $filters['facility_id'] !== '' && $filters['facility_id'] !== null, function ($q) use ($filters) {
                $q->where('facility_id', (int) $filters['facility_id']);
            })
            ->when(isset($filters['facility_type_id']) && $filters['facility_type_id'] !== '' && $filters['facility_type_id'] !== null, function ($q) use ($filters) {
                $q->where('facility_type_id', (int) $filters['facility_type_id']);
            })
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        $facilities = Facility::all()->map(fn($f) => [
            'id'   => $f->id,
            'name' => $f->getTranslation('name', app()->getLocale()),
        ]);

        $facilityTypes = FacilityType::all()->map(fn($t) => [
            'id'   => $t->id,
            'name' => $t->getTranslation('name', app()->getLocale()),
        ]);

        return Inertia::render('Admin/MembershipUsage/List', [
            'usages'        => new AdminMembershipUsageListCollection($usages)->toArray($request),
            'filters'       => $filters,
            'facilities'    => $facilities,
            'facilityTypes' => $facilityTypes,
        ]);
    }

    protected function getFilters(Request $request): array
    {
        return [
            'search'           => $request->input('search', ''),
            'facility_id'      => $request->input('facility_id'),
            'facility_type_id' => $request->input('facility_type_id'),
        ];
    }
}
