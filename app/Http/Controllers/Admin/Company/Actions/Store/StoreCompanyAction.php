<?php

namespace App\Http\Controllers\Admin\Company\Actions\Store;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreCompanyAction
{
    public function execute(array $validated): Company
    {
        DB::beginTransaction();
        try {
            $company = Company::create([
                'name' => $validated['name'],
                'created_by' => Auth::id(),
            ]);
            DB::commit();
            Log::info('Company created', ['id' => $company->id, 'slug' => $company->slug]);
            return $company;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create company', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
