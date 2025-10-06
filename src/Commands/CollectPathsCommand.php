<?php

namespace Devrabiul\LaravelPhpInspector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CollectPathsCommand extends Command
{
    protected $signature = 'phpinspector-compat:collect-paths
                            {--path= : Specific path to scan}';

    protected $description = 'Collect all PHP files paths for scanning';

    public function handle()
    {
        $paths = $this->option('path') 
            ? [$this->option('path')]
            : [base_path('/')];

        $exclude = ['vendor', 'storage', 'bootstrap', 'node_modules', 'public'];
        $phpFiles = [];

        $this->info("📦 Collecting PHP files...");

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

        $dir = storage_path('app/php-inspector');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $pathFile = $dir . '/phpcompat_path.php';
        $phpArrayContent = "<?php\n\nreturn " . var_export($phpFiles, true) . ";\n";
        file_put_contents($pathFile, $phpArrayContent);

        $this->info("💾 Collected " . count($phpFiles) . " PHP files.");
        $this->info("💾 Saved paths to: {$pathFile}");
    }
}
