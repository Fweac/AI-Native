<?php

namespace AiNative\Laravel\Generators;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Support\Str;

class ModelGenerator extends BaseGenerator
{
    public function generate(string $modelName, SchemaParser $parser): string
    {
        $model = $parser->getModel($modelName);
        $fields = $parser->getModelFields($modelName);
        $relations = $parser->getModelRelations($modelName);
        $scopes = $parser->getModelScopes($modelName);
        $cacheConfig = $parser->getModelCacheConfig($modelName);
        
        $className = Str::studly($modelName);
        $tableName = $parser->getModelTable($modelName);
        
        $fillable = $this->generateFillable($fields);
        $casts = $this->generateCasts($fields, $parser);
        $relationMethods = $this->generateRelations($relations);
        $scopeMethods = $this->generateScopes($scopes);
        
        $imports = $this->generateImports($relations, $fields);
        
        $stub = $this->getStub('model');
        
        return str_replace([
            '{{ namespace }}',
            '{{ imports }}',
            '{{ class }}',
            '{{ table }}',
            '{{ fillable }}',
            '{{ casts }}',
            '{{ relations }}',
            '{{ scopes }}',
            '{{ traits }}'
        ], [
            'App\\Models',
            $imports,
            $className,
            $tableName,
            $fillable,
            $casts,
            $relationMethods,
            $scopeMethods,
            $this->generateTraits($fields, $cacheConfig)
        ], $stub);
    }

    protected function generateFillable(array $fields): string
    {
        $fillableFields = [];
        
        foreach ($fields as $fieldName => $fieldDefinition) {
            // Skip timestamps and auto-generated fields
            if (!in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                $fillableFields[] = "'{$fieldName}'";
            }
        }
        
        if (empty($fillableFields)) {
            return '';
        }
        
        $fieldsString = implode(",\n        ", $fillableFields);
        return "protected \$fillable = [\n        {$fieldsString}\n    ];";
    }

    protected function generateCasts(array $fields, SchemaParser $parser): string
    {
        $casts = [];
        
        foreach ($fields as $fieldName => $fieldDefinition) {
            $fieldType = $parser->parseFieldType($fieldDefinition);
            
            switch ($fieldType['type']) {
                case 'boolean':
                    $casts[] = "'{$fieldName}' => 'boolean'";
                    break;
                case 'json':
                    $casts[] = "'{$fieldName}' => 'array'";
                    break;
                case 'date':
                    $casts[] = "'{$fieldName}' => 'date'";
                    break;
                case 'datetime':
                case 'timestamp':
                    $casts[] = "'{$fieldName}' => 'datetime'";
                    break;
                case 'decimal':
                case 'float':
                    $casts[] = "'{$fieldName}' => 'float'";
                    break;
                case 'integer':
                    $casts[] = "'{$fieldName}' => 'integer'";
                    break;
            }
            
            // Handle file fields
            if (isset($fieldType['is_file']) && $fieldType['is_file']) {
                if ($fieldType['multiple']) {
                    $casts[] = "'{$fieldName}' => 'array'";
                }
            }
        }
        
        // Add password hiding
        foreach ($fields as $fieldName => $fieldDefinition) {
            if ($fieldName === 'password' || strpos($fieldName, 'password') !== false) {
                $casts[] = "'{$fieldName}' => 'hashed'";
            }
        }
        
        if (empty($casts)) {
            return '';
        }
        
        $castsString = implode(",\n        ", $casts);
        return "protected \$casts = [\n        {$castsString}\n    ];";
    }

    protected function generateRelations(array $relations): string
    {
        $methods = [];
        
        foreach ($relations as $relationName => $relationDefinition) {
            $method = $this->generateRelationMethod($relationName, $relationDefinition);
            if ($method) {
                $methods[] = $method;
            }
        }
        
        return implode("\n\n    ", $methods);
    }

    protected function generateRelationMethod(string $relationName, string $relationDefinition): string
    {
        $parts = explode(':', $relationDefinition);
        $relationType = $parts[0];
        $relatedModel = $parts[1] ?? '';
        
        if (strpos($relatedModel, ',') !== false) {
            $relationParts = explode(',', $relatedModel);
            $relatedModel = $relationParts[0];
        }
        
        $methodName = Str::camel($relationName);
        $returnType = Str::studly($relatedModel);
        
        switch ($relationType) {
            case 'belongsTo':
                $foreignKey = isset($relationParts[1]) ? ", '{$relationParts[1]}'" : '';
                return "public function {$methodName}(): BelongsTo\n    {\n        return \$this->belongsTo({$returnType}::class{$foreignKey});\n    }";
                
            case 'hasOne':
                $foreignKey = isset($relationParts[1]) ? ", '{$relationParts[1]}'" : '';
                return "public function {$methodName}(): HasOne\n    {\n        return \$this->hasOne({$returnType}::class{$foreignKey});\n    }";
                
            case 'hasMany':
                $foreignKey = isset($relationParts[1]) ? ", '{$relationParts[1]}'" : '';
                return "public function {$methodName}(): HasMany\n    {\n        return \$this->hasMany({$returnType}::class{$foreignKey});\n    }";
                
            case 'belongsToMany':
                $pivotTable = $relationParts[1] ?? '';
                $foreignPivotKey = $relationParts[2] ?? '';
                $relatedPivotKey = $relationParts[3] ?? '';
                
                $pivotParams = '';
                if ($pivotTable) {
                    $pivotParams = ", '{$pivotTable}'";
                    if ($foreignPivotKey) {
                        $pivotParams .= ", '{$foreignPivotKey}'";
                        if ($relatedPivotKey) {
                            $pivotParams .= ", '{$relatedPivotKey}'";
                        }
                    }
                }
                
                return "public function {$methodName}(): BelongsToMany\n    {\n        return \$this->belongsToMany({$returnType}::class{$pivotParams});\n    }";
                
            case 'morphTo':
                return "public function {$methodName}(): MorphTo\n    {\n        return \$this->morphTo();\n    }";
                
            case 'morphMany':
                $morphName = $relationParts[1] ?? Str::snake($relationName);
                return "public function {$methodName}(): MorphMany\n    {\n        return \$this->morphMany({$returnType}::class, '{$morphName}');\n    }";
                
            case 'morphedByMany':
                $morphName = $relationParts[1] ?? 'taggable';
                return "public function {$methodName}(): MorphToMany\n    {\n        return \$this->morphedByMany({$returnType}::class, '{$morphName}');\n    }";
        }
        
        return '';
    }

