<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Env;

/**
 * Minimal .env file parser (no external dependencies).
 *
 * Supports:
 *   KEY=value
 *   KEY="quoted value"
 *   KEY='single quoted value'
 *   # comments
 *   empty lines
 *   EXPORT KEY=value (export prefix)
 *   Variable interpolation: KEY2=${KEY1}/suffix
 */
final class DotEnvParser
{
    /**
     * Parse a .env file and return key-value pairs.
     *
     * @return array<string, string>
     */
    public function parse(string $filePath): array
    {
        if (!\is_file($filePath) || !\is_readable($filePath)) {
            return [];
        }

        $content = \file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        return $this->parseString($content);
    }

    /**
     * @return array<string, string>
     */
    public function parseString(string $content): array
    {
        $vars = [];
        $lines = \explode("\n", \str_replace("\r\n", "\n", $content));

        foreach ($lines as $line) {
            $line = \trim($line);

            // Skip empty lines and comments
            if ($line === '' || \str_starts_with($line, '#')) {
                continue;
            }

            // Strip optional "export " prefix
            if (\str_starts_with($line, 'export ')) {
                $line = \substr($line, 7);
            }

            $eqPos = \strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = \trim(\substr($line, 0, $eqPos));
            $value = \trim(\substr($line, $eqPos + 1));

            if ($key === '') {
                continue;
            }

            $value = $this->parseValue($value, $vars);
            $vars[$key] = $value;
        }

        return $vars;
    }

    /**
     * @param array<string, string> $existingVars
     */
    private function parseValue(string $value, array $existingVars): string
    {
        // Double-quoted value: interpret escapes and variable interpolation
        if (\str_starts_with($value, '"') && \str_ends_with($value, '"') && \strlen($value) >= 2) {
            $value = \substr($value, 1, -1);
            $value = $this->interpolate($value, $existingVars);
            return \stripcslashes($value);
        }

        // Single-quoted value: literal, no interpolation
        if (\str_starts_with($value, "'") && \str_ends_with($value, "'") && \strlen($value) >= 2) {
            return \substr($value, 1, -1);
        }

        // Unquoted: strip inline comment, interpolate
        $commentPos = \strpos($value, ' #');
        if ($commentPos !== false) {
            $value = \trim(\substr($value, 0, $commentPos));
        }

        return $this->interpolate($value, $existingVars);
    }

    /**
     * Replace ${VAR} or $VAR references with previously defined values.
     *
     * @param array<string, string> $vars
     */
    private function interpolate(string $value, array $vars): string
    {
        // ${VAR} syntax
        $value = \preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_]*)}/', fn (array $matches): string => $this->lookupVar($matches[1], $vars), $value) ?? $value;

        // $VAR syntax (not followed by {)
        $value = \preg_replace_callback('/\$([A-Za-z_][A-Za-z0-9_]*)(?![{])/', fn (array $matches): string => $this->lookupVar($matches[1], $vars), $value) ?? $value;

        return $value;
    }

    /**
     * Look up a variable by name: local vars -> $_ENV -> getenv().
     *
     * @param array<string, string> $vars
     */
    private function lookupVar(string $name, array $vars): string
    {
        if (isset($vars[$name])) {
            return $vars[$name];
        }

        if (isset($_ENV[$name]) && \is_string($_ENV[$name])) {
            return $_ENV[$name];
        }

        $envValue = \getenv($name);
        return $envValue !== false ? $envValue : '';
    }
}
