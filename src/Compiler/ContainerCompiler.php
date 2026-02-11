<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Compiler;

use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Lifetime;

/**
 * Compiles container definitions into a PHP class file.
 * The generated class extends CompiledContainer and has a factory method for each service.
 * No reflection is used at runtime.
 */
final class ContainerCompiler
{
    /**
     * @param array<string, Definition> $definitions
     * @param array<string, string> $bindings
     * @param array<string, mixed> $parameters
     * @param array<string, list<string>> $tags
     */
    public function compile(
        array $definitions,
        array $bindings,
        array $parameters,
        array $tags,
        string $outputPath,
        string $className = 'CompiledContainer',
        string $namespace = '',
    ): void {
        $methods = [];
        $methodMap = [];

        foreach ($definitions as $id => $definition) {
            if ($definition->getFactory() !== null) {
                // Factories cannot be compiled — skip, they need runtime closure
                continue;
            }

            $targetClass = $definition->getClassName() ?? $id;
            if (!class_exists($targetClass)) {
                continue;
            }

            $methodName = $this->generateMethodName($id);
            $methodMap[$id] = $methodName;

            $methods[] = $this->generateFactoryMethod(
                $methodName,
                $id,
                $targetClass,
                $definition,
            );
        }

        $code = $this->generateClassCode(
            $className,
            $namespace,
            $methods,
            $methodMap,
            $bindings,
            $parameters,
            $tags,
        );

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, $code);
    }

    private function generateMethodName(string $id): string
    {
        // Convert FQCN to a valid method name
        return 'get' . str_replace(['\\', '.', '-'], '_', $id);
    }

    /**
     * @param class-string $targetClass
     */
    private function generateFactoryMethod(
        string $methodName,
        string $id,
        string $targetClass,
        Definition $definition,
    ): string {
        $lines = [];
        $isSingleton = $definition->isSingleton();

        $lines[] = "    protected function {$methodName}(): \\{$targetClass}";
        $lines[] = '    {';

        if ($isSingleton) {
            $lines[] = '        if (isset($this->instances[' . var_export($id, true) . '])) {';
            $lines[] = '            return $this->instances[' . var_export($id, true) . '];';
            $lines[] = '        }';
            $lines[] = '';
        }

        // Resolve constructor parameters
        try {
            $ref = new \ReflectionClass($targetClass);
            $constructor = $ref->getConstructor();

            if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
                $lines[] = "        \$instance = new \\{$targetClass}();";
            } else {
                $args = [];
                foreach ($constructor->getParameters() as $param) {
                    $args[] = $this->generateParameterResolution($param);
                }
                $argsCode = implode(",\n            ", $args);
                $lines[] = "        \$instance = new \\{$targetClass}(";
                $lines[] = "            {$argsCode},";
                $lines[] = '        );';
            }
        } catch (\ReflectionException) {
            $lines[] = "        \$instance = new \\{$targetClass}();";
        }

        // Method calls (setter injection)
        foreach ($definition->getMethodCalls() as $call) {
            $callArgs = [];
            foreach ($call['arguments'] as $arg) {
                if (is_string($arg) && class_exists($arg)) {
                    $callArgs[] = '$this->get(' . var_export($arg, true) . ')';
                } else {
                    $callArgs[] = var_export($arg, true);
                }
            }
            $callArgsStr = implode(', ', $callArgs);
            $lines[] = "        \$instance->{$call['method']}({$callArgsStr});";
        }

        if ($isSingleton) {
            $lines[] = '';
            $lines[] = '        $this->instances[' . var_export($id, true) . '] = $instance;';
        }

        $lines[] = '';
        $lines[] = '        return $instance;';
        $lines[] = '    }';

        return implode("\n", $lines);
    }

    private function generateParameterResolution(\ReflectionParameter $param): string
    {
        $type = $param->getType();

        // Check for #[Inject] attribute
        $injectAttrs = $param->getAttributes(\AsceticSoft\Wirebox\Attribute\Inject::class);
        if ($injectAttrs !== []) {
            $inject = $injectAttrs[0]->newInstance();
            return '$this->get(' . var_export($inject->id, true) . ')';
        }

        // Check for #[Param] attribute
        $paramAttrs = $param->getAttributes(\AsceticSoft\Wirebox\Attribute\Param::class);
        if ($paramAttrs !== []) {
            $paramAttr = $paramAttrs[0]->newInstance();
            return '$this->getParameter(' . var_export($paramAttr->name, true) . ')';
        }

        // Type-hinted service
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            return '$this->get(' . var_export($type->getName(), true) . ')';
        }

        // Default value
        if ($param->isDefaultValueAvailable()) {
            return var_export($param->getDefaultValue(), true);
        }

        // Nullable
        if ($param->allowsNull()) {
            return 'null';
        }

        return "null /* WARNING: cannot resolve \${$param->getName()} */";
    }

    /**
     * @param list<string> $methods
     * @param array<string, string> $methodMap
     * @param array<string, string> $bindings
     * @param array<string, mixed> $parameters
     * @param array<string, list<string>> $tags
     */
    private function generateClassCode(
        string $className,
        string $namespace,
        array $methods,
        array $methodMap,
        array $bindings,
        array $parameters,
        array $tags,
    ): string {
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = 'declare(strict_types=1);';
        $lines[] = '';

        if ($namespace !== '') {
            $lines[] = "namespace {$namespace};";
            $lines[] = '';
        }

        $baseClass = '\\' . CompiledContainer::class;

        $lines[] = "/**";
        $lines[] = " * Auto-generated compiled container.";
        $lines[] = " * DO NOT EDIT — regenerate with ContainerBuilder::compile().";
        $lines[] = " */";
        $lines[] = "class {$className} extends {$baseClass}";
        $lines[] = '{';
        $lines[] = '    public function __construct()';
        $lines[] = '    {';
        $lines[] = '        parent::__construct();';
        $lines[] = '';
        $lines[] = '        $this->methodMap = ' . $this->exportArray($methodMap, 2) . ';';
        $lines[] = '';
        $lines[] = '        $this->bindings = ' . $this->exportArray($bindings, 2) . ';';
        $lines[] = '';
        $lines[] = '        $this->parameters = ' . $this->exportArray($parameters, 2) . ';';
        $lines[] = '';
        $lines[] = '        $this->tags = ' . $this->exportArray($tags, 2) . ';';
        $lines[] = '    }';

        foreach ($methods as $method) {
            $lines[] = '';
            $lines[] = $method;
        }

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Export an array as formatted PHP code.
     */
    private function exportArray(array $data, int $indentLevel): string
    {
        if ($data === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $indentLevel);
        $innerIndent = str_repeat('    ', $indentLevel + 1);

        $lines = ['['];
        foreach ($data as $key => $value) {
            $keyStr = var_export($key, true);
            if (is_array($value)) {
                $valueStr = $this->exportArray($value, $indentLevel + 1);
            } else {
                $valueStr = var_export($value, true);
            }
            $lines[] = "{$innerIndent}{$keyStr} => {$valueStr},";
        }
        $lines[] = "{$indent}]";

        return implode("\n", $lines);
    }
}
