<?php

namespace AiNative\Laravel\Commands;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Console\Command;

class ValidateSchemaCommand extends Command
{
    protected $signature = 'ai-native:validate {schema : The JSON schema file path}';

    protected $description = 'Validate an AI-Native JSON schema file';

    public function handle(): int
    {
        $schemaPath = $this->argument('schema');
        
        if (!file_exists($schemaPath)) {
            $this->error("Schema file not found: {$schemaPath}");
            return 1;
        }

        $this->info('Validating AI-Native schema...');
        $this->line("File: {$schemaPath}");
        $this->line('');

        try {
            $parser = new SchemaParser($schemaPath);
        } catch (\Exception $e) {
            $this->error("Error parsing schema: " . $e->getMessage());
            return 1;
        }

        // Run validation
        $errors = $parser->validate();

        if (empty($errors)) {
            $this->info('✅ Schema is valid!');
            $this->showSchemaInfo($parser);
            return 0;
        } else {
            $this->error('❌ Schema validation failed:');
            $this->line('');
            
            foreach ($errors as $error) {
                $this->line("  • {$error}");
            }
            
            return 1;
        }
    }

    protected function showSchemaInfo(SchemaParser $parser): void
    {
        $this->line('');
        $this->info('Schema Information:');
        
        // Project info
        $this->line("Project: {$parser->getProjectName()}");
        $this->line("Version: {$parser->getVersion()}");
        
        // Models
        $modelNames = $parser->getModelNames();
        $this->line("Models: " . count($modelNames) . " (" . implode(', ', $modelNames) . ")");
        
        // Auth
        $authConfig = $parser->getAuthConfig();
        if ($authConfig['enabled'] ?? false) {
            $this->line("Authentication: Enabled ({$authConfig['provider']})");
        } else {
            $this->line("Authentication: Disabled");
        }
        
        // Pivots
        $pivots = $parser->getPivots();
        if (!empty($pivots)) {
            $this->line("Pivot tables: " . count($pivots) . " (" . implode(', ', array_keys($pivots)) . ")");
        }
        
        // Custom routes
        $customRoutes = $parser->getCustomRoutes();
        if (!empty($customRoutes)) {
            $this->line("Custom routes: " . count($customRoutes));
        }

        $this->line('');
    }
}