    protected function generateScopes(array $scopes): string
    {
        $methods = [];
        
        foreach ($scopes as $scopeName => $scopeDefinition) {
            $methodName = 'scope' . Str::studly($scopeName);
            
            if (is_string($scopeDefinition)) {
                // Simple scope like "where:status,active"
                if (strpos($scopeDefinition, 'where:') === 0) {
                    $parts = explode(':', $scopeDefinition, 2);
                    $conditions = explode(',', $parts[1]);
                    $field = $conditions[0];
                    $value = $conditions[1] ?? 'true';
                    
                    $methods[] = "public function {$methodName}(\$query)\n    {\n        return \$query->where('{$field}', '{$value}');\n    }";
                } elseif (strpos($scopeDefinition, 'orderBy:') === 0) {
                    $parts = explode(':', $scopeDefinition, 2);
                    $conditions = explode(',', $parts[1]);
                    $field = $conditions[0];
                    $direction = $conditions[1] ?? 'asc';
                    
                    $methods[] = "public function {$methodName}(\$query)\n    {\n        return \$query->orderBy('{$field}', '{$direction}');\n    }";
                } elseif (strpos($scopeDefinition, 'whereNull:') === 0) {
                    $field = explode(':', $scopeDefinition)[1];
                    $methods[] = "public function {$methodName}(\$query)\n    {\n        return \$query->whereNull('{$field}');\n    }";
                }
            }
        }
        
        return implode("\n\n    ", $methods);
    }

    protected function generateImports(array $relations, array $fields): string
    {
        $imports = [
            'use Illuminate\Database\Eloquent\Model;',
            'use Illuminate\Database\Eloquent\Factories\HasFactory;'
        ];
        
        // Add relation imports based on used relationships
        $relationTypes = [];
        foreach ($relations as $relationDefinition) {
            $type = explode(':', $relationDefinition)[0];
            $relationTypes[] = $type;
        }
        
        $relationTypes = array_unique($relationTypes);
        
        foreach ($relationTypes as $type) {
            switch ($type) {
                case 'belongsTo':
                    $imports[] = 'use Illuminate\Database\Eloquent\Relations\BelongsTo;';
                    break;
                case 'hasOne':
                    $imports[] = 'use Illuminate\Database\Eloquent\Relations\HasOne;';
                    break;
                case 'hasMany':
                    $imports[] = 'use Illuminate\Database\Eloquent\Relations\HasMany;';
                    break;
                case 'belongsToMany':
                    $imports[] = 'use Illuminate\Database\Eloquent\Relations\BelongsToMany;';
                    break;
                case 'morphTo':
                    $imports[] = 'use Illuminate\Database\Eloquent\Relations\MorphTo;';
                    break;
                case 'morphMany':
                    $imports[] = 'use Illuminate\Database\Eloquent\Relations\MorphMany;';
                    break;
                case 'morphedByMany':
                    $imports[] = 'use Illuminate\Database\Eloquent\Relations\MorphToMany;';
                    break;
            }
        }
        
        // Check if we have soft deletes
        foreach ($fields as $fieldName => $fieldDefinition) {
            if ($fieldName === 'deleted_at') {
                $imports[] = 'use Illuminate\Database\Eloquent\SoftDeletes;';
                break;
            }
        }
        
        return implode("\n", array_unique($imports));
    }

    protected function generateTraits(array $fields, array $cacheConfig): string
    {
        $traits = ['HasFactory'];
        
        // Add SoftDeletes if deleted_at field exists
        foreach ($fields as $fieldName => $fieldDefinition) {
            if ($fieldName === 'deleted_at') {
                $traits[] = 'SoftDeletes';
                break;
            }
        }
        
        // Add cacheable trait if cache is configured
        if (!empty($cacheConfig)) {
            $traits[] = 'Cacheable';
        }
        
        if (empty($traits)) {
            return '';
        }
        
        $traitsString = implode(', ', $traits);
        return "use {$traitsString};";
    }

    protected function getStub(string $type): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

{{ imports }}

class {{ class }} extends Model
{
    {{ traits }}

    protected $table = '{{ table }}';

    // >>> AI-NATIVE FILLABLE START
    {{ fillable }}
    // >>> AI-NATIVE FILLABLE END

    // >>> AI-NATIVE CASTS START
    {{ casts }}
    // >>> AI-NATIVE CASTS END

    // >>> AI-NATIVE RELATIONS START
    {{ relations }}
    // >>> AI-NATIVE RELATIONS END

    // >>> AI-NATIVE SCOPES START
    {{ scopes }}
    // >>> AI-NATIVE SCOPES END
}
STUB;
    }
}