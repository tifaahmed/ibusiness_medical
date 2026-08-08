<?php

namespace App\Http\Controllers\Admin\Contract\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Contract\Edit\AdminContractEditResource;
use App\Models\Contract;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminContractEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_CONTRACTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_CONTRACTS; }

    /**
     * Show the form for editing the specified contract.
     */
    public function __invoke(Request $request, string $contract): Response
    {
        $contract = Contract::where('slug', $contract)->firstOrFail();
        $this->assertOwns($contract);

        $result = [
            'contract' => (new AdminContractEditResource($contract))->toArray($request),
        ];

        return Inertia::render('Admin/Contract/Form/ContractFormView', $result);
    }
}
