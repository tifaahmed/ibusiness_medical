<?php

namespace App\Http\Controllers\Admin\Company\Store;

use App\Http\Controllers\Admin\Company\Actions\Store\StoreCompanyAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Company\StoreCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminCompanyStoreController extends BaseController
{
    public function __construct(private StoreCompanyAction $storeAction) {}

    public function __invoke(StoreCompanyRequest $request): RedirectResponse
    {
        try {
            $company = $this->storeAction->execute($request->validated());
            Log::info('Company store request', ['id' => $company->id, 'ip' => $request->ip()]);
            return redirect()->route('admin.company.list')->with('success', 'Company created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create company. Please try again.'])->withInput();
        }
    }
}
