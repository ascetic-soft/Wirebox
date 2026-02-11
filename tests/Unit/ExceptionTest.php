<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Exception\AutowireException;
use AsceticSoft\Wirebox\Exception\CircularDependencyException;
use AsceticSoft\Wirebox\Exception\ContainerException;
use AsceticSoft\Wirebox\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class ExceptionTest extends TestCase
{
    public function testContainerExceptionImplementsPsrInterface(): void
    {
        $exception = new ContainerException('test');

        self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
        self::assertSame('test', $exception->getMessage());
    }

    public function testNotFoundExceptionImplementsPsrInterface(): void
    {
        $exception = new NotFoundException('not found');

        self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
        self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
        self::assertInstanceOf(ContainerException::class, $exception);
        self::assertSame('not found', $exception->getMessage());
    }

    public function testAutowireExceptionExtendsContainerException(): void
    {
        $exception = new AutowireException('autowire error');

        self::assertInstanceOf(ContainerException::class, $exception);
        self::assertInstanceOf(ContainerExceptionInterface::class, $exception);
        self::assertSame('autowire error', $exception->getMessage());
    }

    public function testCircularDependencyExceptionFormatsChain(): void
    {
        $exception = new CircularDependencyException(['A', 'B', 'C', 'A']);

        self::assertInstanceOf(ContainerException::class, $exception);
        self::assertSame('Circular dependency detected: A -> B -> C -> A', $exception->getMessage());
    }

    public function testCircularDependencyExceptionWithTwoClasses(): void
    {
        $exception = new CircularDependencyException(['ServiceA', 'ServiceA']);

        self::assertSame('Circular dependency detected: ServiceA -> ServiceA', $exception->getMessage());
    }

    public function testCircularDependencyExceptionWithFqcn(): void
    {
        $exception = new CircularDependencyException([
            'App\\Service\\UserService',
            'App\\Service\\AuthService',
            'App\\Service\\UserService',
        ]);

        $msg = $exception->getMessage();
        self::assertStringContainsString('App\\Service\\UserService', $msg);
        self::assertStringContainsString('App\\Service\\AuthService', $msg);
        self::assertStringContainsString('->', $msg);
    }
}
