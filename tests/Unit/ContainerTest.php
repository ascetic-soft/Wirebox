<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Container;
use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Env\EnvResolver;
use AsceticSoft\Wirebox\Exception\ContainerException;
use AsceticSoft\Wirebox\Exception\NotFoundException;
use AsceticSoft\Wirebox\Tests\Fixtures\DatabaseLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDeps;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithSetter;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use AsceticSoft\Wirebox\Tests\Fixtures\TransientService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ContainerTest extends TestCase
{
    public function testGetSimpleService(): void
    {
        $definition = new Definition(className: SimpleService::class);
        $container = new Container(
            definitions: [SimpleService::class => $definition],
        );

        $service = $container->get(SimpleService::class);

        self::assertInstanceOf(SimpleService::class, $service);
    }

    public function testSingletonByDefault(): void
    {
        $definition = new Definition(className: SimpleService::class);
        $container = new Container(
            definitions: [SimpleService::class => $definition],
        );

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        self::assertSame($a, $b);
    }

    public function testTransientCreatesNewInstances(): void
    {
        $definition = new Definition(className: SimpleService::class)->transient();
        $container = new Container(
            definitions: [SimpleService::class => $definition],
        );

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        self::assertNotSame($a, $b);
    }

    public function testBindingResolvesInterface(): void
    {
        $definition = new Definition(className: FileLogger::class);
        $container = new Container(
            definitions: [FileLogger::class => $definition],
            bindings: [LoggerInterface::class => FileLogger::class],
        );

        $logger = $container->get(LoggerInterface::class);

        self::assertInstanceOf(FileLogger::class, $logger);
    }

    public function testFactoryDefinition(): void
    {
        $definition = new Definition(
            factory: fn (Container $c) => new SimpleService(),
        );
        $container = new Container(
            definitions: [SimpleService::class => $definition],
        );

        $service = $container->get(SimpleService::class);

        self::assertInstanceOf(SimpleService::class, $service);
    }

    public function testMethodCallsSetterInjection(): void
    {
        $loggerDef = new Definition(className: FileLogger::class);
        $setterDef = new Definition(className: ServiceWithSetter::class)
            ->call('setLogger', [FileLogger::class]);

        $container = new Container(
            definitions: [
                FileLogger::class => $loggerDef,
                ServiceWithSetter::class => $setterDef,
            ],
        );

        /** @var ServiceWithSetter $service */
        $service = $container->get(ServiceWithSetter::class);

        self::assertInstanceOf(FileLogger::class, $service->logger);
    }

    public function testHasReturnsTrueForRegistered(): void
    {
        $definition = new Definition(className: SimpleService::class);
        $container = new Container(
            definitions: [SimpleService::class => $definition],
        );

        self::assertTrue($container->has(SimpleService::class));
    }

    public function testHasReturnsTrueForAutowirable(): void
    {
        $container = new Container();

        self::assertTrue($container->has(SimpleService::class));
    }

    public function testHasReturnsFalseForUnknown(): void
    {
        $container = new Container();

        self::assertFalse($container->has('NonExistentClass'));
    }

    public function testGetThrowsNotFoundException(): void
    {
        $container = new Container();

        $this->expectException(NotFoundException::class);
        $container->get('NonExistentService');
    }

    public function testAutoWiresWithoutExplicitDefinition(): void
    {
        $container = new Container();

        $service = $container->get(ServiceWithDeps::class);

        self::assertInstanceOf(ServiceWithDeps::class, $service);
        self::assertInstanceOf(SimpleService::class, $service->simple);
    }

    public function testContainerResolvesItself(): void
    {
        $container = new Container();

        self::assertSame($container, $container->get(Container::class));
        self::assertSame($container, $container->get(ContainerInterface::class));
    }

    public function testGetTagged(): void
    {
        $fileLoggerDef = new Definition(className: FileLogger::class)->tag('logger');
        $dbLoggerDef = new Definition(className: DatabaseLogger::class)->tag('logger');

        $container = new Container(
            definitions: [
                FileLogger::class => $fileLoggerDef,
                DatabaseLogger::class => $dbLoggerDef,
            ],
            tags: ['logger' => [FileLogger::class, DatabaseLogger::class]],
        );

        $loggers = \iterator_to_array($container->getTagged('logger'));

        self::assertCount(2, $loggers);
        self::assertInstanceOf(FileLogger::class, $loggers[0]);
        self::assertInstanceOf(DatabaseLogger::class, $loggers[1]);
    }

    public function testGetParameter(): void
    {
        $container = new Container(
            parameters: ['db.host' => 'localhost', 'db.port' => 5432],
        );

        self::assertSame('localhost', $container->getParameter('db.host'));
        self::assertSame(5432, $container->getParameter('db.port'));
        self::assertNull($container->getParameter('nonexistent'));
    }

    public function testGetParameters(): void
    {
        $params = ['db.host' => 'localhost', 'db.port' => 5432];
        $container = new Container(parameters: $params);

        self::assertSame($params, $container->getParameters());
    }

    public function testGetDefinitions(): void
    {
        $definition = new Definition(className: SimpleService::class);
        $container = new Container(
            definitions: [SimpleService::class => $definition],
        );

        $definitions = $container->getDefinitions();
        self::assertArrayHasKey(SimpleService::class, $definitions);
        self::assertSame($definition, $definitions[SimpleService::class]);
    }

    public function testGetBindings(): void
    {
        $bindings = [LoggerInterface::class => FileLogger::class];
        $container = new Container(bindings: $bindings);

        self::assertSame($bindings, $container->getBindings());
    }

    public function testGetTags(): void
    {
        $tags = ['logger' => [FileLogger::class, DatabaseLogger::class]];
        $container = new Container(tags: $tags);

        self::assertSame($tags, $container->getTags());
    }

    public function testGetTaggedReturnsEmptyForUnknownTag(): void
    {
        $container = new Container();

        $services = \iterator_to_array($container->getTagged('nonexistent'));

        self::assertSame([], $services);
    }

    public function testAutoWireTransientAttribute(): void
    {
        $container = new Container();

        $a = $container->get(TransientService::class);
        $b = $container->get(TransientService::class);

        self::assertInstanceOf(TransientService::class, $a);
        self::assertInstanceOf(TransientService::class, $b);
        self::assertNotSame($a, $b);
    }

    public function testAutoWireWithoutTransientIsSingleton(): void
    {
        $container = new Container();

        $a = $container->get(SimpleService::class);
        $b = $container->get(SimpleService::class);

        self::assertSame($a, $b);
    }

    public function testHasReturnsTrueForBinding(): void
    {
        $definition = new Definition(className: FileLogger::class);
        $container = new Container(
            definitions: [FileLogger::class => $definition],
            bindings: [LoggerInterface::class => FileLogger::class],
        );

        self::assertTrue($container->has(LoggerInterface::class));
    }

    public function testHasReturnsTrueForResolvedBindingInstance(): void
    {
        $definition = new Definition(className: FileLogger::class);
        $container = new Container(
            definitions: [FileLogger::class => $definition],
            bindings: [LoggerInterface::class => FileLogger::class],
        );

        // First resolve to cache the singleton
        $container->get(FileLogger::class);

        // Now check via binding
        self::assertTrue($container->has(LoggerInterface::class));
    }

    public function testGetResolvedBindingFromCache(): void
    {
        $definition = new Definition(className: FileLogger::class);
        $container = new Container(
            definitions: [FileLogger::class => $definition],
            bindings: [LoggerInterface::class => FileLogger::class],
        );

        // Resolve the concrete class first to cache it
        $direct = $container->get(FileLogger::class);
        // Now resolve via binding — should return the same cached instance
        $viaBinding = $container->get(LoggerInterface::class);

        self::assertSame($direct, $viaBinding);
    }

    public function testFactoryReturningNonObjectThrows(): void
    {
        $definition = new Definition(
            factory: fn (Container $c) => 'not an object',
        );
        $container = new Container(
            definitions: ['service' => $definition],
        );

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/must return an object/');
        $container->get('service');
    }

    public function testGetParameterFallsBackToEnvResolver(): void
    {
        $tmpDir = \sys_get_temp_dir() . '/wirebox_container_env_' . \uniqid();
        \mkdir($tmpDir, 0o755, true);
        \file_put_contents($tmpDir . '/.env', "MY_VAR=from_env\n");

        try {
            $envResolver = new EnvResolver($tmpDir);
            $container = new Container(envResolver: $envResolver);

            self::assertSame('from_env', $container->getParameter('MY_VAR'));
        } finally {
            @\unlink($tmpDir . '/.env');
            @\rmdir($tmpDir);
        }
    }

    public function testGetParameterReturnsNullWhenNotFoundAnywhere(): void
    {
        $container = new Container();

        self::assertNull($container->getParameter('NONEXISTENT'));
    }

}
