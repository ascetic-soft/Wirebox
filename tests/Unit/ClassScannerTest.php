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
}
