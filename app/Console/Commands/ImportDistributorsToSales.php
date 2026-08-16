<?php

namespace App\Console\Commands;

use App\Models\Sales;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDistributorsToSales extends Command
{
    protected $signature = 'sales:import-distributors
        {--fresh : Wipe existing sales rows before importing}';

    protected $description = 'Import distributor names from a JSON file into the sales table';

    public function handle(): int
    {
        $path = base_path('distributors.json');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $names = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($names) || array_is_list($names) === false) {
            $this->error('Expected a JSON array of names.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Sales::query()->delete();
            $this->warn('Deleted all existing sales rows.');
        }

        $createdBy = User::value('id');

        DB::beginTransaction();

        try {
            foreach ($names as $name) {
                Sales::create([
                    'name' => ['ar' => $name],
                    'created_by' => $createdBy,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported ".count($names)." distributor(s) into sales.");

        return self::SUCCESS;
    }
}
