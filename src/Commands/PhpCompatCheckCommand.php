<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;

class PhpCompatCheckCommand extends Command
{
    protected $signature = 'phpcompat:check
                            {--php=8.4 : Target PHP version}';

    protected $description = 'Run PHPCompatibility check end-to-end';

    public function handle()
    {
        $phpVersion = $this->option('php');

        $this->info("🚀 Starting PHP Compatibility Check (PHP {$phpVersion})");

        // Step 1: Collect paths
        $this->info("\n1️⃣ Running phpcompat:collect-paths...");
        $this->call('phpcompat:collect-paths');

        // Step 2: Scan in batches
        $this->info("\n2️⃣ Running phpcompat:scan-batch...");
        // Keep running scan-batch until all files are scanned
        $batchNumber = 1;
        while (true) {
            $exitCode = $this->call('phpcompat:scan-batch', [
                '--php' => $phpVersion,
            ]);

            if ($exitCode === 0) {
                $this->info("✅ All batches scanned successfully.");
                break;
            }

            $this->info("➡️ Batch {$batchNumber} completed. Scanning next batch...");
            $batchNumber++;
        }

        // Step 3: Merge all batch reports
        $this->info("\n3️⃣ Running phpcompat:merge-reports...");
        $this->call('phpcompat:merge-reports');

        $this->info("\n🎉 PHP Compatibility check completed! Report is stored in storage/app/public.");
    }
}
