<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MergeReportsCommand extends Command
{
    protected $signature = 'phpinspector-compat:merge-reports';
    protected $description = 'Merge all batch reports into one final JSON report';

    public function handle()
    {
        $dir = storage_path('app/php-inspector/batches');

        if (!File::exists($dir)) {
            $this->error("No batch reports found.");
            return 1;
        }

        $files = File::files($dir);
        $mergedResults = [];

        foreach ($files as $file) {
            $content = json_decode(File::get($file), true);
            if (isset($content['files'])) {
                foreach ($content['files'] as $path => $data) {
                    $mergedResults[$path] = [
                        'errors' => count(array_filter($data['messages'], fn($m) => $m['type'] === 'ERROR')),
                        'warnings' => count(array_filter($data['messages'], fn($m) => $m['type'] === 'WARNING')),
                        'messages' => $data['messages'],
                    ];
                }
            }
        }

        // Save final JSON report
        $reportPath = storage_path('app/public/php-inspector-phpcompat_report.json');
        File::put($reportPath, json_encode($mergedResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("💾 Final merged report saved: {$reportPath}");

        // Optional: show summary in console
        $totalFiles = count($mergedResults);
        $totalErrors = collect($mergedResults)->sum('errors');
        $totalWarnings = collect($mergedResults)->sum('warnings');

        $this->info("\n📊 Summary:");
        $this->info("📄 Total files: {$totalFiles}");
        $this->info("❌ Errors: {$totalErrors}");
        $this->info("⚠️ Warnings: {$totalWarnings}");

        // Clean up batch directory
        File::deleteDirectory($dir);
        $this->info("\n🧹 Deleted all temporary batch reports.");
    }
}
