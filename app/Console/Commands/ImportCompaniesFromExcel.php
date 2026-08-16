<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportCompaniesFromExcel extends Command
{
    protected $signature = 'companies:import
        {--fresh : Wipe existing companies before importing}';

    protected $description = 'Import companies from the fixed companies_import.xlsx file';

    public function handle(): int
    {
        $path = base_path('companies_import.xlsx');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $spreadsheet->disconnectWorksheets();

        $companies = [];
        foreach ($rows as $row) {
            if (($row[0] ?? null) === null || trim((string) $row[0]) === '') {
                continue;
            }
            $name = trim((string) ($row[2] ?? ''));
            if ($name === '') {
                continue;
            }
            $companies[] = [
                'id' => (int) $row[0],
                'name' => $name,
            ];
        }

        if (empty($companies)) {
            $this->warn('No company rows found in the file.');

            return self::SUCCESS;
        }

        if ($this->option('fresh')) {
            Company::query()->delete();
            $this->warn('Deleted all existing companies.');
        }

        $createdBy = User::value('id');

        app()->setLocale('ar');

        DB::beginTransaction();

        try {
            foreach ($companies as $company) {
                Company::forceCreate([
                    'id' => $company['id'],
                    'name' => ['ar' => $company['name']],
                    'created_by' => $createdBy,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Imported '.count($companies).' company(ies).');

        return self::SUCCESS;
    }
}
