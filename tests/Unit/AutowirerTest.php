<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Autowire\Autowirer;
use AsceticSoft\Wirebox\Container;
use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Exception\AutowireException;
use AsceticSoft\Wirebox\Exception\CircularDependencyException;
use AsceticSoft\Wirebox\Tests\Fixtures\CircularA;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDefault;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\AbstractClass;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithArrayParam;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDeps;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithNonBuiltinParam;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithInject;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithIntParam;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithMissingParam;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithNullable;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithOptionalParam;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithSetter;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithUnion;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithUnresolvable;
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
        $this->expectExceptionMessageMatches('/does not exist|not instantiable/');

        $this->autowirer->resolve(LoggerInterface::class, $container);
    }

    public function testResolveWithNullableParameterReturnsNull(): void
    {
        $container = new Container();
        $instance = $this->autowirer->resolve(ServiceWithNullable::class, $container);

        self::assertInstanceOf(ServiceWithNullable::class, $instance);
        self::assertNull($instance->logger);
    }

    public function testResolveWithUnionTypePicksAvailable(): void
    {
        $container = new Container();
        $instance = $this->autowirer->resolve(ServiceWithUnion::class, $container);

        self::assertInstanceOf(ServiceWithUnion::class, $instance);
        // SimpleService is a concrete class that can be autowired
        self::assertInstanceOf(SimpleService::class, $instance->dependency);
    }

    public function testThrowsOnUnresolvableParameter(): void
    {
        $container = new Container();

        $this->expectException(AutowireException::class);
        $this->expectExceptionMessageMatches('/Cannot resolve parameter/');

        $this->autowirer->resolve(ServiceWithUnresolvable::class, $container);
    }

    public function testResolveMethodArgumentsWithClassString(): void
    {
        $loggerDef = new Definition(className: FileLogger::class);
        $container = new Container(
            definitions: [FileLogger::class => $loggerDef],
        );

        $method = new \ReflectionMethod(ServiceWithSetter::class, 'setLogger');
        $args = $this->autowirer->resolveMethodArguments($method, [FileLogger::class], $container);

        self::assertCount(1, $args);
        self::assertInstanceOf(FileLogger::class, $args[0]);
    }

    public function testResolveMethodArgumentsWithScalarValue(): void
    {
        $container = new Container();

        //        $method = new \ReflectionMethod(ServiceWithSetter::class, 'setLogger');
        // When a non-class-string is provided, it should be passed as-is
        // In this case, we use a scalar value that will be passed directly
        $method = new \ReflectionMethod(new class () {
            public function doSomething(string $name): void
            {
            }
        }, 'doSomething');

        $args = $this->autowirer->resolveMethodArguments($method, ['hello'], $container);

        self::assertCount(1, $args);
        self::assertSame('hello', $args[0]);
    }

    public function testResolveMethodArgumentsAutowiresWhenNotProvided(): void
    {
        $method = new \ReflectionMethod(ServiceWithSetter::class, 'setLogger');
        // Don't provide any arguments — should try to autowire from type hint
        // LoggerInterface can't be autowired without binding, but FileLogger is a concrete
        $container = new Container(
            bindings: [LoggerInterface::class => FileLogger::class],
        );

        $args = $this->autowirer->resolveMethodArguments($method, [], $container);

        self::assertCount(1, $args);
        self::assertInstanceOf(FileLogger::class, $args[0]);
    }

    public function testResolveWithParamAttributeCastsInt(): void
    {
        $container = new Container(
            parameters: ['PORT' => '8080', 'RATE' => '3.14', 'DEBUG' => 'true'],
        );

        /** @var ServiceWithIntParam $instance */
        $instance = $this->autowirer->resolve(ServiceWithIntParam::class, $container);

        self::assertSame(8080, $instance->port);
        self::assertSame(3.14, $instance->rate);
        self::assertTrue($instance->debug);
    }

    public function testResolveWithMissingParamThrows(): void
    {
        $container = new Container();

        $this->expectException(AutowireException::class);
        $this->expectExceptionMessageMatches('/Parameter "MISSING_PARAM" is not defined/');

        $this->autowirer->resolve(ServiceWithMissingParam::class, $container);
    }

    public function testCircularDependencyExceptionContainsChain(): void
    {
        $container = new Container();

        try {
            $this->autowirer->resolve(CircularA::class, $container);
            self::fail('Expected CircularDependencyException');
        } catch (CircularDependencyException $e) {
            self::assertStringContainsString('CircularA', $e->getMessage());
            self::assertStringContainsString('CircularB', $e->getMessage());
            self::assertStringContainsString('->', $e->getMessage());
        }
    }

    public function testResolveResetsStackAfterSuccess(): void
    {
        $container = new Container();

        // First resolve should work fine
        $this->autowirer->resolve(SimpleService::class, $container);

        // Second resolve should also work (stack should be clean)
        $instance = $this->autowirer->resolve(SimpleService::class, $container);

        self::assertInstanceOf(SimpleService::class, $instance);
    }

    public function testResolveResetsStackAfterFailure(): void
    {
        $container = new Container();

        // This should fail
        try {
            $this->autowirer->resolve(ServiceWithUnresolvable::class, $container);
        } catch (AutowireException) {
            // Expected
        }

        // But subsequent resolves should still work (stack is clean)
        $instance = $this->autowirer->resolve(SimpleService::class, $container);
        self::assertInstanceOf(SimpleService::class, $instance);
    }

    public function testResolveWithParamMissingButDefaultValueAvailable(): void
    {
        // Container has no 'OPTIONAL' parameter — should fall back to PHP default
        $container = new Container();

        /** @var ServiceWithOptionalParam $instance */
        $instance = $this->autowirer->resolve(ServiceWithOptionalParam::class, $container);

        self::assertSame('fallback', $instance->val);
    }

    public function testCastParameterValueWithNonStringPassesThrough(): void
    {
        // When container parameters are already the correct type (non-string),
        // castParameterValue should return them as-is
        $container = new Container(
            parameters: ['PORT' => 8080, 'RATE' => 3.14, 'DEBUG' => true],
        );

        /** @var ServiceWithIntParam $instance */
        $instance = $this->autowirer->resolve(ServiceWithIntParam::class, $container);

        self::assertSame(8080, $instance->port);
        self::assertSame(3.14, $instance->rate);
        self::assertTrue($instance->debug);
    }

    public function testCastParameterValueWithArrayType(): void
    {
        $container = new Container(
            parameters: ['CONFIG' => '{"key":"val"}'],
        );

        /** @var ServiceWithArrayParam $instance */
        $instance = $this->autowirer->resolve(ServiceWithArrayParam::class, $container);

        self::assertSame(['key' => 'val'], $instance->config);
    }

    public function testCastParameterValueWithUnionTypePassesThrough(): void
    {
        // When #[Param] is on a union-typed parameter (ReflectionUnionType, not ReflectionNamedType),
        // castParameterValue returns the string value as-is without casting
        $container = new Container(
            parameters: ['UNION_VAL' => 'raw_value'],
        );

        /** @var ServiceWithNonBuiltinParam $instance */
        $instance = $this->autowirer->resolve(ServiceWithNonBuiltinParam::class, $container);

        self::assertSame('raw_value', $instance->val);
    }

    public function testThrowsOnAbstractClass(): void
    {
        $container = new Container();

        $this->expectException(AutowireException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');

        $this->autowirer->resolve(AbstractClass::class, $container);
    }
}
