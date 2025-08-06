<?php

namespace AiNative\Laravel\Helpers;

use Illuminate\Support\Facades\File;

class ConfigManager
{
    public function updateCorsConfig(array $corsConfig): void
    {
        $configPath = config_path('cors.php');
        
        if (!File::exists($configPath)) {
            return; // CORS config doesn't exist, skip
        }

        $config = include $configPath;

        if (isset($corsConfig['allowed_origins'])) {
            $config['allowed_origins'] = $corsConfig['allowed_origins'];
        }

        if (isset($corsConfig['allowed_methods'])) {
            $config['allowed_methods'] = $corsConfig['allowed_methods'];
        }

        if (isset($corsConfig['allowed_headers'])) {
            $config['allowed_headers'] = $corsConfig['allowed_headers'];
        }

        $this->writeConfigFile($configPath, $config);
    }

    public function updateFilesystemConfig(array $storageConfig): void
    {
        if (!isset($storageConfig['disks'])) {
            return;
        }

        $configPath = config_path('filesystems.php');
        $config = include $configPath;

        foreach ($storageConfig['disks'] as $disk => $driver) {
            if ($driver === 'public') {
                $config['disks'][$disk] = [
                    'driver' => 'local',
                    'root' => storage_path("app/public/{$disk}"),
                    'url' => env('APP_URL') . "/storage/{$disk}",
                    'visibility' => 'public',
                ];
            } elseif ($driver === 'local') {
                $config['disks'][$disk] = [
                    'driver' => 'local',
                    'root' => storage_path("app/private/{$disk}"),
                ];
            }
        }

        $this->writeConfigFile($configPath, $config);
    }

    public function ensureApiRoutesEnabled(): void
    {
        $appPath = base_path('bootstrap/app.php');
        
        if (!File::exists($appPath)) {
            return;
        }

        $content = File::get($appPath);

        // Check if API routes are already configured
        if (str_contains($content, 'api:') || str_contains($content, 'routes/api.php')) {
            return;
        }

        // Look for the withRouting configuration and add api parameter if missing
        $pattern = '/->withRouting\s*\(\s*([\s\S]*?)\s*\)/';
        
        if (preg_match($pattern, $content, $matches)) {
            $routingConfig = trim($matches[1]);
            
            // Check if api parameter is missing
            if (!str_contains($routingConfig, 'api:')) {
                // Parse existing parameters to add api in correct position
                if (preg_match('/web:\s*[^,\n]+/', $routingConfig)) {
                    // Add api after web parameter
                    $newRoutingConfig = preg_replace(
                        '/(web:\s*[^,\n]+)(,?)(\s*)/',
                        '$1,$3        api: __DIR__.\'/../routes/api.php\',$3',
                        $routingConfig
                    );
                } else {
                    // Add api as first parameter
                    $newRoutingConfig = "api: __DIR__.'/../routes/api.php',\n        " . $routingConfig;
                }
                
                $newContent = str_replace($matches[1], $newRoutingConfig, $content);
                File::put($appPath, $newContent);
            }
        }
    }

    public function createApiRoutesFile(): void
    {
        $apiRoutesPath = base_path('routes/api.php');
        
        if (!File::exists($apiRoutesPath)) {
            $content = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n// AI-Native generated routes will be placed here\n";
            File::put($apiRoutesPath, $content);
        }
    }

    protected function writeConfigFile(string $path, array $config): void
    {
        $content = "<?php\n\nreturn " . $this->arrayToString($config, 0) . ";\n";
        File::put($path, $content);
    }

    protected function arrayToString(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $result = "[\n";

        foreach ($array as $key => $value) {
            $result .= $spaces . '    ';
            
            if (is_string($key)) {
                $result .= "'" . addslashes($key) . "' => ";
            }

            if (is_array($value)) {
                $result .= $this->arrayToString($value, $indent + 1);
            } elseif (is_string($value)) {
                $result .= "'" . addslashes($value) . "'";
            } elseif (is_bool($value)) {
                $result .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $result .= 'null';
            } else {
                $result .= $value;
            }

            $result .= ",\n";
        }

        $result .= $spaces . ']';
        return $result;
    }

    public function publishSanctumConfig(): void
    {
        // Check if Sanctum config exists
        if (!File::exists(config_path('sanctum.php'))) {
            // Run artisan command to publish Sanctum config
            if (class_exists(\Laravel\Sanctum\SanctumServiceProvider::class)) {
                \Artisan::call('vendor:publish', [
                    '--provider' => 'Laravel\Sanctum\SanctumServiceProvider',
                    '--tag' => 'sanctum-config'
                ]);
            }
        }
    }
}