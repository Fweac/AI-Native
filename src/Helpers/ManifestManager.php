<?php

namespace AiNative\Laravel\Helpers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class ManifestManager
{
    protected Filesystem $files;
    protected string $manifestPath;
    protected string $historyPath;
    protected array $manifest = [];

    public function __construct(Filesystem $files, string $basePath = null)
    {
        $this->files = $files;
        $basePath = $basePath ?? base_path();
        $this->manifestPath = $basePath . '/.ai-native-manifest.json';
        $this->historyPath = $basePath . '/.ai-native/history';
        
        $this->loadManifest();
    }

    protected function loadManifest(): void
    {
        if ($this->files->exists($this->manifestPath)) {
            $content = $this->files->get($this->manifestPath);
            $this->manifest = json_decode($content, true) ?: [];
        } else {
            $this->manifest = $this->getDefaultManifest();
        }
    }

    protected function getDefaultManifest(): array
    {
        return [
            'version' => '1.0.0',
            'generated_at' => now()->toISOString(),
            'schema_hash' => null,
            'schema_content' => null,
            'files' => [
                'models' => [],
                'controllers' => [],
                'migrations' => [],
                'factories' => [],
                'seeders' => [],
                'policies' => [],
                'observers' => [],
                'routes' => [],
                'config' => []
            ],
            'metadata' => [
                'total_files' => 0,
                'ai_native_version' => '1.0.0'
            ]
        ];
    }

    public function setSchema(array $schema): void
    {
        $schemaContent = json_encode($schema, JSON_PRETTY_PRINT);
        $schemaHash = md5($schemaContent);

        $this->manifest['schema_hash'] = $schemaHash;
        $this->manifest['schema_content'] = $schema;
        $this->manifest['generated_at'] = now()->toISOString();
    }

    public function hasSchemaChanged(array $newSchema): bool
    {
        $newHash = md5(json_encode($newSchema, JSON_PRETTY_PRINT));
        return $this->manifest['schema_hash'] !== $newHash;
    }

    public function getPreviousSchema(): ?array
    {
        return $this->manifest['schema_content'] ?? null;
    }

    public function addGeneratedFile(string $type, string $relativePath, array $metadata = []): void
    {
        if (!isset($this->manifest['files'][$type])) {
            $this->manifest['files'][$type] = [];
        }

        $this->manifest['files'][$type][$relativePath] = array_merge([
            'generated_at' => now()->toISOString(),
            'hash' => $this->files->exists(base_path($relativePath)) ? md5_file(base_path($relativePath)) : null,
            'size' => $this->files->exists(base_path($relativePath)) ? $this->files->size(base_path($relativePath)) : 0
        ], $metadata);

        $this->updateTotalFiles();
    }

    public function removeGeneratedFile(string $type, string $relativePath): void
    {
        if (isset($this->manifest['files'][$type][$relativePath])) {
            unset($this->manifest['files'][$type][$relativePath]);
            $this->updateTotalFiles();
        }
    }

    public function getGeneratedFiles(string $type = null): array
    {
        if ($type) {
            return $this->manifest['files'][$type] ?? [];
        }

        $allFiles = [];
        foreach ($this->manifest['files'] as $fileType => $files) {
            foreach ($files as $path => $metadata) {
                $allFiles[$path] = array_merge($metadata, ['type' => $fileType]);
            }
        }

        return $allFiles;
    }

    public function getFilesToCleanup(array $newSchema): array
    {
        $currentFiles = $this->getGeneratedFiles();
        $filesToKeep = $this->calculateRequiredFiles($newSchema);
        
        $filesToDelete = [];
        foreach ($currentFiles as $filePath => $metadata) {
            if (!in_array($filePath, $filesToKeep)) {
                $filesToDelete[] = $filePath;
            }
        }

        return $filesToDelete;
    }

    protected function calculateRequiredFiles(array $schema): array
    {
        $requiredFiles = [];
        $models = $schema['models'] ?? [];

        foreach ($models as $modelName => $modelConfig) {
            $studlyName = ucfirst($modelName);
            $snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));
            $tableName = $modelConfig['table'] ?? $snakeName . 's';

            // Models
            $requiredFiles[] = "app/Models/{$studlyName}.php";

            // Controllers (if routes defined)
            if (!empty($modelConfig['routes'])) {
                $requiredFiles[] = "app/Http/Controllers/{$studlyName}Controller.php";
            }

            // Migrations
            $requiredFiles[] = "database/migrations/*_create_{$tableName}_table.php";

            // Factories (if defined)
            if (isset($modelConfig['factory'])) {
                $requiredFiles[] = "database/factories/{$studlyName}Factory.php";
            }

            // Seeders (if defined)
            if (isset($modelConfig['seeder']) && $modelConfig['seeder'] === true) {
                $requiredFiles[] = "database/seeders/{$studlyName}Seeder.php";
            }

