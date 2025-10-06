<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MergeReportsCommand extends Command
{
    protected $signature = 'phpcompat:merge-reports';

    protected $description = 'Merge all batch reports into a single JSON and display errors';

    public function handle()
    {
        $dir = storage_path('app/php-inspector/batches');
        if (!File::exists($dir)) {
            $this->error("No batch reports found.");
            return 1;
        }

        $files = File::files($dir);
        $allResults = [];

        foreach ($files as $file) {
            $content = json_decode(File::get($file), true);
            if (isset($content['files'])) {
                $allResults = array_merge($allResults, $content['files']);
            }
        }

        $reportPath = storage_path('app/public/php-inspector-phpcompat_report.json');
        file_put_contents($reportPath, json_encode($allResults, JSON_PRETTY_PRINT));
        $this->info("💾 Final merged report: {$reportPath}");

        // Show errors in terminal (first 50)
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
    }
}
