<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;

class ScanBatchCommand extends Command
{
    protected $signature = 'phpinspector-compat:scan-batch
                            {--php= : Target PHP version}
                            {--batchSize=500 : Number of files per batch}';

    protected $description = 'Scan PHP files in batches using PHPCompatibility';

    public function handle()
    {
        $phpVersion = $this->option('php') ?: '8.4';
        $batchSize = (int) $this->option('batchSize');
        $pathFile = storage_path('app/php-inspector/phpcompat_path.php');

        if (!File::exists($pathFile)) {
            $this->error("Path file not found. Run phpinspector-compat:collect-paths first.");
            return 1;
        }

        $allFiles = include $pathFile;

        // Load already scanned files
        $scannedFile = storage_path('app/php-inspector/scanned_files.php');
        $scanned = File::exists($scannedFile) ? include $scannedFile : [];

        // Get next batch
        $remainingFiles = array_diff($allFiles, $scanned);
        if (empty($remainingFiles)) {
            $this->info("✅ All files have already been scanned.");
            return 0;
        }

        $batch = array_slice($remainingFiles, 0, $batchSize);
        $this->info("➡️ Scanning batch of " . count($batch) . " files...");

        $command = [
            PHP_BINARY,
            '-d', 'memory_limit=-1',
            base_path('vendor/squizlabs/php_codesniffer/bin/phpcs'),
            '--standard=PHPCompatibility',
            '--runtime-set', 'testVersion', $phpVersion,
            '--report=json',
            '--extensions=php',
        ];

        $command = array_merge($command, $batch);

        $process = new Process($command);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(null);
        $process->run();

        $output = $process->getOutput();

        $dir = storage_path('app/php-inspector/batches');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $batchFile = $dir . '/batch_' . time() . '.json';
        file_put_contents($batchFile, $output);

        $this->info("💾 Batch report saved: {$batchFile}");

        // Mark files as scanned
        $scanned = array_merge($scanned, $batch);
        $scannedContent = "<?php\n\nreturn " . var_export($scanned, true) . ";\n";
        file_put_contents($scannedFile, $scannedContent);

        // Return 1 to indicate more batches remaining
        return count($remainingFiles) > $batchSize ? 1 : 0;
    }
}
