<?php

namespace AiNative\Laravel\Generators;

use AiNative\Laravel\Parsers\SchemaParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class FactoryGenerator
{
    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function generate(string $modelName, SchemaParser $parser): string
    {
        $model = $parser->getModel($modelName);
        $fields = $model['fields'] ?? [];
        // The schema may specify "factory": true/false or an object with config.
        $factory = $model['factory'] ?? [];
        if (!is_array($factory)) {
            // Normalize non-array (boolean) values to empty array configuration
            $factory = [];
        }
        
        $className = Str::studly($modelName) . 'Factory';
        $modelClass = Str::studly($modelName);
        
        $content = [];
        $content[] = "<?php";
        $content[] = "";
        $content[] = "namespace Database\\Factories;";
        $content[] = "";
        $content[] = "use App\\Models\\{$modelClass};";
        $content[] = "use Illuminate\\Database\\Eloquent\\Factories\\Factory;";
        $content[] = "use Illuminate\\Support\\Str;";
        $content[] = "";
        $content[] = "/**";
        $content[] = " * @extends \\Illuminate\\Database\\Eloquent\\Factories\\Factory<\\App\\Models\\{$modelClass}>";
        $content[] = " */";
        $content[] = "class {$className} extends Factory";
        $content[] = "{";
        $content[] = "    /**";
        $content[] = "     * The name of the factory's corresponding model.";
        $content[] = "     */";
        $content[] = "    protected \$model = {$modelClass}::class;";
        $content[] = "";
        $content[] = "    /**";
        $content[] = "     * Define the model's default state.";
        $content[] = "     *";
        $content[] = "     * @return array<string, mixed>";
        $content[] = "     */";
    $content[] = "    // >>> AI-NATIVE FACTORY DEFINITION START";
    $content[] = "    public function definition(): array";
    $content[] = "    {";
    $content[] = "        return [";
        
        foreach ($fields as $field => $definition) {
            // Skip auto-generated fields
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            $fakeValue = $this->generateFakeValue($field, $definition);
            $content[] = "            '{$field}' => {$fakeValue},";
        }
        
    $content[] = "        ];";
    $content[] = "    }";
    $content[] = "    // >>> AI-NATIVE FACTORY DEFINITION END";
        
        // Add factory states if defined
        $this->addFactoryStates($content, $factory, $fields);
        
    $content[] = "}";
        
        return implode("\n", $content);
    }

    protected function generateFakeValue(string $fieldName, string $definition): string
    {
        $type = $this->extractType($definition);
        
        // Handle foreign keys
        if (str_contains($definition, 'foreign:')) {
            preg_match('/foreign:([^|]+)/', $definition, $matches);
            $relatedTable = $matches[1] ?? 'users';
            $relatedModel = Str::studly(Str::singular($relatedTable));
            
            // Check for nullable foreign keys
            if (str_contains($definition, 'nullable')) {
                return "fake()->optional()->randomElement([null, \\App\\Models\\{$relatedModel}::factory()])";
            }
            
            return "\\App\\Models\\{$relatedModel}::factory()";
        }
        
        // Handle specific field names
        switch (strtolower($fieldName)) {
            case 'email':
                return "fake()->unique()->safeEmail()";
            case 'name':
                return "fake()->name()";
            case 'title':
                return "fake()->sentence(3)";
            case 'description':
                return "fake()->paragraph()";
            case 'content':
                return "fake()->paragraphs(3, true)";
            case 'slug':
                return "fake()->slug()";
            case 'password':
                return "bcrypt('password')";
            case 'phone':
                return "fake()->phoneNumber()";
            case 'address':
                return "fake()->address()";
            case 'bio':
                return "fake()->paragraph()";
            case 'url':
            case 'website':
                return "fake()->url()";
        }
        
        // Handle by type
        switch ($type) {
            case 'string':
                if (str_contains($definition, 'email')) {
                    return "fake()->unique()->safeEmail()";
                }
                $maxLength = $this->extractMaxLength($definition);
                if ($maxLength && $maxLength <= 50) {
                    return "fake()->word()";
                } elseif ($maxLength && $maxLength <= 255) {
                    return "fake()->sentence()";
                }
                return "fake()->text()";
                
            case 'text':
            case 'longText':
                return "fake()->paragraph()";
                
            case 'integer':
            case 'bigInteger':
            case 'unsignedInteger':
            case 'unsignedBigInteger':
                return "fake()->numberBetween(1, 1000)";
                
            case 'decimal':
            case 'float':
            case 'double':
                return "fake()->randomFloat(2, 0, 999.99)";
                
            case 'boolean':
                return "fake()->boolean()";
                
            case 'date':
                return "fake()->date()";
                
            case 'datetime':
            case 'timestamp':
                return "fake()->dateTime()";
                
            case 'json':
                return "fake()->randomElements(['key1' => 'value1', 'key2' => 'value2'])";
                
            case 'enum':
                $values = $this->extractEnumValues($definition);
                if (!empty($values)) {
                    $valuesString = "'" . implode("', '", $values) . "'";
                    return "fake()->randomElement([{$valuesString}])";
                }
                return "fake()->word()";
                
            case 'uuid':
                return "fake()->uuid()";
                
            case 'file':
            case 'image':
                return "null"; // Files need special handling in tests
                
            default:
                return "fake()->word()";
        }
    }

    protected function extractType(string $definition): string
    {
        $parts = explode('|', $definition);
        $type = $parts[0];
        
        if (str_contains($type, ':')) {
            return explode(':', $type)[0];
        }
        
        return $type;
    }

    protected function extractMaxLength(string $definition): ?int
    {
        if (preg_match('/max:(\d+)/', $definition, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    protected function extractEnumValues(string $definition): array
    {
        if (preg_match('/enum:([^|]+)/', $definition, $matches)) {
            return explode(',', $matches[1]);
        }
        return [];
    }

    protected function addFactoryStates(array &$content, array $factory, array $fields): void
    {
        // Defensive: ensure factory config is array (schema could have boolean)
        if (!is_array($factory)) {
            return; // nothing to do
        }
        // Add common states based on field patterns
        if (isset($fields['is_active']) || isset($fields['active'])) {
            $content[] = "";
            $content[] = "    /**";
            $content[] = "     * Indicate that the model is inactive.";
            $content[] = "     */";
            $content[] = "    public function inactive(): static";
            $content[] = "    {";
            $activeField = isset($fields['is_active']) ? 'is_active' : 'active';
            $content[] = "        return \$this->state([";
            $content[] = "            '{$activeField}' => false,";
            $content[] = "        ]);";
            $content[] = "    }";
        }
        
        if (isset($fields['published']) || isset($fields['is_published'])) {
            $content[] = "";
            $content[] = "    /**";
            $content[] = "     * Indicate that the model is unpublished.";
            $content[] = "     */";
            $content[] = "    public function unpublished(): static";
            $content[] = "    {";
            $publishedField = isset($fields['published']) ? 'published' : 'is_published';
            $content[] = "        return \$this->state([";
            $content[] = "            '{$publishedField}' => false,";
            $content[] = "        ]);";
            $content[] = "    }";
        }
        
        // Add states based on enum fields
        foreach ($fields as $field => $definition) {
            if (str_contains($definition, 'enum:')) {
                $values = $this->extractEnumValues($definition);
                foreach ($values as $value) {
                    $methodName = Str::camel($value);
                    $content[] = "";
                    $content[] = "    /**";
                    $content[] = "     * Indicate that the {$field} is {$value}.";
                    $content[] = "     */";
                    $content[] = "    public function {$methodName}(): static";
                    $content[] = "    {";
                    $content[] = "        return \$this->state([";
                    $content[] = "            '{$field}' => '{$value}',";
                    $content[] = "        ]);";
                    $content[] = "    }";
                }
            }
        }
    }
}