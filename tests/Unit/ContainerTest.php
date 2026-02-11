<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Container;
use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Exception\NotFoundException;
use AsceticSoft\Wirebox\Lifetime;
use AsceticSoft\Wirebox\Tests\Fixtures\DatabaseLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDeps;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithSetter;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
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
        $definition = (new Definition(className: SimpleService::class))->transient();
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
            factory: fn(Container $c) => new SimpleService(),
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
        $setterDef = (new Definition(className: ServiceWithSetter::class))
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
        $fileLoggerDef = (new Definition(className: FileLogger::class))->tag('logger');
        $dbLoggerDef = (new Definition(className: DatabaseLogger::class))->tag('logger');

        $container = new Container(
            definitions: [
                FileLogger::class => $fileLoggerDef,
                DatabaseLogger::class => $dbLoggerDef,
            ],
            tags: ['logger' => [FileLogger::class, DatabaseLogger::class]],
        );

        $loggers = iterator_to_array($container->getTagged('logger'));

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
}
