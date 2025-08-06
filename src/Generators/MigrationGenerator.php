<?php

namespace AiNative\Laravel\Generators;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Support\Str;

class MigrationGenerator extends BaseGenerator
{
    public function generate(string $modelName, SchemaParser $parser): string
    {
        $model = $parser->getModel($modelName);
        $fields = $parser->getModelFields($modelName);
        $tableName = $parser->getModelTable($modelName);
        
        $className = 'Create' . Str::studly($tableName) . 'Table';
        $fieldsCode = $this->generateFields($fields, $parser);
        $indexesCode = $this->generateIndexes($fields, $parser);
        $foreignKeysCode = $this->generateForeignKeys($fields, $parser);
        
        $stub = $this->getStub('migration');
        
        return str_replace([
            '{{ class }}',
            '{{ table }}',
            '{{ fields }}',
            '{{ indexes }}',
            '{{ foreign_keys }}'
        ], [
            $className,
            $tableName,
            $fieldsCode,
            $indexesCode,
            $foreignKeysCode
        ], $stub);
    }

    public function generatePivotMigration(string $pivotName, array $pivotConfig): string
    {
        $className = 'Create' . Str::studly($pivotName) . 'Table';
        $fieldsCode = $this->generatePivotFields($pivotConfig);
        $indexesCode = $this->generatePivotIndexes($pivotConfig);
        $foreignKeysCode = $this->generatePivotForeignKeys($pivotConfig);
        
        $stub = $this->getStub('migration');
        
        return str_replace([
            '{{ class }}',
            '{{ table }}',
            '{{ fields }}',
            '{{ indexes }}',
            '{{ foreign_keys }}'
        ], [
            $className,
            $pivotName,
            $fieldsCode,
            $indexesCode,
            $foreignKeysCode
        ], $stub);
    }

