<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Scanner\ClassScanner;
use PHPUnit\Framework\TestCase;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\WithAttributedAnonymous;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\BogusNamespace\NonLoadable;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\Sub\SubConcreteClass;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\WithAnonymousClass;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\ConcreteClass;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\WithDoubleColon;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\SomeEnum;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\SomeInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\AbstractClass;

final class ClassScannerTest extends TestCase
{
    private ClassScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new ClassScanner();
    }

    public function testFindsConcreteClassesOnly(): void
    {
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        // Should find ConcreteClass and SubConcreteClass
        self::assertContains(ConcreteClass::class, $classes);
        self::assertContains(SubConcreteClass::class, $classes);

        // Should NOT find abstract, interface, trait, enum
        self::assertNotContains(AbstractClass::class, $classes);
        self::assertNotContains(SomeInterface::class, $classes);
        self::assertNotContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\SomeTrait', $classes);
        self::assertNotContains(SomeEnum::class, $classes);
    }

    public function testExcludePattern(): void
    {
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan', ['Sub/*']);

        self::assertContains(ConcreteClass::class, $classes);
        self::assertNotContains(SubConcreteClass::class, $classes);
    }

    public function testThrowsOnInvalidDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->scanner->scan('/nonexistent/directory');
    }

    public function testScanSkipsNonPhpFiles(): void
    {
        // The Scan directory contains ignored.txt — it must be skipped
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        // No class named 'ignored' should appear
        foreach ($classes as $class) {
            self::assertStringNotContainsString('ignored', $class);
        }
    }

    public function testScanHandlesDoubleColonClassReference(): void
    {
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        // WithDoubleColon class should be found
        self::assertContains(WithDoubleColon::class, $classes);

        // ConcreteClass::class inside WithDoubleColon should NOT produce a duplicate
        $concreteCount = \array_count_values($classes)[ConcreteClass::class] ?? 0;
        self::assertSame(1, $concreteCount);
    }

    public function testScanHandlesAnonymousClass(): void
    {
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        // The named class should be found
        self::assertContains(WithAnonymousClass::class, $classes);

        // No anonymous class artifacts should be in the list
        foreach ($classes as $class) {
            self::assertStringNotContainsString('class@anonymous', $class);
        }
    }

    public function testExcludePatternByBasename(): void
    {
        // Use SubConcreteClass.php — relative path is "Sub/SubConcreteClass.php"
        // which won't match the pattern with FNM_PATHNAME, but basename will match
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan', ['SubConcreteClass.php']);

        self::assertNotContains(
            SubConcreteClass::class,
            $classes,
        );
    }

    public function testScanFindsNonLoadableClassByName(): void
    {
        // NonLoadable.php has a wrong namespace — scanner should still extract its FQCN
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        self::assertContains(
            NonLoadable::class,
            $classes,
        );
    }

    public function testScanHandlesAttributedAnonymousClass(): void
    {
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        // The named class should be found
        self::assertContains(
            WithAttributedAnonymous::class,
            $classes,
        );
    }
}
