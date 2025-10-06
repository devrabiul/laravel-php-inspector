<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;

class PhpCompatCheckCommand extends Command
{
    protected $signature = 'phpinspector-compat:check
                            {--php=8.4 : Target PHP version}';

    protected $description = 'Run PHPCompatibility check end-to-end';

    public function handle()
    {
        $phpVersion = $this->option('php');

        $this->info("🚀 Starting PHP Compatibility Check (PHP {$phpVersion})");

        // Step 1: Collect paths
        $this->info("\n1️⃣ Running phpinspector-compat:collect-paths...");
        $this->call('phpinspector-compat:collect-paths');

        // Step 2: Scan in batches
        $this->info("\n2️⃣ Running phpinspector-compat:scan-batch...");
        // Keep running scan-batch until all files are scanned
        $batchNumber = 1;
        while (true) {
            $exitCode = $this->call('phpinspector-compat:scan-batch', [
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
        $this->info("\n3️⃣ Running phpinspector-compat:merge-reports...");
        $this->call('phpinspector-compat:merge-reports');

        $this->info("\n🎉 PHP Compatibility check completed! Report is stored in storage/app/public.");

        // ✅ Read the merged report
        $reportPath = storage_path('app/public/php-inspector-phpcompat_report.json');
        if (file_exists($reportPath)) {
            $allResults = json_decode(file_get_contents($reportPath), true);

            $totalFiles = count($allResults);
            $totalErrors = collect($allResults)->sum('errors');
            $totalWarnings = collect($allResults)->sum('warnings');

            // ✅ Display the summary
            $this->info("\n📄 Total files scanned: {$totalFiles}");
            $this->info(" ❌ Total errors: {$totalErrors}");
            $this->info(" ⚠️ Total warnings: {$totalWarnings}");
        } else {
            $this->warn("⚠️ Report file not found at {$reportPath}");
        }
    }
}
