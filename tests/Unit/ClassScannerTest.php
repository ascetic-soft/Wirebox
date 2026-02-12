<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Scanner\ClassScanner;
use PHPUnit\Framework\TestCase;

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
        self::assertContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\ConcreteClass', $classes);
        self::assertContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\Sub\\SubConcreteClass', $classes);

        // Should NOT find abstract, interface, trait, enum
        self::assertNotContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\AbstractClass', $classes);
        self::assertNotContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\SomeInterface', $classes);
        self::assertNotContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\SomeTrait', $classes);
        self::assertNotContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\SomeEnum', $classes);
    }

    public function testExcludePattern(): void
    {
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan', ['Sub/*']);

        self::assertContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\ConcreteClass', $classes);
        self::assertNotContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\Sub\\SubConcreteClass', $classes);
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
        self::assertContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\WithDoubleColon', $classes);

        // ConcreteClass::class inside WithDoubleColon should NOT produce a duplicate
        $concreteCount = \array_count_values($classes)['AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\ConcreteClass'] ?? 0;
        self::assertSame(1, $concreteCount);
    }

    public function testScanHandlesAnonymousClass(): void
    {
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        // The named class should be found
        self::assertContains('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\WithAnonymousClass', $classes);

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
            'AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\Sub\\SubConcreteClass',
            $classes,
        );
    }

    public function testScanFindsNonLoadableClassByName(): void
    {
        // NonLoadable.php has a wrong namespace — scanner should still extract its FQCN
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        self::assertContains(
            'AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\BogusNamespace\\NonLoadable',
            $classes,
        );
    }

    public function testScanHandlesAttributedAnonymousClass(): void
    {
        $classes = $this->scanner->scan(__DIR__ . '/../Fixtures/Scan');

        // The named class should be found
        self::assertContains(
            'AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\WithAttributedAnonymous',
            $classes,
        );
    }
}
