<?php

namespace AiNative\Laravel\Parsers;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class SchemaParser
{
    protected array $schema = [];
    protected array $models = [];
    protected array $pivots = [];

    public function __construct(string $schemaPath = null)
    {
        if ($schemaPath) {
            $this->loadSchema($schemaPath);
        }
    }

    public function loadSchema(string $schemaPath): self
    {
        if (!file_exists($schemaPath)) {
            throw new InvalidArgumentException("Schema file not found: {$schemaPath}");
        }

        $json = file_get_contents($schemaPath);
        $this->schema = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid JSON: " . json_last_error_msg());
        }

        $this->models = $this->schema['models'] ?? [];
        $this->pivots = $this->schema['pivots'] ?? [];

        return $this;
    }

    public function fromArray(array $schema): self
    {
        $this->schema = $schema;
        $this->models = $this->schema['models'] ?? [];
        $this->pivots = $this->schema['pivots'] ?? [];

        return $this;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    // Meta information
    public function getProjectName(): string
    {
        return $this->schema['meta']['project'] ?? 'GeneratedAPI';
    }

    public function getVersion(): string
    {
        return $this->schema['meta']['version'] ?? '1.0.0';
    }

    public function getAuthConfig(): array
    {
        return $this->schema['meta']['auth'] ?? ['enabled' => false];
    }

    public function getDatabaseConfig(): array
    {
        return $this->schema['meta']['database'] ?? [];
    }

    public function getGlobalMiddlewares(): array
    {
        return $this->schema['meta']['middlewares'] ?? [];
    }

    public function getCacheConfig(): array
    {
        return $this->schema['meta']['cache'] ?? [];
    }

    public function getQueueConfig(): array
    {
        return $this->schema['meta']['queues'] ?? [];
    }

    // Models
    public function getModels(): array
    {
        return $this->models;
    }

    public function getModel(string $modelName): ?array
    {
        return $this->models[$modelName] ?? null;
    }

    public function getModelNames(): array
    {
        return array_keys($this->models);
    }

    public function hasModel(string $modelName): bool
    {
        return isset($this->models[$modelName]);
    }

    // Model fields
    public function getModelFields(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['fields'] ?? [];
    }

    public function getModelTable(string $modelName): string
    {
        $model = $this->getModel($modelName);
        return $model['table'] ?? strtolower($modelName) . 's';
    }

    // Relationships
    public function getModelRelations(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['relations'] ?? [];
    }

    // Routes
    public function getModelRoutes(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['routes'] ?? [];
    }

    // Validation rules
    public function getValidationRules(string $modelName): array
    {
        $fields = $this->getModelFields($modelName);
        $rules = [];

        foreach ($fields as $fieldName => $fieldDefinition) {
            if (is_string($fieldDefinition)) {
                // Simple format: "string|required"
                $parts = explode('|', $fieldDefinition);
                $type = array_shift($parts);
                
                if (!empty($parts)) {
                    $rules[$fieldName] = implode('|', $parts);
                }
            } elseif (is_array($fieldDefinition)) {
                // Advanced format with validation key
                if (isset($fieldDefinition['validation'])) {
                    $rules[$fieldName] = $fieldDefinition['validation'];
                }
            }
        }

        return $rules;
    }

    // Field types and parsing
    public function parseFieldType(string $fieldDefinition): array
    {
        $parts = explode('|', $fieldDefinition);
        $type = array_shift($parts);
        $validations = $parts;

        // Handle special types with parameters
        if (strpos($type, ':') !== false) {
            [$actualType, $params] = explode(':', $type, 2);
            
            switch ($actualType) {
                case 'foreign':
                    return [
                        'type' => 'unsignedBigInteger',
                        'foreign' => $params,
                        'validations' => $validations,
                        'migration_method' => 'foreignId'
                    ];
                    
                case 'enum':
                    $values = explode(',', $params);
                    return [
                        'type' => 'enum',
                        'values' => $values,
                        'validations' => array_merge($validations, ['in:' . $params]),
                        'migration_method' => 'enum'
                    ];
                    
                case 'decimal':
                    [$precision, $scale] = explode(',', $params);
                    return [
                        'type' => 'decimal',
                        'precision' => $precision,
                        'scale' => $scale,
                        'validations' => $validations,
                        'migration_method' => 'decimal'
                    ];
                    
                case 'file':
                case 'files':
                    return [
                        'type' => 'string',
                        'storage_disk' => $params,
                        'validations' => $validations,
                        'migration_method' => 'string',
                        'is_file' => true,
                        'multiple' => $actualType === 'files'
                    ];
            }
        }

        // Map simple types to Laravel migration methods
        $typeMapping = [
            'string' => 'string',
            'text' => 'text',
            'longText' => 'longText',
            'integer' => 'integer',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'timestamp' => 'timestamp',
            'json' => 'json',
            'float' => 'float',
            'uuid' => 'uuid'
        ];

        return [
            'type' => $type,
            'validations' => $validations,
            'migration_method' => $typeMapping[$type] ?? 'string'
        ];
    }

    // Scopes
    public function getModelScopes(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['scopes'] ?? [];
    }

    // Policies
    public function getModelPolicies(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['policies'] ?? [];
    }

    // Hooks
    public function getModelHooks(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['hooks'] ?? [];
    }

    // Observers
    public function getModelObservers(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['observers'] ?? [];
    }

    // Filters
    public function getModelFilters(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['filters'] ?? [];
    }

    // Factory and Seeder
    public function hasFactory(string $modelName): bool
    {
        $model = $this->getModel($modelName);
        return isset($model['factory']) || ($model['factory'] ?? false) === true;
    }

    public function getFactoryConfig(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['factory'] ?? [];
    }

    public function hasSeeder(string $modelName): bool
    {
        $model = $this->getModel($modelName);
        return ($model['seeder'] ?? false) === true;
    }

    // Cache configuration
    public function getModelCacheConfig(string $modelName): array
    {
        $model = $this->getModel($modelName);
        return $model['cache'] ?? [];
    }

    // Pivot tables
    public function getPivots(): array
    {
        return $this->pivots;
    }

    public function getPivot(string $pivotName): ?array
    {
        return $this->pivots[$pivotName] ?? null;
    }

    // Custom routes
    public function getCustomRoutes(): array
    {
        return $this->schema['custom']['routes'] ?? [];
    }

    // Middleware
    public function getMiddlewareConfig(): array
    {
        return $this->schema['middleware'] ?? [];
    }

    // Search configuration
    public function getSearchConfig(): array
    {
        return $this->schema['search'] ?? [];
    }

    // Storage configuration
    public function getStorageConfig(): array
    {
        return $this->schema['storage'] ?? [];
    }

    // Mail configuration
    public function getMailConfig(): array
    {
        return $this->schema['mail'] ?? [];
    }

    // Events configuration
    public function getEventsConfig(): array
    {
        return $this->schema['events'] ?? [];
    }

    // Utilities
    public function validate(): array
    {
        $errors = [];

        // Validate meta section
        if (!isset($this->schema['meta'])) {
            $errors[] = "Missing 'meta' section in schema";
        }

        // Validate models
        if (empty($this->models)) {
            $errors[] = "No models defined in schema";
        }

        foreach ($this->models as $modelName => $modelConfig) {
            if (empty($modelConfig['fields'])) {
                $errors[] = "Model '{$modelName}' has no fields defined";
            }

            // Validate foreign key references
            foreach ($modelConfig['fields'] ?? [] as $fieldName => $fieldDefinition) {
                if (strpos($fieldDefinition, 'foreign:') === 0) {
                    $foreignTable = explode(':', $fieldDefinition)[1];
                    $foreignTable = explode('|', $foreignTable)[0];
                    
                    $referencedModel = $this->findModelByTable($foreignTable);
                    if (!$referencedModel) {
                        $errors[] = "Model '{$modelName}' field '{$fieldName}' references unknown table '{$foreignTable}'";
                    }
                }
            }

            // Validate relationships
            foreach ($modelConfig['relations'] ?? [] as $relationName => $relationDefinition) {
                if (strpos($relationDefinition, ':') !== false) {
                    $relatedModel = explode(':', $relationDefinition)[1];
                    $relatedModel = explode(',', $relatedModel)[0];
                    
                    if (!$this->hasModel($relatedModel)) {
                        $errors[] = "Model '{$modelName}' relation '{$relationName}' references unknown model '{$relatedModel}'";
                    }
                }
            }
        }

        return $errors;
    }

    protected function findModelByTable(string $tableName): ?string
    {
        foreach ($this->models as $modelName => $modelConfig) {
            $modelTable = $modelConfig['table'] ?? strtolower($modelName) . 's';
            if ($modelTable === $tableName) {
                return $modelName;
            }
        }
        return null;
    }

    public function toArray(): array
    {
        return $this->schema;
    }
}