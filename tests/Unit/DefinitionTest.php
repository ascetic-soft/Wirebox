<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Container;
use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Lifetime;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use PHPUnit\Framework\TestCase;

final class DefinitionTest extends TestCase
{
    public function testDefaultLifetimeIsSingleton(): void
    {
        $definition = new Definition();

        self::assertSame(Lifetime::Singleton, $definition->getLifetime());
        self::assertTrue($definition->isSingleton());
    }

    public function testConstructorAcceptsClassName(): void
    {
        $definition = new Definition(className: SimpleService::class);

        self::assertSame(SimpleService::class, $definition->getClassName());
        self::assertNull($definition->getFactory());
    }

    public function testConstructorAcceptsFactory(): void
    {
        $factory = fn (Container $c) => new SimpleService();
        $definition = new Definition(factory: $factory);

        self::assertNull($definition->getClassName());
        self::assertSame($factory, $definition->getFactory());
    }

    public function testConstructorDefaultsAreNull(): void
    {
        $definition = new Definition();

        self::assertNull($definition->getClassName());
        self::assertNull($definition->getFactory());
    }

    public function testSingletonFluentSetter(): void
    {
        $definition = new Definition();
        $result = $definition->transient()->singleton();

        self::assertSame($definition, $result);
        self::assertSame(Lifetime::Singleton, $definition->getLifetime());
        self::assertTrue($definition->isSingleton());
    }

    public function testTransientFluentSetter(): void
    {
        $definition = new Definition();
        $result = $definition->transient();

        self::assertSame($definition, $result);
        self::assertSame(Lifetime::Transient, $definition->getLifetime());
        self::assertFalse($definition->isSingleton());
    }

    public function testLifetimeFluentSetter(): void
    {
        $definition = new Definition();

        $definition->lifetime(Lifetime::Transient);
        self::assertSame(Lifetime::Transient, $definition->getLifetime());

        $definition->lifetime(Lifetime::Singleton);
        self::assertSame(Lifetime::Singleton, $definition->getLifetime());
    }

    public function testTagAddsUniqueTags(): void
    {
        $definition = new Definition();
        $result = $definition->tag('logger', 'handler');

        self::assertSame($definition, $result);
        self::assertSame(['logger', 'handler'], $definition->getTags());
    }

    public function testTagDeduplicates(): void
    {
        $definition = new Definition();
        $definition->tag('logger')->tag('logger')->tag('handler');

        self::assertSame(['logger', 'handler'], $definition->getTags());
    }

    public function testTagsAreEmptyByDefault(): void
    {
        $definition = new Definition();

        self::assertSame([], $definition->getTags());
    }

    public function testCallRegistersMethodCalls(): void
    {
        $definition = new Definition();
        $result = $definition->call('setLogger', [SimpleService::class]);

        self::assertSame($definition, $result);
        $calls = $definition->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('setLogger', $calls[0]['method']);
        self::assertSame([SimpleService::class], $calls[0]['arguments']);
    }

    public function testCallDefaultArgumentsAreEmpty(): void
    {
        $definition = new Definition();
        $definition->call('init');

        $calls = $definition->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('init', $calls[0]['method']);
        self::assertSame([], $calls[0]['arguments']);
    }

    public function testCallAccumulatesMultipleCalls(): void
    {
        $definition = new Definition();
        $definition->call('init')
            ->call('configure', ['value1'])
            ->call('setLogger', [SimpleService::class]);

        $calls = $definition->getMethodCalls();
        self::assertCount(3, $calls);
        self::assertSame('init', $calls[0]['method']);
        self::assertSame('configure', $calls[1]['method']);
        self::assertSame('setLogger', $calls[2]['method']);
    }

    public function testMethodCallsAreEmptyByDefault(): void
    {
        $definition = new Definition();

        self::assertSame([], $definition->getMethodCalls());
    }

    public function testSetClassName(): void
    {
        $definition = new Definition();
        $result = $definition->setClassName(SimpleService::class);

        self::assertSame($definition, $result);
        self::assertSame(SimpleService::class, $definition->getClassName());
    }

    public function testSetFactory(): void
    {
        $factory = fn (Container $c) => new SimpleService();
        $definition = new Definition();
        $result = $definition->setFactory($factory);

        self::assertSame($definition, $result);
        self::assertSame($factory, $definition->getFactory());
    }

    public function testLazyIsFalseByDefault(): void
    {
        $definition = new Definition();

        self::assertFalse($definition->isLazy());
    }

    public function testLazyFluentSetter(): void
    {
        $definition = new Definition();
        $result = $definition->lazy();

        self::assertSame($definition, $result);
        self::assertTrue($definition->isLazy());
    }

    public function testLazyCanBeDisabled(): void
    {
        $definition = new Definition();
        $definition->lazy();
        $definition->lazy(false);

        self::assertFalse($definition->isLazy());
    }

    public function testFluentChaining(): void
    {
        $factory = fn (Container $c) => new SimpleService();

        $definition = new Definition()
            ->setClassName(SimpleService::class)
            ->setFactory($factory)
            ->transient()
            ->lazy()
            ->tag('service', 'injectable')
            ->call('init')
            ->call('configure', ['value']);

        self::assertSame(SimpleService::class, $definition->getClassName());
        self::assertSame($factory, $definition->getFactory());
        self::assertSame(Lifetime::Transient, $definition->getLifetime());
        self::assertTrue($definition->isLazy());
        self::assertSame(['service', 'injectable'], $definition->getTags());
        self::assertCount(2, $definition->getMethodCalls());
    }
}