            // Policies (if defined)
            if (!empty($modelConfig['policies'])) {
                $requiredFiles[] = "app/Policies/{$studlyName}Policy.php";
            }

            // Observers (if defined)
            if (!empty($modelConfig['observers'])) {
                $requiredFiles[] = "app/Observers/{$studlyName}Observer.php";
            }
        }

        // Pivot migrations
        if (isset($schema['pivots'])) {
            foreach ($schema['pivots'] as $pivotName => $pivotConfig) {
                $requiredFiles[] = "database/migrations/*_create_{$pivotName}_table.php";
            }
        }

        // Always keep certain files
        $requiredFiles[] = "routes/api.php";
        $requiredFiles[] = "database/seeders/DatabaseSeeder.php";
        $requiredFiles[] = "app/Providers/AuthServiceProvider.php";
        $requiredFiles[] = "app/Providers/ObserverServiceProvider.php";

        return $requiredFiles;
    }

    public function cleanupObsoleteFiles(): array
    {
        $previousSchema = $this->getPreviousSchema();
        if (!$previousSchema) {
            return [];
        }

        $currentSchema = $this->manifest['schema_content'];
        $filesToDelete = $this->getFilesToCleanup($currentSchema);
        $deletedFiles = [];

        foreach ($filesToDelete as $filePath) {
            $fullPath = base_path($filePath);
            
            // Handle wildcard patterns for migrations
            if (strpos($filePath, '*') !== false) {
                $pattern = str_replace('*', '*', $fullPath);
                $matchingFiles = glob($pattern);
                
                foreach ($matchingFiles as $matchingFile) {
                    if ($this->files->exists($matchingFile)) {
                        $this->files->delete($matchingFile);
                        $relativePath = str_replace(base_path() . '/', '', $matchingFile);
                        $deletedFiles[] = $relativePath;
                    }
                }
            } else {
                if ($this->files->exists($fullPath)) {
                    $this->files->delete($fullPath);
                    $deletedFiles[] = $filePath;
                }
            }

            // Remove from manifest
            foreach ($this->manifest['files'] as $type => &$files) {
                if (isset($files[$filePath])) {
                    unset($files[$filePath]);
                }
            }
        }

        $this->updateTotalFiles();
        return $deletedFiles;
    }

    public function saveToHistory(): void
    {
        if (empty($this->manifest['schema_content'])) {
            return;
        }

        $this->ensureDirectoryExists($this->historyPath);

        $timestamp = now()->format('Y-m-d_H-i-s');
        $hash = substr($this->manifest['schema_hash'], 0, 8);
        $historyFile = "{$this->historyPath}/{$timestamp}_{$hash}.json";

        $historyData = [
            'timestamp' => now()->toISOString(),
            'schema_hash' => $this->manifest['schema_hash'],
            'manifest' => $this->manifest
        ];

        $this->files->put($historyFile, json_encode($historyData, JSON_PRETTY_PRINT));

        // Keep only last 10 history files
        $this->cleanupHistory();
    }

    protected function cleanupHistory(): void
    {
        if (!$this->files->exists($this->historyPath)) {
            return;
        }

        $historyFiles = collect($this->files->files($this->historyPath))
            ->map(function ($file) {
                return $file->getPathname();
            })
            ->sort()
            ->values();

        if ($historyFiles->count() > 10) {
            $filesToDelete = $historyFiles->take($historyFiles->count() - 10);
            foreach ($filesToDelete as $file) {
                $this->files->delete($file);
            }
        }
    }

    public function getHistory(): array
    {
        if (!$this->files->exists($this->historyPath)) {
            return [];
        }

        $history = [];
        $historyFiles = $this->files->files($this->historyPath);

        foreach ($historyFiles as $file) {
            $content = $this->files->get($file->getPathname());
            $data = json_decode($content, true);
            if ($data) {
                $history[] = [
                    'file' => $file->getFilename(),
                    'timestamp' => $data['timestamp'],
                    'schema_hash' => $data['schema_hash']
                ];
            }
        }

        return collect($history)->sortByDesc('timestamp')->values()->toArray();
    }

    public function save(): void
    {
        $this->ensureDirectoryExists(dirname($this->manifestPath));
        $this->files->put(
            $this->manifestPath,
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function updateTotalFiles(): void
    {
        $total = 0;
        foreach ($this->manifest['files'] as $files) {
            $total += count($files);
        }
        $this->manifest['metadata']['total_files'] = $total;
    }

    protected function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    public function getManifest(): array
    {
        return $this->manifest;
    }

    public function clear(): void
    {
        $this->manifest = $this->getDefaultManifest();
        if ($this->files->exists($this->manifestPath)) {
            $this->files->delete($this->manifestPath);
        }
    }
}