<?php

namespace App\Http\Controllers\Admin\Company\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCompanyMembersController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    /**
     * List the members (via their memberships) belonging to a company, for
     * the "view members" popup on the admin company list page.
     */
    public function __invoke(Request $request, string $company): JsonResponse
    {
        $company = Company::where('slug', $company)->firstOrFail();
        $this->assertOwns($company);

        $perPage = (int) $request->input('per_page', 10);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 10;

        $name = trim((string) $request->input('name', ''));
        $membershipNumber = trim((string) $request->input('membership_number', ''));
        $phone = trim((string) $request->input('phone', ''));

        $members = $company->memberships()
            ->with('user:id,name,phone')
            ->when($name !== '', fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', '%' . $name . '%')))
            ->when($membershipNumber !== '', fn ($q) => $q->where('membership_number', 'like', '%' . $membershipNumber . '%'))
            ->when($phone !== '', fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('phone', 'like', '%' . $phone . '%')))
            ->orderBy('id')
            ->paginate($perPage);

        $members->getCollection()->transform(fn ($membership) => [
            'id'                 => $membership->id,
            'membership_number'  => $membership->membership_number,
            'name'               => $membership->user?->name,
            'phone'              => $membership->user?->phone,
        ]);

        return response()->json([
            'data' => $members->items(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page'    => $members->lastPage(),
                'per_page'     => $members->perPage(),
                'total'        => $members->total(),
                'from'         => $members->firstItem(),
                'to'           => $members->lastItem(),
            ],
        ]);
    }
}
