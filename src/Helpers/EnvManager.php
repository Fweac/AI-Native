<?php

namespace AiNative\Laravel\Helpers;

class EnvManager
{
    protected string $envPath;

    public function __construct(string $envPath = null)
    {
        $this->envPath = $envPath ?? base_path('.env');
    }

    public function updateEnvValues(array $values): void
    {
        if (!file_exists($this->envPath)) {
            // Create .env from .env.example if it doesn't exist
            if (file_exists(base_path('.env.example'))) {
                copy(base_path('.env.example'), $this->envPath);
            } else {
                touch($this->envPath);
            }
        }

        $envContent = file_get_contents($this->envPath);

        foreach ($values as $key => $value) {
            $envContent = $this->setEnvValue($envContent, $key, $value);
        }

        file_put_contents($this->envPath, $envContent);
    }

    protected function setEnvValue(string $envContent, string $key, mixed $value): string
    {
        // Convert value to string and escape if needed
        $value = $this->formatEnvValue($value);
        
        $pattern = "/^{$key}=.*$/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $envContent)) {
            // Update existing key
            return preg_replace($pattern, $replacement, $envContent);
        } else {
            // Add new key at the end
            return rtrim($envContent) . "\n{$replacement}\n";
        }
    }

    protected function formatEnvValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        // String values - quote if contains spaces or special characters
        $stringValue = (string) $value;
        if (preg_match('/\s|["\'#=]/', $stringValue)) {
            return '"' . str_replace('"', '\"', $stringValue) . '"';
        }

        return $stringValue;
    }

    public function getEnvValue(string $key): ?string
    {
        if (!file_exists($this->envPath)) {
            return null;
        }

        $envContent = file_get_contents($this->envPath);
        $pattern = "/^{$key}=(.*)$/m";

        if (preg_match($pattern, $envContent, $matches)) {
            return trim($matches[1], '"\'');
        }

        return null;
    }

    public function setDatabaseConfig(array $dbConfig): void
    {
        $envValues = [];

        // Set database connection
        if (isset($dbConfig['connection'])) {
            $envValues['DB_CONNECTION'] = $dbConfig['connection'];
        }

        // Set database host
        if (isset($dbConfig['host'])) {
            $envValues['DB_HOST'] = $dbConfig['host'];
        }

        // Set database port
        if (isset($dbConfig['port'])) {
            $envValues['DB_PORT'] = $dbConfig['port'];
        }

        // Set database name
        if (isset($dbConfig['database'])) {
            $envValues['DB_DATABASE'] = $dbConfig['database'];
        }

        // Set database username
        if (isset($dbConfig['username'])) {
            $envValues['DB_USERNAME'] = $dbConfig['username'];
        }

        // Set database password
        if (isset($dbConfig['password'])) {
            $envValues['DB_PASSWORD'] = $dbConfig['password'];
        }

        if (isset($dbConfig['charset'])) {
            $envValues['DB_CHARSET'] = $dbConfig['charset'];
        }

        if (isset($dbConfig['collation'])) {
            $envValues['DB_COLLATION'] = $dbConfig['collation'];
        }

        if (!empty($envValues)) {
            $this->updateEnvValues($envValues);
        }
    }

    public function setCacheConfig(array $cacheConfig): void
    {
        $envValues = [];

        if (isset($cacheConfig['driver'])) {
            $envValues['CACHE_DRIVER'] = $cacheConfig['driver'];
        }

        if (isset($cacheConfig['default_ttl'])) {
            $envValues['CACHE_DEFAULT_TTL'] = $cacheConfig['default_ttl'];
        }

        if (!empty($envValues)) {
            $this->updateEnvValues($envValues);
        }
    }

    public function setQueueConfig(array $queueConfig): void
    {
        $envValues = [];

        if (isset($queueConfig['default'])) {
            $envValues['QUEUE_CONNECTION'] = $queueConfig['default'];
        }

        if (isset($queueConfig['retry_after'])) {
            $envValues['QUEUE_RETRY_AFTER'] = $queueConfig['retry_after'];
        }

        if (!empty($envValues)) {
            $this->updateEnvValues($envValues);
        }
    }

    public function setAuthConfig(array $authConfig): void
    {
        $envValues = [];

        if (isset($authConfig['provider']) && $authConfig['provider'] === 'sanctum') {
            // Ensure Sanctum is properly configured
            $envValues['SANCTUM_STATEFUL_DOMAINS'] = 'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1';
        }

        if (!empty($envValues)) {
            $this->updateEnvValues($envValues);
        }
    }

    public function setAppConfig(array $appConfig): void
    {
        $envValues = [];

        if (isset($appConfig['name'])) {
            $envValues['APP_NAME'] = $appConfig['name'];
        }

        if (isset($appConfig['url'])) {
            $envValues['APP_URL'] = $appConfig['url'];
        }

        if (isset($appConfig['env'])) {
            $envValues['APP_ENV'] = $appConfig['env'];
        }

        if (isset($appConfig['debug'])) {
            $envValues['APP_DEBUG'] = $appConfig['debug'] ? 'true' : 'false';
        }

        if (isset($appConfig['timezone'])) {
            $envValues['APP_TIMEZONE'] = $appConfig['timezone'];
        }

        if (!empty($envValues)) {
            $this->updateEnvValues($envValues);
        }
    }

    public function setMailConfig(array $mailConfig): void
    {
        $envValues = [];

        if (isset($mailConfig['mailer'])) {
            $envValues['MAIL_MAILER'] = $mailConfig['mailer'];
        }

        if (isset($mailConfig['host'])) {
            $envValues['MAIL_HOST'] = $mailConfig['host'];
        }

        if (isset($mailConfig['port'])) {
            $envValues['MAIL_PORT'] = $mailConfig['port'];
        }

        if (isset($mailConfig['username'])) {
            $envValues['MAIL_USERNAME'] = $mailConfig['username'];
        }

        if (isset($mailConfig['password'])) {
            $envValues['MAIL_PASSWORD'] = $mailConfig['password'];
        }

        if (isset($mailConfig['encryption'])) {
            $envValues['MAIL_ENCRYPTION'] = $mailConfig['encryption'];
        }

        if (isset($mailConfig['from_address'])) {
            $envValues['MAIL_FROM_ADDRESS'] = $mailConfig['from_address'];
        }

        if (isset($mailConfig['from_name'])) {
            $envValues['MAIL_FROM_NAME'] = $mailConfig['from_name'];
        }

        if (!empty($envValues)) {
            $this->updateEnvValues($envValues);
        }
    }
}