<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Compiler\ContainerCompiler;
use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use PHPUnit\Framework\TestCase;

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

        $content = \file_get_contents($outputPath);
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
        /** @var \AsceticSoft\Wirebox\Compiler\CompiledContainer $container */
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

        $content = \file_get_contents($outputPath);
        self::assertStringContainsString('namespace App\\Container;', $content);
    }
}
