<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;

class CheckCompatibilityCommand extends Command
{
    protected $signature = 'phpcompat:check
                            {--php= : Target PHP version (overrides config)}
                            {--path= : Specific path to scan (overrides config)}';

    protected $description = 'Check PHP compatibility using PHPCompatibility and PHPCS';

    public function handle()
    {
        $phpVersion = $this->option('php') ?: '8.4';
        $exclude = ['vendor', 'storage'];
        $failOnError = true;
        $showWarnings = true;

        $paths = $this->option('path')
            ? [$this->option('path')]
            : [base_path('/')]; // keep it simple for now

        $this->info(" 🔍 Checking PHP compatibility for PHP {$phpVersion}");
        $this->info(" 📂 Paths to scan: " . implode(', ', $paths));
        $this->info(" ❌ Excluded paths: " . implode(', ', $exclude));

        $ignorePatterns = array_merge(['*.blade.php'], array_map(fn($d) => "$d/*", $exclude));

        $allResults = [];
        $totalFilesScanned = 0;

        foreach ($paths as $path) {
            $command = [
                base_path('vendor/squizlabs/php_codesniffer/bin/phpcs'),
                '--standard=PHPCompatibility',
                '--runtime-set', 'testVersion', $phpVersion,
                '--report=json',
                '--extensions=php',
                '--ignore=' . implode(',', $ignorePatterns),
                $path,
            ];

            if (!$showWarnings) {
                $command[] = '--warning-severity=0';
            }

            $this->line(' 🧩 Executing: ' . implode(' ', $command));

            $process = new Process($command);
            $process->setWorkingDirectory(base_path());
            $process->setTimeout(null);
            $process->run();

            $this->line(' ⚠️ STDERR: ' . $process->getErrorOutput());
            $this->line(' 📤 STDOUT: ' . substr($process->getOutput(), 0, 200));

            $results = json_decode($process->getOutput(), true);

            if (isset($results['files'])) {
                $allResults = array_merge($allResults, $results['files']);
                $totalFilesScanned += count($results['files']);
            }
        }

        $totalErrors = 0;
        $totalWarnings = 0;

        foreach ($allResults as $details) {
            $totalErrors += $details['errors'] ?? 0;
            $totalWarnings += $details['warnings'] ?? 0;
        }

        $this->info("\n📄 Total files scanned: {$totalFilesScanned}");
        $this->info(" ❌ Total errors: {$totalErrors}");
        $this->info(" ⚠️ Total warnings: {$totalWarnings}");

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
