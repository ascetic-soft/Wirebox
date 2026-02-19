<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\AutoBindingResolver;
use AsceticSoft\Wirebox\Exception\ContainerException;
use AsceticSoft\Wirebox\Tests\Fixtures\DatabaseLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use PHPUnit\Framework\TestCase;

final class AutoBindingResolverTest extends TestCase
{
    private AutoBindingResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AutoBindingResolver();
    }

    public function testSingleImplementationBindsAutomatically(): void
    {
        $this->resolver->registerImplementation(
            FileLogger::class,
            [LoggerInterface::class],
            [],
        );

        self::assertSame(
            [LoggerInterface::class => FileLogger::class],
            $this->resolver->getBindings(),
        );
    }

    public function testMultipleImplementationsCreateAmbiguity(): void
    {
        $this->resolver->registerImplementation(FileLogger::class, [LoggerInterface::class], []);
        $this->resolver->registerImplementation(DatabaseLogger::class, [LoggerInterface::class], []);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Ambiguous auto-binding/');

        $this->resolver->validateNoAmbiguity();
    }

    public function testExplicitBindResolvesAmbiguity(): void
    {
        $this->resolver->registerImplementation(FileLogger::class, [LoggerInterface::class], []);
        $this->resolver->registerImplementation(DatabaseLogger::class, [LoggerInterface::class], []);
        $this->resolver->bind(LoggerInterface::class, FileLogger::class);

        $this->resolver->validateNoAmbiguity();

        self::assertSame(FileLogger::class, $this->resolver->getBindings()[LoggerInterface::class]);
    }

    public function testExcludedInterfaceSkipsAmbiguityCheck(): void
    {
        $this->resolver->exclude(LoggerInterface::class);
        $this->resolver->registerImplementation(FileLogger::class, [LoggerInterface::class], []);
        $this->resolver->registerImplementation(DatabaseLogger::class, [LoggerInterface::class], []);

        $this->resolver->validateNoAmbiguity();
        self::assertEmpty($this->resolver->getBindings());
    }

    public function testValidateNoAmbiguityPassesWhenClean(): void
    {
        $this->resolver->registerImplementation(FileLogger::class, [LoggerInterface::class], []);

        $this->resolver->validateNoAmbiguity();
        $this->addToAssertionCount(1);
    }

    public function testEmptyBindingsValidates(): void
    {
        $this->resolver->validateNoAmbiguity();
        self::assertSame([], $this->resolver->getBindings());
    }
}
