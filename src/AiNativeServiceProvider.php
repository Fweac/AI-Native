<?php

namespace AiNative\Laravel;

use AiNative\Laravel\Commands\GenerateFromJsonCommand;
use AiNative\Laravel\Commands\InstallCommand;
use AiNative\Laravel\Commands\ValidateSchemaCommand;
use Illuminate\Support\ServiceProvider;

class AiNativeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ai-native.php', 'ai-native');

        $this->app->singleton('ai-native.schema-parser', function ($app) {
            return new Parsers\SchemaParser();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateFromJsonCommand::class,
                InstallCommand::class,
                ValidateSchemaCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/../config/ai-native.php' => config_path('ai-native.php'),
        ], 'ai-native-config');

        $this->publishes([
            __DIR__ . '/../stubs' => resource_path('stubs/ai-native'),
        ], 'ai-native-stubs');
    }

    public function provides(): array
    {
        return ['ai-native.schema-parser'];
    }
}