    protected function generateFields(array $fields, SchemaParser $parser): string
    {
        $lines = [];
        $lines[] = "\$table->id();";
        
        foreach ($fields as $fieldName => $fieldDefinition) {
            // Skip auto-generated fields
            if (in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            $fieldType = $parser->parseFieldType($fieldDefinition);
            $line = $this->generateFieldLine($fieldName, $fieldType);
            
            if ($line) {
                $lines[] = $line;
            }
        }
        
        $lines[] = "\$table->timestamps();";
        
        // Add deleted_at if it exists
        if (isset($fields['deleted_at'])) {
            $lines[] = "\$table->softDeletes();";
        }
        
        return $this->indentLines($lines);
    }

    protected function generateFieldLine(string $fieldName, array $fieldType): string
    {
        $line = '';
        $method = $fieldType['migration_method'];
        $validations = $fieldType['validations'] ?? [];
        
        switch ($method) {
            case 'string':
                $maxLength = $this->extractValidationValue($validations, 'max');
                if ($maxLength) {
                    $line = "\$table->string('{$fieldName}', {$maxLength})";
                } else {
                    $line = "\$table->string('{$fieldName}')";
                }
                break;
                
            case 'enum':
                $values = $fieldType['values'] ?? [];
                $valuesString = "'" . implode("', '", $values) . "'";
                $line = "\$table->enum('{$fieldName}', [{$valuesString}])";
                break;
                
            case 'decimal':
                $precision = $fieldType['precision'] ?? 8;
                $scale = $fieldType['scale'] ?? 2;
                $line = "\$table->decimal('{$fieldName}', {$precision}, {$scale})";
                break;
                
            case 'foreignId':
                $foreignTable = $fieldType['foreign'];
                if (Str::endsWith($fieldName, '_id')) {
                    $relationName = Str::beforeLast($fieldName, '_id');
                    $line = "\$table->foreignId('{$fieldName}')->constrained('{$foreignTable}')";
                } else {
                    $line = "\$table->unsignedBigInteger('{$fieldName}')";
                }
                break;
                
            default:
                $line = "\$table->{$method}('{$fieldName}')";
                break;
        }
        
        // Add nullable if specified
        if (in_array('nullable', $validations)) {
            $line .= "->nullable()";
        }
        
        // Add default value if specified
        $defaultValue = $this->extractValidationValue($validations, 'default');
        if ($defaultValue !== null) {
            if ($defaultValue === 'true' || $defaultValue === 'false') {
                $line .= "->default({$defaultValue})";
            } elseif ($defaultValue === 'now') {
                $line .= "->useCurrent()";
            } elseif (is_numeric($defaultValue)) {
                $line .= "->default({$defaultValue})";
            } else {
                $line .= "->default('{$defaultValue}')";
            }
        }
        
        // Add unique constraint if specified
        if (in_array('unique', $validations)) {
            $line .= "->unique()";
        }
        
        return $line . ';';
    }

    protected function generateIndexes(array $fields, SchemaParser $parser): string
    {
        $lines = [];
        
        foreach ($fields as $fieldName => $fieldDefinition) {
            $validations = $parser->parseFieldType($fieldDefinition)['validations'] ?? [];
            
            // Add index for searchable fields
            if (strpos($fieldDefinition, 'index') !== false) {
                $lines[] = "\$table->index('{$fieldName}');";
            }
        }
        
        return empty($lines) ? '' : $this->indentLines($lines);
    }

    protected function generateForeignKeys(array $fields, SchemaParser $parser): string
    {
        $lines = [];
        
        foreach ($fields as $fieldName => $fieldDefinition) {
            $fieldType = $parser->parseFieldType($fieldDefinition);
            
            if (isset($fieldType['foreign']) && $fieldType['migration_method'] !== 'foreignId') {
                $foreignTable = $fieldType['foreign'];
                $lines[] = "\$table->foreign('{$fieldName}')->references('id')->on('{$foreignTable}');";
            }
        }
        
        return empty($lines) ? '' : $this->indentLines($lines);
    }

    protected function generatePivotFields(array $pivotConfig): string
    {
        $lines = [];
        $fields = $pivotConfig['fields'] ?? [];
        
        foreach ($fields as $fieldName => $fieldDefinition) {
            if (strpos($fieldDefinition, 'foreign:') === 0) {
                $foreignTable = explode(':', $fieldDefinition)[1];
                $foreignTable = explode('|', $foreignTable)[0];
                
                $lines[] = "\$table->foreignId('{$fieldName}')->constrained('{$foreignTable}');";
            } else {
                // Handle other field types
                $parts = explode('|', $fieldDefinition);
                $type = $parts[0];
                $validations = array_slice($parts, 1);
                
                switch (true) {
                    case str_starts_with($type, 'enum:'):
                        $enumParts = explode(':', $type, 2);
                        if (count($enumParts) > 1) {
                            $values = explode(',', $enumParts[1]);
                            $valuesString = "'" . implode("', '", $values) . "'";
                            $line = "\$table->enum('{$fieldName}', [{$valuesString}])";
                        } else {
                            $line = "\$table->string('{$fieldName}')";
                        }
                        break;
                    case $type === 'enum':
                        $line = "\$table->string('{$fieldName}')";
                        break;
                    case 'timestamp':
                        $line = "\$table->timestamp('{$fieldName}')";
                        break;
                    case 'json':
                        $line = "\$table->json('{$fieldName}')";
                        break;
                    default:
                        $line = "\$table->{$type}('{$fieldName}')";
                        break;
                }
                
                if (in_array('nullable', $validations)) {
                    $line .= "->nullable()";
                }
                
                if (in_array('default:now', $validations)) {
                    $line .= "->useCurrent()";
                }
                
                // Handle default values for enums
                foreach ($validations as $validation) {
                    if (str_starts_with($validation, 'default:')) {
                        $defaultValue = substr($validation, 8);
                        $line .= "->default('{$defaultValue}')";
                        break;
                    }
                }
                
                $lines[] = $line . ';';
            }
        }
        
        return $this->indentLines($lines);
    }

    protected function generatePivotIndexes(array $pivotConfig): string
    {
        $lines = [];
        
        // Add unique constraint if specified
        if (isset($pivotConfig['unique'])) {
            $uniqueFields = $pivotConfig['unique'];
            $fieldsString = "'" . implode("', '", $uniqueFields) . "'";
            $lines[] = "\$table->unique([{$fieldsString}]);";
        }
        
        return empty($lines) ? '' : $this->indentLines($lines);
    }

    protected function generatePivotForeignKeys(array $pivotConfig): string
    {
        // Foreign keys are handled in generatePivotFields for pivot tables
        return '';
    }

    protected function extractValidationValue(array $validations, string $rule): ?string
    {
        foreach ($validations as $validation) {
            if (strpos($validation, $rule . ':') === 0) {
                return explode(':', $validation, 2)[1];
            }
        }
        
        if (in_array($rule, $validations)) {
            return $rule;
        }
        
        return null;
    }

    protected function indentLines(array $lines): string
    {
        return implode("\n            ", array_map(function($line) {
            return $line;
        }, $lines));
    }

    protected function getStub(string $type): string
    {
        return <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{{ table }}', function (Blueprint $table) {
            {{ fields }}
            {{ indexes }}
            {{ foreign_keys }}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{{ table }}');
    }
};
STUB;
    }

    public function generateModifyUsersTable(string $modelName, SchemaParser $parser): string
    {
        $model = $parser->getModel($modelName);
        $fields = $model['fields'] ?? [];
        $className = 'Modify' . Str::studly($parser->getModelTable($modelName)) . 'Table';
        
        // Laravel's default users table fields
        $defaultFields = ['id', 'name', 'email', 'email_verified_at', 'password', 'remember_token', 'created_at', 'updated_at'];
        
        $content = [];
        $content[] = "<?php";
        $content[] = "";
        $content[] = "use Illuminate\\Database\\Migrations\\Migration;";
        $content[] = "use Illuminate\\Database\\Schema\\Blueprint;";
        $content[] = "use Illuminate\\Support\\Facades\\Schema;";
        $content[] = "";
        $content[] = "return new class extends Migration";
        $content[] = "{";
        $content[] = "    public function up(): void";
        $content[] = "    {";
        $content[] = "        Schema::table('users', function (Blueprint \$table) {";
        
        // Add only new fields not in Laravel's default users table
        foreach ($fields as $fieldName => $fieldDefinition) {
            if (!in_array($fieldName, $defaultFields)) {
                $fieldCode = $this->generateFieldCode($fieldName, $fieldDefinition);
                $content[] = "            " . $fieldCode;
            }
        }
        
        $content[] = "        });";
        $content[] = "    }";
        $content[] = "";
        $content[] = "    public function down(): void";
        $content[] = "    {";
        $content[] = "        Schema::table('users', function (Blueprint \$table) {";
        
        // Drop only the added fields
        foreach ($fields as $fieldName => $fieldDefinition) {
            if (!in_array($fieldName, $defaultFields)) {
                $content[] = "            \$table->dropColumn('{$fieldName}');";
            }
        }
        
        $content[] = "        });";
        $content[] = "    }";
        $content[] = "};";
        
        return implode("\n", $content);
    }

    protected function generateFieldCode(string $fieldName, string $fieldDefinition): string
    {
        // Handle foreign keys
        if (str_contains($fieldDefinition, 'foreign:')) {
            $foreignTable = explode(':', $fieldDefinition)[1];
            $foreignTable = explode('|', $foreignTable)[0];
            return "\$table->foreignId('{$fieldName}')->constrained('{$foreignTable}');";
        }

        // Handle other field types
        $parts = explode('|', $fieldDefinition);
        $type = $parts[0];
        $validations = array_slice($parts, 1);

        switch (true) {
            case str_starts_with($type, 'enum:'):
                $enumParts = explode(':', $type, 2);
                if (count($enumParts) > 1) {
                    $values = explode(',', $enumParts[1]);
                    $valuesString = "'" . implode("', '", $values) . "'";
                    $line = "\$table->enum('{$fieldName}', [{$valuesString}])";
                } else {
                    $line = "\$table->string('{$fieldName}')";
                }
                break;
            case $type === 'enum':
                $line = "\$table->string('{$fieldName}')";
                break;
            case str_starts_with($type, 'file:') || str_starts_with($type, 'files:'):
                $line = "\$table->string('{$fieldName}')";
                break;
            case $type === 'timestamp':
                $line = "\$table->timestamp('{$fieldName}')";
                break;
            case $type === 'json':
                $line = "\$table->json('{$fieldName}')";
                break;
            case $type === 'text':
                $line = "\$table->text('{$fieldName}')";
                break;
            case $type === 'longText':
                $line = "\$table->longText('{$fieldName}')";
                break;
            case $type === 'boolean':
                $line = "\$table->boolean('{$fieldName}')";
                break;
            case $type === 'decimal':
                $line = "\$table->decimal('{$fieldName}')";
                break;
            case $type === 'date':
                $line = "\$table->date('{$fieldName}')";
                break;
            case $type === 'datetime':
                $line = "\$table->dateTime('{$fieldName}')";
                break;
            case $type === 'integer':
                $line = "\$table->integer('{$fieldName}')";
                break;
            default:
                $line = "\$table->{$type}('{$fieldName}')";
                break;
        }

        if (in_array('nullable', $validations)) {
            $line .= "->nullable()";
        }

        if (in_array('default:now', $validations)) {
            $line .= "->useCurrent()";
        }

        // Handle default values for enums and other types
        foreach ($validations as $validation) {
            if (str_starts_with($validation, 'default:') && $validation !== 'default:now') {
                $defaultValue = substr($validation, 8);
                if ($defaultValue === 'true' || $defaultValue === 'false') {
                    $line .= "->default({$defaultValue})";
                } else {
                    $line .= "->default('{$defaultValue}')";
                }
                break;
            }
        }

        return $line . ';';
    }
}