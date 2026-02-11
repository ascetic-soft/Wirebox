<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Scanner;

/**
 * Recursively scans a directory for PHP files and extracts fully qualified class names
 * using PHP's tokenizer. Filters out abstract classes, interfaces, traits, and enums.
 */
final class ClassScanner
{
    /**
     * Scan directory and return list of concrete class FQCNs.
     *
     * @param list<string> $excludePatterns Glob-like patterns to exclude
     * @return list<class-string>
     */
    public function scan(string $directory, array $excludePatterns = []): array
    {
        $realDir = realpath($directory);
        if ($realDir === false || !is_dir($realDir)) {
            throw new \InvalidArgumentException("Directory \"{$directory}\" does not exist.");
        }

        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realDir, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            if ($this->isExcluded($realPath, $realDir, $excludePatterns)) {
                continue;
            }

            $discovered = $this->extractClasses($realPath);
            foreach ($discovered as $fqcn) {
                $classes[] = $fqcn;
            }
        }

        sort($classes);
        return $classes;
    }

    /**
     * Extract concrete class FQCNs from a PHP file using the tokenizer.
     *
     * @return list<class-string>
     */
    private function extractClasses(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $tokens = token_get_all($content);
        $count = count($tokens);
        $classes = [];
        $namespace = '';
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            // Namespace declaration
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = $this->parseNamespace($tokens, $i, $count);
                continue;
            }

            // Skip abstract, interface, trait, enum — we only want concrete classes
            if (is_array($token) && $token[0] === T_ABSTRACT) {
                // Skip the abstract class entirely
                $this->skipToNextSemicolonOrBrace($tokens, $i, $count);
                continue;
            }

            if (is_array($token) && in_array($token[0], [T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $this->skipToNextSemicolonOrBrace($tokens, $i, $count);
                continue;
            }

            // Class declaration
            if (is_array($token) && $token[0] === T_CLASS) {
                // Make sure it's not an anonymous class: check previous meaningful token
                // Also make sure it's not ::class
                $prevMeaningful = $this->previousMeaningfulToken($tokens, $i);
                if ($prevMeaningful !== null && is_array($prevMeaningful) && $prevMeaningful[0] === T_DOUBLE_COLON) {
                    $i++;
                    continue;
                }
                if ($prevMeaningful !== null && is_array($prevMeaningful) && $prevMeaningful[0] === T_NEW) {
                    // anonymous class: new class { ... }
                    $i++;
                    continue;
                }

                $className = $this->parseClassName($tokens, $i, $count);
                if ($className !== null) {
                    $fqcn = $namespace !== '' ? $namespace . '\\' . $className : $className;
                    /** @var class-string $fqcn */
                    $classes[] = $fqcn;
                }
                continue;
            }

            $i++;
        }

        return $classes;
    }

    /**
     * Parse namespace from tokens, advance $i past the namespace declaration.
     */
    private function parseNamespace(array $tokens, int &$i, int $count): string
    {
        $i++; // skip T_NAMESPACE
        $namespace = '';

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                $namespace .= $token[1];
            } elseif ($token === ';' || $token === '{') {
                $i++;
                break;
            }

            $i++;
        }

        return $namespace;
    }

    /**
     * Parse class name after T_CLASS token.
     */
    private function parseClassName(array $tokens, int &$i, int $count): ?string
    {
        $i++; // skip T_CLASS

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_STRING) {
                $name = $token[1];
                $i++;
                return $name;
            }

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                $i++;
                continue;
            }

            // Not a named class (anonymous)
            break;
        }

        return null;
    }

    /**
     * Skip tokens until we find ; or { (to skip interface/trait/abstract class declarations).
     */
    private function skipToNextSemicolonOrBrace(array $tokens, int &$i, int $count): void
    {
        while ($i < $count) {
            $token = $tokens[$i];
            if ($token === ';' || $token === '{') {
                $i++;
                return;
            }
            $i++;
        }
    }

    /**
     * Find the previous meaningful (non-whitespace, non-comment) token.
     */
    private function previousMeaningfulToken(array $tokens, int $index): mixed
    {
        for ($j = $index - 1; $j >= 0; $j--) {
            $t = $tokens[$j];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return $t;
        }
        return null;
    }

    /**
     * Check if a file path matches any of the exclude patterns.
     */
    private function isExcluded(string $filePath, string $baseDir, array $excludePatterns): bool
    {
        if ($excludePatterns === []) {
            return false;
        }

        // Normalize to relative path with forward slashes
        $relative = str_replace('\\', '/', substr($filePath, strlen($baseDir)));
        $relative = ltrim($relative, '/');

        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $relative, FNM_PATHNAME | FNM_CASEFOLD)) {
                return true;
            }
            // Also try matching just the filename
            if (fnmatch($pattern, basename($filePath), FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }
}
