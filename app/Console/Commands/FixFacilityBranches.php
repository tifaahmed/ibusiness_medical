<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\FacilityBranch;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FixFacilityBranches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facilities:fix-branches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure all facilities have at least one branch';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking facilities for missing branches...');

        $facilities = Facility::all();
        $fixed = 0;

        foreach ($facilities as $facility) {
            $branchCount = FacilityBranch::where('facility_id', $facility->id)->count();

            if ($branchCount === 0) {
                // Create main branch from facility data
                $branchSlug = Str::slug($facility->slug . '-main');

                FacilityBranch::updateOrCreate(
                    [
                        'slug' => $branchSlug,
                    ],
                    [
                        'facility_id' => $facility->id,
                        'name' => [
                            'ar' => 'الفرع الرئيسي',
                            'en' => 'Main Branch',
                        ],
                        'address' => $facility->address ?: [
                            'ar' => '',
                            'en' => '',
                        ],
                        'phone' => $facility->phone,
                    ]
                );

                $fixed++;
                $facilityName = is_array($facility->name) ? ($facility->name['ar'] ?? $facility->name['en'] ?? 'Unknown') : $facility->name;
                $this->line("✓ Created branch for facility: {$facilityName}");
            }
        }

        if ($fixed > 0) {
            $this->info("Fixed {$fixed} facilities by adding missing branches.");
        } else {
            $this->info('All facilities already have branches.');
        }

        return Command::SUCCESS;
    }
}

