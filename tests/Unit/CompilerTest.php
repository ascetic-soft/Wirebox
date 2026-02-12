<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Compiler\CompiledContainer;
use AsceticSoft\Wirebox\Compiler\ContainerCompiler;
use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Exception\NotFoundException;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDeps;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithInject;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithParam;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDefault;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithNullable;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithNullableBuiltin;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithSetter;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithUnresolvable;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class CompilerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/wirebox_compiler_test_' . \uniqid();
        \mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $files = \glob($this->tmpDir . '/*') ?: [];
        foreach ($files as $file) {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
        if (\is_dir($this->tmpDir)) {
            \rmdir($this->tmpDir);
        }
    }

    public function testCompileGeneratesValidPhpFile(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/TestContainer.php';

        $compiler->compile(
            definitions: [
                SimpleService::class => new Definition(className: SimpleService::class),
            ],
            bindings: [],
            parameters: ['app.name' => 'Wirebox'],
            tags: [],
            outputPath: $outputPath,
            className: 'TestContainer',
        );

        self::assertFileExists($outputPath);

        $content = (string) \file_get_contents($outputPath);
        self::assertStringContainsString('class TestContainer', $content);
        self::assertStringContainsString('declare(strict_types=1)', $content);
        self::assertStringContainsString('CompiledContainer', $content);
    }

    public function testCompiledContainerResolves(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/TestResolvable.php';
        $uniqueClass = 'TestResolvable_' . \uniqid();

        $compiler->compile(
            definitions: [
                SimpleService::class => new Definition(className: SimpleService::class),
                FileLogger::class => new Definition(className: FileLogger::class),
            ],
            bindings: [
                LoggerInterface::class => FileLogger::class,
            ],
            parameters: ['db.host' => 'localhost'],
            tags: ['logger' => [FileLogger::class]],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        // Get simple service
        $service = $container->get(SimpleService::class);
        self::assertInstanceOf(SimpleService::class, $service);

        // Singleton behavior
        self::assertSame($service, $container->get(SimpleService::class));

        // Binding resolution
        $logger = $container->get(LoggerInterface::class);
        self::assertInstanceOf(FileLogger::class, $logger);

        // Parameters
        self::assertSame('localhost', $container->getParameter('db.host'));

        // Tags
        $loggers = \iterator_to_array($container->getTagged('logger'));
        self::assertCount(1, $loggers);
        self::assertInstanceOf(FileLogger::class, $loggers[0]);

        // has()
        self::assertTrue($container->has(SimpleService::class));
        self::assertFalse($container->has('NonExistent'));
    }

    public function testCompileWithNamespace(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/NamespacedContainer.php';

        $compiler->compile(
            definitions: [
                SimpleService::class => new Definition(className: SimpleService::class),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: 'NamespacedContainer',
            namespace: 'App\\Container',
        );

        $content = (string) \file_get_contents($outputPath);
        self::assertStringContainsString('namespace App\\Container;', $content);
    }

    public function testCompileSkipsFactoryDefinitions(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/FactorySkipped.php';

        $compiler->compile(
            definitions: [
                'factory_service' => new Definition(
                    factory: fn () => new SimpleService(),
                ),
                SimpleService::class => new Definition(className: SimpleService::class),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: 'FactorySkipped',
        );

        $content = (string) \file_get_contents($outputPath);
        // Factory-based definitions should be skipped
        self::assertStringNotContainsString('factory_service', $content);
        // But the class-based definition should be present
        self::assertStringContainsString('SimpleService', $content);
    }

    public function testCompileWithMethodCalls(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/WithMethodCalls.php';
        $uniqueClass = 'WithMethodCalls_' . \uniqid();

        $compiler->compile(
            definitions: [
                FileLogger::class => new Definition(className: FileLogger::class),
                ServiceWithSetter::class => new Definition(className: ServiceWithSetter::class)
                    ->call('setLogger', [FileLogger::class]),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        /** @var ServiceWithSetter $service */
        $service = $container->get(ServiceWithSetter::class);
        self::assertInstanceOf(ServiceWithSetter::class, $service);
        self::assertInstanceOf(FileLogger::class, $service->logger);
    }

    public function testCompileWithTransientDefinition(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/TransientCompiled.php';
        $uniqueClass = 'TransientCompiled_' . \uniqid();

        $compiler->compile(
            definitions: [
                SimpleService::class => new Definition(className: SimpleService::class)->transient(),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        // Transient means new instance each time
        self::assertNotSame($a, $b);
    }

    public function testCompileWithConstructorDependencies(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/WithDeps.php';
        $uniqueClass = 'WithDeps_' . \uniqid();

        $compiler->compile(
            definitions: [
                SimpleService::class => new Definition(className: SimpleService::class),
                ServiceWithDeps::class => new Definition(className: ServiceWithDeps::class),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        /** @var ServiceWithDeps $service */
        $service = $container->get(ServiceWithDeps::class);
        self::assertInstanceOf(ServiceWithDeps::class, $service);
        self::assertInstanceOf(SimpleService::class, $service->simple);
    }

    public function testCompiledContainerThrowsNotFoundForUnknownService(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/ThrowsNotFound.php';
        $uniqueClass = 'ThrowsNotFound_' . \uniqid();

        $compiler->compile(
            definitions: [
                SimpleService::class => new Definition(className: SimpleService::class),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        $this->expectException(NotFoundException::class);
        $container->get('NonExistentService');
    }

    public function testCompiledContainerRegistersItself(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/RegistersSelf.php';
        $uniqueClass = 'RegistersSelf_' . \uniqid();

        $compiler->compile(
            definitions: [],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        self::assertSame($container, $container->get(ContainerInterface::class));
        self::assertSame($container, $container->get($uniqueClass));
    }

    public function testCompiledContainerGetParametersReturnsAll(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/ParamsAll.php';
        $uniqueClass = 'ParamsAll_' . \uniqid();

        $params = ['db.host' => 'localhost', 'db.port' => '3306'];

        $compiler->compile(
            definitions: [],
            bindings: $params,
            parameters: $params,
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        self::assertSame($params, $container->getParameters());
        self::assertNull($container->getParameter('nonexistent'));
    }

    public function testCompileWithInjectAttribute(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/WithInject.php';
        $uniqueClass = 'WithInject_' . \uniqid();

        $compiler->compile(
            definitions: [
                FileLogger::class => new Definition(className: FileLogger::class),
                ServiceWithInject::class => new Definition(className: ServiceWithInject::class),
            ],
            bindings: [LoggerInterface::class => FileLogger::class],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        /** @var ServiceWithInject $service */
        $service = $container->get(ServiceWithInject::class);
        self::assertInstanceOf(ServiceWithInject::class, $service);
        self::assertInstanceOf(FileLogger::class, $service->logger);
    }

    public function testCompileWithParamAttribute(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/WithParam.php';
        $uniqueClass = 'WithParam_' . \uniqid();

        // Compiled containers use pre-resolved parameters,
        // so bool/int values must already be the correct type
        $compiler->compile(
            definitions: [
                ServiceWithParam::class => new Definition(className: ServiceWithParam::class),
            ],
            bindings: [],
            parameters: ['DB_HOST' => 'testhost', 'APP_DEBUG' => false],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        /** @var ServiceWithParam $service */
        $service = $container->get(ServiceWithParam::class);
        self::assertInstanceOf(ServiceWithParam::class, $service);
        self::assertSame('testhost', $service->dbHost);
        self::assertFalse($service->debug);
    }

    public function testCompiledContainerHasViaBinding(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/HasBinding.php';
        $uniqueClass = 'HasBinding_' . \uniqid();

        $compiler->compile(
            definitions: [
                FileLogger::class => new Definition(className: FileLogger::class),
            ],
            bindings: [LoggerInterface::class => FileLogger::class],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        self::assertTrue($container->has(LoggerInterface::class));
        self::assertTrue($container->has(FileLogger::class));
        self::assertFalse($container->has('DoesNotExist'));
    }

    public function testCompileWithEmptyDefinitions(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/EmptyContainer.php';
        $uniqueClass = 'EmptyContainer_' . \uniqid();

        $compiler->compile(
            definitions: [],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        self::assertFileExists($outputPath);
        $content = (string) \file_get_contents($outputPath);
        self::assertStringContainsString("class $uniqueClass", $content);

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();
        self::assertTrue($container->has(ContainerInterface::class));
    }

    public function testCompileCreatesDirectoryIfNotExists(): void
    {
        $compiler = new ContainerCompiler();
        $nestedDir = $this->tmpDir . '/nested/deep';
        $outputPath = $nestedDir . '/Container.php';

        $compiler->compile(
            definitions: [],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: 'NestedContainer',
        );

        self::assertFileExists($outputPath);

        // Cleanup
        @\unlink($outputPath);
        @\rmdir($nestedDir);
        @\rmdir($this->tmpDir . '/nested');
    }

    public function testCompileSkipsNonExistentClass(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/SkipNonExistent.php';

        $compiler->compile(
            definitions: [
                'NonExistentFakeClass' => new Definition(className: 'NonExistentFakeClass'),
                SimpleService::class => new Definition(className: SimpleService::class),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: 'SkipNonExistent',
        );

        $content = (string) \file_get_contents($outputPath);
        self::assertStringNotContainsString('NonExistentFakeClass', $content);
        self::assertStringContainsString('SimpleService', $content);
    }

    public function testCompileWithScalarMethodCallArguments(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/ScalarArgs.php';

        $compiler->compile(
            definitions: [
                FileLogger::class => new Definition(className: FileLogger::class),
                ServiceWithSetter::class => new Definition(className: ServiceWithSetter::class)
                    ->call('setLogger', [42]),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: 'ScalarArgs',
        );

        $content = (string) \file_get_contents($outputPath);
        // Scalar argument should be var_exported (42 as-is)
        self::assertStringContainsString('42', $content);
    }

    public function testCompileWithDefaultValueParameter(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/WithDefault.php';

        $compiler->compile(
            definitions: [
                ServiceWithDefault::class => new Definition(className: ServiceWithDefault::class),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: 'WithDefault',
        );

        $content = (string) \file_get_contents($outputPath);
        // Default value 'default' should appear in the generated code
        self::assertStringContainsString("'default'", $content);
    }

    public function testCompileWithNullableBuiltinParameter(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/WithNullable.php';

        $compiler->compile(
            definitions: [
                ServiceWithNullableBuiltin::class => new Definition(className: ServiceWithNullableBuiltin::class),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: 'WithNullable',
        );

        $content = (string) \file_get_contents($outputPath);
        // Nullable builtin parameter without default should resolve to null
        self::assertMatchesRegularExpression('/\bnull\b/', $content);
    }

    public function testCompileWithUnresolvableParameter(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/WithUnresolvable.php';

        $compiler->compile(
            definitions: [
                ServiceWithUnresolvable::class => new Definition(className: ServiceWithUnresolvable::class),
            ],
            bindings: [],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: 'WithUnresolvable',
        );

        $content = (string) \file_get_contents($outputPath);
        // Unresolvable builtin param should produce a WARNING comment
        self::assertStringContainsString('WARNING: cannot resolve', $content);
    }

    public function testCompiledContainerResolveBindingFromInstanceCache(): void
    {
        $compiler = new ContainerCompiler();
        $outputPath = $this->tmpDir . '/BindingCache.php';
        $uniqueClass = 'BindingCache_' . \uniqid();

        $compiler->compile(
            definitions: [
                FileLogger::class => new Definition(className: FileLogger::class),
            ],
            bindings: [LoggerInterface::class => FileLogger::class],
            parameters: [],
            tags: [],
            outputPath: $outputPath,
            className: $uniqueClass,
        );

        require_once $outputPath;
        /** @var CompiledContainer $container */
        $container = new $uniqueClass();

        // First resolve FileLogger directly — caches in instances
        $direct = $container->get(FileLogger::class);

        // Now resolve via interface binding — should hit the instance cache path
        $viaBinding = $container->get(LoggerInterface::class);

        self::assertSame($direct, $viaBinding);
    }

    public function testCompileThrowsWhenDirectoryCannotBeCreated(): void
    {
        $compiler = new ContainerCompiler();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/was not created/');

        // /dev/null is a file, so mkdir inside it will fail.
        // Suppress the PHP warning from mkdir() to avoid PHPUnit failOnWarning.
        \set_error_handler(static fn () => true);
        try {
            $compiler->compile(
                definitions: [],
                bindings: [],
                parameters: [],
                tags: [],
                outputPath: '/dev/null/impossible/dir/Container.php',
                className: 'Impossible',
            );
        } finally {
            \restore_error_handler();
        }
    }
}
