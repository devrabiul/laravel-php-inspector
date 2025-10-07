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
        $batchDir = storage_path('app/php-inspector/batches');
        $reportDir = storage_path('app/php-inspector/reports');

        if (!File::exists($batchDir)) {
            $this->error("No batch reports found.");
            return 1;
        }

        if (!File::exists($reportDir)) {
            File::makeDirectory($reportDir, 0755, true);
        }

        $files = File::files($batchDir);
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

        // ✅ Save report to storage/app/php-inspector/reports/
        $reportFile = 'phpcompat_report_' . now()->format('Ymd_His') . '.json';
        $reportPath = $reportDir . '/' . $reportFile;

        File::put($reportPath, json_encode($mergedResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("💾 Final merged report saved: {$reportPath}");

        // ✅ Summary
        $totalFiles = count($mergedResults);
        $totalErrors = collect($mergedResults)->sum('errors');
        $totalWarnings = collect($mergedResults)->sum('warnings');
        $cleanFiles = collect($mergedResults)->filter(fn($r) => ($r['errors'] + $r['warnings']) === 0)->count();

        $this->info("\n📊 Summary:");
        $this->info("📄 Total files: {$totalFiles}");
        $this->info("❌ Errors: {$totalErrors}");
        $this->info("⚠️ Warnings: {$totalWarnings}");
        $this->info("✅ Clean files: {$cleanFiles}");

        // ✅ Show errors/warnings table (first 50)
        $tableData = [];
        foreach ($mergedResults as $file => $details) {
            foreach ($details['messages'] as $msg) {
                $tableData[] = [
                    'File' => Str::after($file, base_path() . '/'),
                    'Line' => $msg['line'] ?? '-',
                    'Type' => $msg['type'] ?? '-',
                    'Message' => $msg['message'] ?? '',
                ];
            }
        }

        if (!empty($tableData)) {
            $this->line("\n🔹 Sample issues (first 50):");
            $this->table(['File', 'Line', 'Type', 'Message'], array_slice($tableData, 0, 50));
        } else {
            $this->info("\n✅ No issues found across all files!");
        }

        // ✅ Clean up batch directory
        File::deleteDirectory($batchDir);
        $this->info("\n🧹 Deleted all temporary batch reports.");

        return 0;
    }
}
