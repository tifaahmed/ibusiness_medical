<?php

namespace Database\Seeders\AlexandriaData;

use Illuminate\Console\Command;

class TestParserCommand extends Command
{
    protected $signature = 'alexandria:test-parser {file?}';
    protected $description = 'Test the Alexandria HTML parser';

    public function handle()
    {
        $filePath = $this->argument('file');
        
        if (!$filePath) {
            $filePath = base_path('database/seeders/AlexandriaData/alexandria.html');
        }
        
        if (!file_exists($filePath)) {
            $filePath = storage_path('app/alexandria.html');
        }
        
        if (!file_exists($filePath)) {
            $filePath = '/mnt/c/Users/nasse/Downloads/الاسكندرية.html';
        }

        if (!file_exists($filePath)) {
            $this->error("HTML file not found. Tried:");
            $this->error("  - " . base_path('database/seeders/AlexandriaData/alexandria.html'));
            $this->error("  - " . storage_path('app/alexandria.html'));
            $this->error("  - /mnt/c/Users/nasse/Downloads/الاسكندرية.html");
            return 1;
        }

        $this->info("Testing parser with file: {$filePath}");
        $this->info("File size: " . filesize($filePath) . " bytes");

        try {
            $parser = new AlexandriaDataParser($filePath);
            $facilities = $parser->parseFacilities();

            $this->info("Found " . count($facilities) . " facilities");

            if (count($facilities) > 0) {
                // Show summary
                $this->info("\n=== SUMMARY ===");
                $this->line("Total facilities: " . count($facilities));
                
                $totalBranches = 0;
                foreach ($facilities as $facility) {
                    $totalBranches += count($facility['branches'] ?? []);
                }
                $this->line("Total branches: " . $totalBranches);
                
                // Show first 3 facilities as example
                $this->info("\n=== FIRST 3 FACILITIES (EXAMPLE) ===");
                foreach (array_slice($facilities, 0, 3) as $index => $facility) {
                    $this->line("\n" . ($index + 1) . ". " . ($facility['name_ar'] ?? 'N/A'));
                    $this->line("   Name EN: " . ($facility['name_en'] ?? 'N/A'));
                    $this->line("   Type AR: " . ($facility['facility_type_ar'] ?? 'N/A'));
                    $this->line("   Type EN: " . ($facility['facility_type_en'] ?? 'N/A'));
                    $this->line("   Address AR: " . ($facility['address_ar'] ?? 'N/A'));
                    $this->line("   Address EN: " . ($facility['address_en'] ?? 'N/A'));
                    $this->line("   Phone: " . ($facility['phone'] ?? 'N/A'));
                    $this->line("   Branches: " . count($facility['branches'] ?? []));
                    if (!empty($facility['branches'])) {
                        foreach ($facility['branches'] as $branchIndex => $branch) {
                            $this->line("     Branch " . ($branchIndex + 1) . ":");
                            $this->line("       Address AR: " . ($branch['address_ar'] ?? 'N/A'));
                            $this->line("       Address EN: " . ($branch['address_en'] ?? 'N/A'));
                            $this->line("       Phone: " . ($branch['phone'] ?? 'N/A'));
                        }
                    }
                }
                
                // Output full JSON structure
                $this->info("\n=== FULL NESTED ARRAY STRUCTURE (JSON) ===");
                $this->line("Outputting complete data structure...");
                
                $jsonOutput = json_encode($facilities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                // Save to file
                $outputFile = storage_path('app/alexandria_parsed_data.json');
                file_put_contents($outputFile, $jsonOutput);
                $this->info("Full data structure saved to: " . $outputFile);
                
                // Also output to console (truncated if too long)
                if (strlen($jsonOutput) > 50000) {
                    $this->warn("Data is too large to display in console. Showing first 2000 characters:");
                    $this->line(substr($jsonOutput, 0, 2000) . "\n... (truncated, see file for full output)");
                } else {
                    $this->line($jsonOutput);
                }
                
                // Show statistics
                $this->info("\n=== STATISTICS ===");
                $facilitiesWithBranches = 0;
                $facilitiesWithoutBranches = 0;
                $facilitiesWithEnglish = 0;
                $facilitiesWithoutEnglish = 0;
                
                foreach ($facilities as $facility) {
                    if (!empty($facility['branches'])) {
                        $facilitiesWithBranches++;
                    } else {
                        $facilitiesWithoutBranches++;
                    }
                    
                    if (!empty($facility['name_en']) && !preg_match('/[\x{0600}-\x{06FF}]/u', $facility['name_en'])) {
                        $facilitiesWithEnglish++;
                    } else {
                        $facilitiesWithoutEnglish++;
                    }
                }
                
                $this->line("Facilities with branches: " . $facilitiesWithBranches);
                $this->line("Facilities without branches: " . $facilitiesWithoutBranches);
                $this->line("Facilities with English translation: " . $facilitiesWithEnglish);
                $this->line("Facilities without English translation: " . $facilitiesWithoutEnglish);
                
            } else {
                $this->warn("No facilities found. The parser may need adjustment.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}


