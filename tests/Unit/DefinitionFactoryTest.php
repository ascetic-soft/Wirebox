<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\DefinitionFactory;
use AsceticSoft\Wirebox\Lifetime;
use AsceticSoft\Wirebox\Tests\Fixtures\EagerService;
use AsceticSoft\Wirebox\Tests\Fixtures\ExcludedService;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LazyService;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use AsceticSoft\Wirebox\Tests\Fixtures\TransientService;
use PHPUnit\Framework\TestCase;

final class DefinitionFactoryTest extends TestCase
{
    private DefinitionFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DefinitionFactory();
    }

    public function testIsExcludedReturnsTrueForExcludedClass(): void
    {
        $ref = new \ReflectionClass(ExcludedService::class);
        self::assertTrue($this->factory->isExcluded($ref));
    }

    public function testIsExcludedReturnsFalseForNormalClass(): void
    {
        $ref = new \ReflectionClass(SimpleService::class);
        self::assertFalse($this->factory->isExcluded($ref));
    }

    public function testCreateFromAttributesDefaultsToSingleton(): void
    {
        $ref = new \ReflectionClass(SimpleService::class);
        $definition = $this->factory->createFromAttributes($ref);

        self::assertTrue($definition->isSingleton());
        self::assertSame(Lifetime::Singleton, $definition->getLifetime());
    }

    public function testCreateFromAttributesReadsTransient(): void
    {
        $ref = new \ReflectionClass(TransientService::class);
        $definition = $this->factory->createFromAttributes($ref);

        self::assertFalse($definition->isSingleton());
        self::assertSame(Lifetime::Transient, $definition->getLifetime());
    }

    public function testCreateFromAttributesReadsLazy(): void
    {
        $ref = new \ReflectionClass(LazyService::class);
        $definition = $this->factory->createFromAttributes($ref);

        self::assertTrue($definition->isLazy());
        self::assertTrue($definition->hasExplicitLazy());
    }

    public function testCreateFromAttributesReadsEager(): void
    {
        $ref = new \ReflectionClass(EagerService::class);
        $definition = $this->factory->createFromAttributes($ref);

        self::assertFalse($definition->isLazy());
        self::assertTrue($definition->hasExplicitLazy());
    }

    public function testCreateFromAttributesReadsTag(): void
    {
        $ref = new \ReflectionClass(FileLogger::class);
        $definition = $this->factory->createFromAttributes($ref);

        self::assertContains('logger', $definition->getTags());
    }

    public function testCreateFromAttributesSetsClassName(): void
    {
        $ref = new \ReflectionClass(SimpleService::class);
        $definition = $this->factory->createFromAttributes($ref);

        self::assertSame(SimpleService::class, $definition->getClassName());
    }

    public function testCreateFromAttributesNoExplicitLazyForPlainClass(): void
    {
        $ref = new \ReflectionClass(SimpleService::class);
        $definition = $this->factory->createFromAttributes($ref);

        self::assertFalse($definition->hasExplicitLazy());
    }
}
