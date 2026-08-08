<?php

namespace App\Http\Controllers\Admin\Company\Actions\Update;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateCompanyAction
{
    public function execute(Company $company, array $validated): Company
    {
        DB::beginTransaction();
        try {
            $company->update(['name' => $validated['name']]);
            $company->refresh();
            DB::commit();
            Log::info('Company updated', ['id' => $company->id, 'slug' => $company->slug]);
            return $company;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update company', ['id' => $company->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
