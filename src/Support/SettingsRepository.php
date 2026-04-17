<?php

namespace Stokoe\AiGateway\Support;

use Symfony\Component\Yaml\Yaml;

class SettingsRepository
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('statamic/addons/ai-gateway/settings.yaml');
    }

    /**
     * Read raw settings from YAML file. Returns empty array if file missing.
     */
    public function read(): array
    {
        if (! file_exists($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $parsed = Yaml::parse($contents);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Write settings array to YAML file. Creates directories if needed.
     */
    public function write(array $settings): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->path, Yaml::dump($settings, 10, 2));
    }

    /**
     * Resolve effective config: YAML values merged over config defaults.
     * YAML values win, config values fill gaps.
     */
    public function resolve(): array
    {
        return array_replace_recursive(config('ai_gateway'), $this->read());
    }

    /**
     * Apply YAML overrides into Laravel's config repository.
     */
    public function applyToConfig(): void
    {
        config()->set('ai_gateway', $this->resolve());
    }

    /**
     * Get the file path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Mask a token, revealing only the last 4 characters.
     * For tokens shorter than 4 chars (or null), mask everything.
     */
    public static function maskToken(?string $token): string
    {
        if ($token === null || strlen($token) === 0) {
            return '';
        }

        $len = strlen($token);

        if ($len <= 4) {
            return str_repeat('•', $len);
        }

        return str_repeat('•', $len - 4) . substr($token, -4);
    }

    /**
     * Generate a cryptographically random 64-character hex token.
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
