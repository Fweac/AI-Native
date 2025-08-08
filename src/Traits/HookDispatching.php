<?php

namespace AiNative\Laravel\Traits;

use AiNative\Laravel\Helpers\HookDispatcher;

trait HookDispatching
{
    /**
     * Dispatch a configured hook stage.
     */
    protected function dispatchHook(string $stage, $model = null, array $data = [])
    {
        if (!property_exists($this, 'hooks') || empty($this->hooks)) { return $data; }
        $definition = $this->hooks[$stage] ?? null;
        if (!$definition) { return $data; }
        return HookDispatcher::process($stage, $model, $data, $definition);
    }
}
