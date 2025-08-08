<?php

namespace AiNative\Laravel\Helpers;

use Illuminate\Support\Facades\Log;

class HookDispatcher
{
    /**
     * Process a hook definition.
     * @param string $stage beforeCreate|afterCreate|beforeUpdate|afterUpdate|beforeDelete|afterDelete
     * @param mixed $model Model class name (before create) or instance (other stages)
     * @param array $data Mutable data array (for beforeCreate/beforeUpdate)
     * @param mixed $definition String shorthand or array with 'action'
     * @return array Modified data (only for before hooks)
     */
    public static function process(string $stage, $model, array $data, $definition): array
    {
        if (!$definition) { return $data; }
        // Normalize definition
        if (is_string($definition)) {
            $definition = ['action' => $definition];
        }
        $action = $definition['action'] ?? null;
        if (!$action) { return $data; }

        switch ($action) {
            case 'log':
                $message = $definition['message'] ?? ($stage.' event');
                $context = self::buildContext($model, $data);
                $message = self::interpolate($message, $context);
                Log::info('[AI-NATIVE HOOK] '.$message, ['stage'=>$stage]);
                break;
            case 'sanitizeInput':
                foreach ($data as $k => $v) {
                    if (is_string($v)) { $data[$k] = trim(strip_tags($v)); }
                }
                break;
        }

        return $data;
    }

    protected static function buildContext($model, array $data): array
    {
        $ctx = $data;
        if (is_object($model)) {
            foreach ($model->getAttributes() as $k => $v) {
                if (!array_key_exists($k, $ctx)) { $ctx[$k] = $v; }
            }
        }
        return $ctx;
    }

    protected static function interpolate(string $message, array $context): string
    {
        return preg_replace_callback('/\$(\w+)/', function($m) use ($context) {
            $key = $m[1];
            return array_key_exists($key, $context) ? (string)$context[$key] : $m[0];
        }, $message);
    }
}
