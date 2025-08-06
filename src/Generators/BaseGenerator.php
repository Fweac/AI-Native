<?php

namespace AiNative\Laravel\Generators;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

abstract class BaseGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files = null)
    {
        $this->files = $files ?: new Filesystem();
    }

    protected function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
    }

    protected function writeFile(string $path, string $content): bool
    {
        $this->ensureDirectoryExists($path);
        
        return $this->files->put($path, $content) !== false;
    }

    protected function getStubPath(string $stubName): string
    {
        return __DIR__ . "/../../stubs/{$stubName}.stub";
    }

    protected function loadStub(string $stubName): string
    {
        $stubPath = $this->getStubPath($stubName);
        
        if (!$this->files->exists($stubPath)) {
            throw new \InvalidArgumentException("Stub file not found: {$stubPath}");
        }
        
        return $this->files->get($stubPath);
    }

    protected function replaceStubVariables(string $stub, array $replacements): string
    {
        foreach ($replacements as $placeholder => $replacement) {
            $stub = str_replace("{{ {$placeholder} }}", $replacement, $stub);
        }
        
        return $stub;
    }

    protected function studlyCase(string $value): string
    {
        return Str::studly($value);
    }

    protected function camelCase(string $value): string
    {
        return Str::camel($value);
    }

    protected function snakeCase(string $value): string
    {
        return Str::snake($value);
    }

    protected function pluralize(string $value): string
    {
        return Str::plural($value);
    }

    protected function singularize(string $value): string
    {
        return Str::singular($value);
    }
}