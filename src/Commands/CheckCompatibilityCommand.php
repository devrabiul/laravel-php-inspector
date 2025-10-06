<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CheckCompatibilityCommand extends Command
{
    protected $signature = 'phpinspector-compat:check
                            {--php= : Target PHP version (overrides config)}
                            {--path= : Specific path to scan (overrides config)}';

    protected $description = 'Check PHP compatibility using PHPCompatibility and PHPCS';

    public function handle()
    {
        $phpVersion = $this->option('php') ?: '8.4';
        $exclude = ['vendor', 'storage', 'bootstrap', 'node_modules', 'public'];
        $failOnError = true;
        $showWarnings = true;
        $batchSize = 500;

        $paths = $this->option('path')
            ? [$this->option('path')]
            : [base_path('/')];

        $this->info("🔍 Checking PHP compatibility for PHP {$phpVersion}");
        $this->info("📂 Paths to scan: " . implode(', ', $paths));
        $this->info("❌ Excluded paths: " . implode(', ', $exclude));

        // 🧩 1. Collect PHP files (excluding blade + excluded dirs)
        $this->info("📦 Collecting PHP files...");
        $phpFiles = [];

        foreach ($paths as $path) {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                $filePath = $file->getRealPath();

                if (Str::endsWith($filePath, '.php') && !Str::endsWith($filePath, '.blade.php')) {
                    $isExcluded = collect($exclude)->contains(fn($dir) => Str::contains($filePath, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR));
                    if (!$isExcluded) {
                        $phpFiles[] = $filePath;
                    }
                }
            }
        }

        $this->info("📄 Found " . count($phpFiles) . " PHP files.");

        // Save paths list for reference
        $pathFile = storage_path('app/php-inspector-phpcompat_path.json');
        file_put_contents($pathFile, json_encode($phpFiles, JSON_PRETTY_PRINT));
        $this->info("💾 File list saved to: storage/app/php-inspector-phpcompat_path.json");

        // 🧩 2. Process in batches
        $allResults = [];
        $batches = array_chunk($phpFiles, $batchSize);
        $batchCount = count($batches);
        $this->info("⚙️ Processing in {$batchCount} batches of {$batchSize} files each...\n");

        foreach ($batches as $index => $batch) {
            $this->info("➡️ Batch " . ($index + 1) . " of {$batchCount} (" . count($batch) . " files)");

            $command = [
                PHP_BINARY,
                '-d', 'memory_limit=-1',
                base_path('vendor/squizlabs/php_codesniffer/bin/phpcs'),
                '--standard=PHPCompatibility',
                '--runtime-set', 'testVersion', $phpVersion,
                '--report=json',
                '--extensions=php',
                '--parallel=8',
            ];

            if (!$showWarnings) {
                $command[] = '--warning-severity=0';
            }

            // Add files to command
            $command = array_merge($command, $batch);

            $process = new Process($command);
            $process->setWorkingDirectory(base_path());
            $process->setTimeout(null);
            $process->run();

            $results = json_decode($process->getOutput(), true);
            if (isset($results['files'])) {
                $allResults = array_merge($allResults, $results['files']);
            }

            $this->line("✅ Completed batch " . ($index + 1));
        }

        // 🧩 3. Summarize and save
        $totalFiles = count($allResults);
        $totalErrors = collect($allResults)->sum('errors');
        $totalWarnings = collect($allResults)->sum('warnings');

        $reportPath = storage_path('app/php-inspector-phpcompat_report.json');
        file_put_contents($reportPath, json_encode($allResults, JSON_PRETTY_PRINT));

        $this->info("\n📊 Summary");
        $this->info("📄 Files scanned: {$totalFiles}");
        $this->info("❌ Errors: {$totalErrors}");
        $this->info("⚠️ Warnings: {$totalWarnings}");
        $this->info("💾 Report saved to: storage/app/php-inspector-phpcompat_report.json");

        // 🧩 4. Display first 50 issues
        $tableData = [];
        foreach ($allResults as $file => $details) {
            foreach ($details['messages'] as $msg) {
                $tableData[] = [
                    'File' => Str::after($file, base_path() . '/'),
                    'Line' => $msg['line'],
                    'Type' => $msg['type'],
                    'Message' => $msg['message'],
                ];
            }
        }

        if (!empty($tableData)) {
            $this->line("\n🔹 Sample issues (first 50):");
            $this->table(['File', 'Line', 'Type', 'Message'], array_slice($tableData, 0, 50));
        }

        return $failOnError && $totalErrors > 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }
}
