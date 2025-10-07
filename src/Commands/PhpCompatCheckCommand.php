<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PhpCompatCheckCommand extends Command
{
    protected $signature = 'phpinspector-compat:check
                            {--php=8.4 : Target PHP version}';

    protected $description = 'Run PHPCompatibility check end-to-end';

    public function handle()
    {
        $phpVersion = $this->option('php');

        $this->info("\n🚀 Starting PHP Compatibility Check (PHP {$phpVersion})");

        // Step 1: Collect paths
        $this->info("\n1️⃣ Running phpinspector-compat:collect-paths...");
        $this->call('phpinspector-compat:collect-paths');

        // Step 2: Scan in batches
        $this->info("\n2️⃣ Running phpinspector-compat:scan-batch...");
        $batchNumber = 1;

        while (true) {
            $exitCode = $this->call('phpinspector-compat:scan-batch', [
                '--php' => $phpVersion,
            ]);

            if ($exitCode === 0) {
                $this->info("\n✅ All batches scanned successfully.");
                break;
            }

            $this->info("\n➡️ Batch {$batchNumber} completed. Scanning next batch...");
            $batchNumber++;
        }

        // Step 3: Merge reports
        $this->info("\n3️⃣ Running phpinspector-compat:merge-reports...");
        $this->call('phpinspector-compat:merge-reports');

        // ✅ Cleanup
        $this->cleanupTemporaryFiles();

        // ✅ Show summary
        $reportPath = storage_path('app/public/php-inspector-phpcompat_report.json');
        if (file_exists($reportPath)) {
            $startTime = microtime(true);

            $allResults = json_decode(file_get_contents($reportPath), true);

            $totalFiles = count($allResults);
            $totalErrors = collect($allResults)->sum(fn($f) => $f['errors'] ?? 0);
            $totalWarnings = collect($allResults)->sum(fn($f) => $f['warnings'] ?? 0);
            $filesWithIssues = collect($allResults)->filter(fn($f) => ($f['errors'] ?? 0) > 0 || ($f['warnings'] ?? 0) > 0)->count();
            $cleanFiles = $totalFiles - $filesWithIssues;

            $errorRate = $totalFiles > 0 ? round(($filesWithIssues / $totalFiles) * 100, 2) : 0;

            $this->info("\n🎉 PHP Compatibility check completed!");
            $this->info("\n📦 Report stored at: storage/app/public/php-inspector-phpcompat_report.json\n");

            $this->line("\n📊 === Summary Report ===");
            $this->line("\n📄 Total files scanned : <fg=green>{$totalFiles}</>");
            $this->line("\n✅ Clean files         : <fg=green>{$cleanFiles}</>");
            $this->line("\n🚨 Files with issues   : <fg=red>{$filesWithIssues}</> ({$errorRate}% of total)");
            $this->line("\n❌ Total errors        : <fg=red>{$totalErrors}</>");
            $this->line("\n⚠️  Total warnings      : <fg=yellow>{$totalWarnings}</>");

            $duration = round(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 2);
            $this->line("⏱️  Duration            : {$duration} sec");

            if ($filesWithIssues > 0) {
                $this->warn("\n🔎 Tip: Review detailed errors inside the JSON report for file paths and message details.");
            } else {
                $this->info("\n🌈 All files are fully PHP {$phpVersion} compatible. Excellent job!");
            }

        } else {
            $this->warn("⚠️ Report file not found at {$reportPath}");
        }

    }

    protected function cleanupTemporaryFiles()
    {
        $pathsToDelete = [
            storage_path('app/php-inspector/phpcompat_path.php'),
            storage_path('app/php-inspector/scanned_files.php'),
            storage_path('app/php-inspector/batches'),
        ];

        foreach ($pathsToDelete as $path) {
            if (file_exists($path)) {
                if (is_dir($path)) {
                    File::deleteDirectory($path);
                } else {
                    File::delete($path);
                }
            }
        }

        $this->info("\n🧹 Cleaned up temporary files from php-inspector directory.");
    }
}
