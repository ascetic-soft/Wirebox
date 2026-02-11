<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Autowire\Autowirer;
use AsceticSoft\Wirebox\Container;
use AsceticSoft\Wirebox\Exception\AutowireException;
use AsceticSoft\Wirebox\Exception\CircularDependencyException;
use AsceticSoft\Wirebox\Tests\Fixtures\CircularA;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDefault;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDeps;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithInject;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use PHPUnit\Framework\TestCase;

final class AutowirerTest extends TestCase
{
    private Autowirer $autowirer;

    protected function setUp(): void
    {
        $this->autowirer = new Autowirer();
    }

    public function testResolveSimpleClass(): void
    {
        $container = new Container();
        $instance = $this->autowirer->resolve(SimpleService::class, $container);

        self::assertInstanceOf(SimpleService::class, $instance);
        self::assertSame('hello', $instance->hello());
    }

    public function testResolveWithDependencies(): void
    {
        $container = new Container();
        $instance = $this->autowirer->resolve(ServiceWithDeps::class, $container);

        self::assertInstanceOf(ServiceWithDeps::class, $instance);
        self::assertInstanceOf(SimpleService::class, $instance->simple);
    }

    public function testResolveWithInjectAttribute(): void
    {
        $container = new Container(
            bindings: [LoggerInterface::class => FileLogger::class],
        );
        $instance = $this->autowirer->resolve(ServiceWithInject::class, $container);

        self::assertInstanceOf(ServiceWithInject::class, $instance);
        self::assertInstanceOf(FileLogger::class, $instance->logger);
    }

    public function testResolveWithDefaultValues(): void
    {
        $container = new Container();
        $instance = $this->autowirer->resolve(ServiceWithDefault::class, $container);

        self::assertInstanceOf(ServiceWithDefault::class, $instance);
        self::assertSame('default', $instance->name);
        self::assertNull($instance->logger);
    }

    public function testCircularDependencyDetected(): void
    {
        $container = new Container();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected/');

        $this->autowirer->resolve(CircularA::class, $container);
    }

    public function testThrowsOnNonExistentClass(): void
    {
        $container = new Container();

        $this->expectException(AutowireException::class);

        /** @phpstan-ignore-next-line */
        $this->autowirer->resolve('NonExistentClass', $container);
    }

    public function testThrowsOnInterface(): void
    {
        $container = new Container();

        $this->expectException(AutowireException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');

        $this->autowirer->resolve(LoggerInterface::class, $container);
    }
}
