<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Definition;
use AsceticSoft\Wirebox\Exception\CircularDependencyException;
use AsceticSoft\Wirebox\Tests\Fixtures\CircularA;
use AsceticSoft\Wirebox\Tests\Fixtures\CircularB;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDeps;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use AsceticSoft\Wirebox\Validator\CircularDependencyDetector;
use PHPUnit\Framework\TestCase;

final class CircularDependencyDetectorTest extends TestCase
{
    private CircularDependencyDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new CircularDependencyDetector();
    }

    public function testDetectsEagerCircularDependency(): void
    {
        $definitions = [
            CircularA::class => (new Definition(className: CircularA::class))->eager(),
            CircularB::class => (new Definition(className: CircularB::class))->eager(),
        ];

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected/');
        $this->expectExceptionMessageMatches('/not lazy/');

        $this->detector->detect($definitions, []);
    }

    public function testAllowsLazySingletonCircularDependency(): void
    {
        $definitions = [
            CircularA::class => (new Definition(className: CircularA::class))->lazy()->singleton(),
            CircularB::class => (new Definition(className: CircularB::class))->lazy()->singleton(),
        ];

        $this->detector->detect($definitions, []);
        $this->addToAssertionCount(1);
    }

    public function testDetectsLazyTransientCircularDependency(): void
    {
        $definitions = [
            CircularA::class => (new Definition(className: CircularA::class))->lazy()->transient(),
            CircularB::class => (new Definition(className: CircularB::class))->lazy()->transient(),
        ];

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/not a singleton/');

        $this->detector->detect($definitions, []);
    }

    public function testDetectsMixedLazyEagerCircularDependency(): void
    {
        $definitions = [
            CircularA::class => (new Definition(className: CircularA::class))->lazy()->singleton(),
            CircularB::class => (new Definition(className: CircularB::class))->eager()->singleton(),
        ];

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/CircularB.*not lazy/');

        $this->detector->detect($definitions, []);
    }

    public function testPassesWithoutCircularDependency(): void
    {
        $definitions = [
            SimpleService::class => (new Definition(className: SimpleService::class))->eager(),
            ServiceWithDeps::class => (new Definition(className: ServiceWithDeps::class))->eager(),
        ];

        $this->detector->detect($definitions, []);
        $this->addToAssertionCount(1);
    }

    public function testSkipsFactoryDefinitions(): void
    {
        $definitions = [
            'factory_service' => new Definition(
                factory: fn () => new SimpleService(),
            ),
            SimpleService::class => (new Definition(className: SimpleService::class))->eager(),
        ];

        $this->detector->detect($definitions, []);
        $this->addToAssertionCount(1);
    }

    public function testDetectsCircularDependencyThroughMethodCall(): void
    {
        $definitions = [
            SimpleService::class => (new Definition(className: SimpleService::class))
                ->eager()
                ->call('setDeps', [ServiceWithDeps::class]),
            ServiceWithDeps::class => (new Definition(className: ServiceWithDeps::class))->eager(),
        ];

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected/');

        $this->detector->detect($definitions, []);
    }
}
