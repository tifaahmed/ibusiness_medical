<?php

namespace App\Http\Controllers\Admin\Contract\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Contract\Actions\Update\UpdateContractAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Contract\UpdateContractRequest;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminContractUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_CONTRACTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_CONTRACTS; }

    private UpdateContractAction $updateAction;

    public function __construct(UpdateContractAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    /**
     * Update the specified contract.
     */
    public function __invoke(
        UpdateContractRequest $request,
        string $contract,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $contract = Contract::where('slug', $contract)->firstOrFail();
            $this->assertOwns($contract);

            $updatedContract = $this->updateAction->execute($contract, $validated);

            Log::info('Contract updated successfully', [
                'contract_id' => $updatedContract->id,
                'contract_slug' => $updatedContract->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.contract.list')
                ->with('success', 'Contract updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update contract', [
                'contract_slug' => $contract,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update contract. Please try again.'])
                ->withInput();
        }
    }
}
