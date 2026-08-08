<?php

namespace App\Http\Controllers\Admin\Contract\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Contract\Show\AdminContractShowResource;
use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminContractShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_CONTRACTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_CONTRACTS; }

    /**
     * Display the specified contract.
     */
    public function __invoke(Request $request, string $contract): Response
    {
        $contract = Contract::where('slug', $contract)->firstOrFail();
        $this->assertOwns($contract);

        $result = [
            'contract' => (new AdminContractShowResource($contract))->toArray($request),
        ];

        return Inertia::render('Admin/Contract/Show', $result);
    }
}
