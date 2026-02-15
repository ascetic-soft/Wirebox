<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\AutoconfigureRule;
use AsceticSoft\Wirebox\Container;
use AsceticSoft\Wirebox\ContainerBuilder;
use AsceticSoft\Wirebox\Exception\CircularDependencyException;
use AsceticSoft\Wirebox\Exception\ContainerException;
use AsceticSoft\Wirebox\Tests\Fixtures\CircularA;
use AsceticSoft\Wirebox\Tests\Fixtures\CircularB;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\AsScheduled;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\CleanupTask;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\CommandHandlerInterface;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\CreateUserHandler;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\DailyReportTask;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\DeleteUserHandler;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\EventListenerInterface;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\GetUserHandler;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\OrderCreatedListener;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\PlainService;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\QueryHandlerInterface;
use AsceticSoft\Wirebox\Tests\FixturesAutoconfigure\UserCreatedListener;
use AsceticSoft\Wirebox\Tests\Fixtures\DatabaseLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\EagerService;
use AsceticSoft\Wirebox\Tests\Fixtures\ExcludedService;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LazyService;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\SomeInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDeps;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithInject;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithParam;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use AsceticSoft\Wirebox\Tests\Fixtures\TransientService;
use AsceticSoft\Wirebox\Tests\FixturesAmbiguous\PaymentInterface;
use AsceticSoft\Wirebox\Tests\FixturesAmbiguous\StripePayment;
use PHPUnit\Framework\TestCase;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\ConcreteClass;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\Sub\SubConcreteClass;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\BogusNamespace\NonLoadable;

final class ContainerBuilderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/wirebox_builder_test_' . \uniqid();
        \mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $files = \glob($this->tmpDir . '/{,.}*', \GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
        if (\is_dir($this->tmpDir)) {
            \rmdir($this->tmpDir);
        }
    }

    public function testScanRegistersConcreteClasses(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures/Scan');

        $definitions = $builder->getDefinitions();

        self::assertArrayHasKey(ConcreteClass::class, $definitions);
        self::assertArrayHasKey(SubConcreteClass::class, $definitions);
    }

    public function testScanSkipsExcludedAttribute(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures');

        $definitions = $builder->getDefinitions();

        self::assertArrayNotHasKey(ExcludedService::class, $definitions);
    }

    public function testScanReadsTransientAttribute(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures');
        $builder->bind(LoggerInterface::class, FileLogger::class);

        $container = $builder->build();

        $a = $container->get(TransientService::class);
        $b = $container->get(TransientService::class);

        self::assertNotSame($a, $b);
    }

    public function testScanReadsTagAttribute(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures');
        $builder->bind(LoggerInterface::class, FileLogger::class);

        $container = $builder->build();

        $loggers = \iterator_to_array($container->getTagged('logger'));

        self::assertNotEmpty($loggers);
    }

    public function testExcludePattern(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->exclude('Sub/*');
        $builder->scan(__DIR__ . '/../Fixtures/Scan');

        $definitions = $builder->getDefinitions();

        self::assertArrayNotHasKey(
            SubConcreteClass::class,
            $definitions,
        );
    }

    public function testBindInterfaceToImplementation(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->bind(LoggerInterface::class, FileLogger::class);

        $container = $builder->build();

        $logger = $container->get(LoggerInterface::class);

        self::assertInstanceOf(FileLogger::class, $logger);
    }

    public function testRegisterWithFactory(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(SimpleService::class, fn () => new SimpleService());

        $container = $builder->build();

        self::assertInstanceOf(SimpleService::class, $container->get(SimpleService::class));
    }

    public function testParameterWithEnvExpression(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "DB_HOST=myhost\n");

        $builder = new ContainerBuilder($this->tmpDir);
        $builder->parameter('db.host', '%env(DB_HOST)%');

        $container = $builder->build();

        self::assertSame('myhost', $container->getParameter('db.host'));
    }

    public function testParameterWithCasting(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "PORT=3306\nDEBUG=true\n");

        $builder = new ContainerBuilder($this->tmpDir);
        $builder->parameter('port', '%env(int:PORT)%');
        $builder->parameter('debug', '%env(bool:DEBUG)%');

        $container = $builder->build();

        self::assertSame(3306, $container->getParameter('port'));
        self::assertTrue($container->getParameter('debug'));
    }

    public function testFullIntegrationWithScanAndParams(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "DB_HOST=testhost\nAPP_DEBUG=false\n");

        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures');
        $builder->bind(LoggerInterface::class, FileLogger::class);
        $builder->parameter('DB_HOST', '%env(DB_HOST)%');
        $builder->parameter('APP_DEBUG', '%env(bool:APP_DEBUG)%');

        $container = $builder->build();

        // Autowired service with dependencies
        $service = $container->get(ServiceWithDeps::class);
        self::assertInstanceOf(ServiceWithDeps::class, $service);

        // Service with #[Inject] attribute
        /** @var ServiceWithInject $injected */
        $injected = $container->get(ServiceWithInject::class);
        self::assertInstanceOf(FileLogger::class, $injected->logger);

        // Service with #[Param] attribute
        /** @var ServiceWithParam $paramService */
        $paramService = $container->get(ServiceWithParam::class);
        self::assertSame('testhost', $paramService->dbHost);
        self::assertFalse($paramService->debug);
    }

    public function testRegisterReturnsExistingDefinition(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        $first = $builder->register(SimpleService::class);
        $first->tag('first_tag');

        $second = $builder->register(SimpleService::class);

        // Should return the same definition
        self::assertSame($first, $second);
        self::assertContains('first_tag', $second->getTags());
    }

    public function testRegisterExistingWithFactoryUpdatesFactory(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        $builder->register(SimpleService::class);
        $factory = fn () => new SimpleService();
        $definition = $builder->register(SimpleService::class, $factory);

        self::assertSame($factory, $definition->getFactory());
    }

    public function testParameterWithPlainValue(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->parameter('app.name', 'Wirebox');

        $container = $builder->build();

        self::assertSame('Wirebox', $container->getParameter('app.name'));
    }

    public function testScanAutoBindsInterfaceWithSingleImplementation(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures/Scan');

        $bindings = $builder->getBindings();

        // SomeInterface has only one implementation (ConcreteClass), so it should be auto-bound
        self::assertArrayHasKey(SomeInterface::class, $bindings);
        self::assertSame(
            ConcreteClass::class,
            $bindings[SomeInterface::class],
        );
    }

    /**
     * When two classes implement the same interface without an explicit
     * bind(), build() must throw a ContainerException.
     */
    public function testBuildThrowsOnAmbiguousAutoBinding(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../FixturesAmbiguous');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Ambiguous auto-binding.*PaymentInterface/');

        $builder->build();
    }

    /**
     * When three or more classes implement the same interface,
     * all implementations must appear in the ambiguous binding error.
     */
    public function testBuildThrowsOnAmbiguousAutoBindingWithThreeImplementations(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../FixturesAmbiguous');

        try {
            $builder->build();
            self::fail('Expected ContainerException was not thrown');
        } catch (ContainerException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('PaymentInterface', $message);
            self::assertStringContainsString('PayPalPayment', $message);
            self::assertStringContainsString('StripePayment', $message);
            self::assertStringContainsString('BitcoinPayment', $message);
        }
    }

    /**
     * When two classes implement the same interface but an explicit
     * bind() is provided, the ambiguity is resolved and build() succeeds.
     */
    public function testExplicitBindResolvesAmbiguousAutoBinding(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../FixturesAmbiguous');
        $builder->bind(
            PaymentInterface::class,
            StripePayment::class,
        );

        $container = $builder->build();

        $payment = $container->get(PaymentInterface::class);
        self::assertInstanceOf(StripePayment::class, $payment);
    }

    public function testExplicitBindOverridesAutoBind(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures');
        $builder->bind(LoggerInterface::class, DatabaseLogger::class);

        $bindings = $builder->getBindings();

        self::assertSame(DatabaseLogger::class, $bindings[LoggerInterface::class]);
    }

    public function testScanSkipsAlreadyRegistered(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        // Register SimpleService explicitly first
        $builder->register(SimpleService::class)->transient();

        // Now scan — should NOT overwrite the existing definition
        $builder->scan(__DIR__ . '/../Fixtures');

        $definitions = $builder->getDefinitions();
        self::assertFalse($definitions[SimpleService::class]->isSingleton());
    }

    public function testCompileCreatesFile(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "DB_HOST=testhost\n");

        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(SimpleService::class);
        $builder->parameter('db.host', '%env(DB_HOST)%');

        $outputPath = $this->tmpDir . '/CompiledTest.php';
        $builder->compile($outputPath, 'CompiledTest');

        self::assertFileExists($outputPath);

        $content = (string) \file_get_contents($outputPath);
        self::assertStringContainsString('class CompiledTest', $content);
    }

    public function testExcludeReturnsSelfForFluent(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        $result = $builder->exclude('*.test.php');

        self::assertSame($builder, $result);
    }

    public function testBindReturnsSelfForFluent(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        $result = $builder->bind(LoggerInterface::class, FileLogger::class);

        self::assertSame($builder, $result);
    }

    public function testParameterReturnsSelfForFluent(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        $result = $builder->parameter('key', 'value');

        self::assertSame($builder, $result);
    }

    public function testScanReturnsSelfForFluent(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        $result = $builder->scan(__DIR__ . '/../Fixtures/Scan');

        self::assertSame($builder, $result);
    }

    public function testScanReadsLazyAttribute(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures');
        $builder->bind(LoggerInterface::class, FileLogger::class);

        $definitions = $builder->getDefinitions();

        self::assertTrue($definitions[LazyService::class]->isLazy());
    }

    public function testScanLazyServiceResolvesAsProxy(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures');
        $builder->bind(LoggerInterface::class, FileLogger::class);

        $container = $builder->build();

        $service = $container->get(LazyService::class);

        self::assertInstanceOf(LazyService::class, $service);

        $ref = new \ReflectionClass(LazyService::class);
        self::assertTrue($ref->isUninitializedLazyObject($service));
    }

    public function testScanSkipsNonLoadableClass(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures/Scan');

        $definitions = $builder->getDefinitions();

        // NonLoadable.php has a wrong namespace (BogusNamespace), so class_exists() returns false
        // and it should be skipped
        self::assertArrayNotHasKey(
            NonLoadable::class,
            $definitions,
        );
    }

    // --- Autoconfiguration tests ---

    public function testRegisterForAutoconfigurationReturnsSameRule(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        $rule1 = $builder->registerForAutoconfiguration(EventListenerInterface::class);
        $rule2 = $builder->registerForAutoconfiguration(EventListenerInterface::class);

        self::assertSame($rule1, $rule2);
        self::assertInstanceOf(AutoconfigureRule::class, $rule1);
    }

    public function testProgrammaticAutoconfigurationByInterface(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        // Add an extra tag programmatically on top of the declarative #[AutoconfigureTag('event.listener')]
        $builder->registerForAutoconfiguration(EventListenerInterface::class)
            ->tag('extra.listener.tag');

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $container = $builder->build();

        // Declarative tag from #[AutoconfigureTag] on the interface
        $listeners = \iterator_to_array($container->getTagged('event.listener'));
        self::assertCount(2, $listeners);

        $classes = \array_map(fn ($obj) => $obj::class, $listeners);
        self::assertContains(UserCreatedListener::class, $classes);
        self::assertContains(OrderCreatedListener::class, $classes);

        // Programmatic tag added via registerForAutoconfiguration()
        $extraListeners = \iterator_to_array($container->getTagged('extra.listener.tag'));
        self::assertCount(2, $extraListeners);
    }

    public function testProgrammaticAutoconfigurationByAttribute(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->registerForAutoconfiguration(AsScheduled::class)
            ->tag('programmatic.scheduled');

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $container = $builder->build();

        $tasks = \iterator_to_array($container->getTagged('programmatic.scheduled'));

        self::assertCount(2, $tasks);

        $classes = \array_map(fn ($obj) => $obj::class, $tasks);
        self::assertContains(DailyReportTask::class, $classes);
        self::assertContains(CleanupTask::class, $classes);
    }

    public function testDeclarativeAutoconfigureTagOnInterface(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        // No explicit registerForAutoconfiguration — the interface has #[AutoconfigureTag]
        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $container = $builder->build();

        $handlers = \iterator_to_array($container->getTagged('command.handler'));

        self::assertCount(2, $handlers);

        $classes = \array_map(fn ($obj) => $obj::class, $handlers);
        self::assertContains(CreateUserHandler::class, $classes);
        self::assertContains(DeleteUserHandler::class, $classes);
    }

    public function testDeclarativeAutoconfigureTagOnCustomAttribute(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        // No explicit registerForAutoconfiguration — the #[AsScheduled] attribute has #[AutoconfigureTag]
        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $container = $builder->build();

        $tasks = \iterator_to_array($container->getTagged('scheduler.task'));

        self::assertCount(2, $tasks);

        $classes = \array_map(fn ($obj) => $obj::class, $tasks);
        self::assertContains(DailyReportTask::class, $classes);
        self::assertContains(CleanupTask::class, $classes);
    }

    public function testAutoconfigurationDoesNotAffectPlainServices(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->registerForAutoconfiguration(EventListenerInterface::class)
            ->tag('event.listener');

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $definitions = $builder->getDefinitions();

        // PlainService should have no tags
        self::assertArrayHasKey(PlainService::class, $definitions);
        self::assertEmpty($definitions[PlainService::class]->getTags());
    }

    public function testProgrammaticAutoconfigurationAppliesLifetime(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->registerForAutoconfiguration(EventListenerInterface::class)
            ->tag('event.listener')
            ->transient();

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $container = $builder->build();

        $a = $container->get(UserCreatedListener::class);
        $b = $container->get(UserCreatedListener::class);

        self::assertNotSame($a, $b);
    }

    public function testProgrammaticAutoconfigurationAppliesSingleton(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->registerForAutoconfiguration(EventListenerInterface::class)
            ->singleton();

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $container = $builder->build();

        $a = $container->get(UserCreatedListener::class);
        $b = $container->get(UserCreatedListener::class);

        self::assertSame($a, $b);
    }

    public function testProgrammaticAutoconfigurationAppliesLazy(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->registerForAutoconfiguration(EventListenerInterface::class)
            ->lazy();

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $definitions = $builder->getDefinitions();

        self::assertTrue($definitions[UserCreatedListener::class]->isLazy());
        self::assertTrue($definitions[OrderCreatedListener::class]->isLazy());
    }

    /**
     * CQRS-style end-to-end test: command and query handlers
     * are auto-tagged via #[AutoconfigureTag] on their interfaces.
     */
    public function testCqrsHandlersAutoTagged(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $container = $builder->build();

        // Command handlers
        $commandHandlers = \iterator_to_array($container->getTagged('command.handler'));
        self::assertCount(2, $commandHandlers);

        $commandClasses = \array_map(fn ($obj) => $obj::class, $commandHandlers);
        self::assertContains(CreateUserHandler::class, $commandClasses);
        self::assertContains(DeleteUserHandler::class, $commandClasses);

        // Query handlers
        $queryHandlers = \iterator_to_array($container->getTagged('query.handler'));
        self::assertCount(1, $queryHandlers);
        self::assertInstanceOf(GetUserHandler::class, $queryHandlers[0]);
    }

    public function testProgrammaticAndDeclarativeAutoconfigureCombine(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        // Programmatically add 'extra.tag' to all CommandHandlerInterface implementations
        $builder->registerForAutoconfiguration(CommandHandlerInterface::class)
            ->tag('extra.tag');

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $container = $builder->build();

        // Should have both 'command.handler' (from interface #[AutoconfigureTag])
        // and 'extra.tag' (from programmatic rule)
        $definitions = $builder->getDefinitions();
        $tags = $definitions[CreateUserHandler::class]->getTags();

        self::assertContains('command.handler', $tags);
        self::assertContains('extra.tag', $tags);

        // Verify via getTagged as well
        $extraTagged = \iterator_to_array($container->getTagged('extra.tag'));
        self::assertCount(2, $extraTagged);
    }

    /**
     * Autoconfigured interfaces (via #[AutoconfigureTag] or registerForAutoconfiguration())
     * must not trigger the ambiguous auto-binding error, even when multiple
     * implementations exist.
     */
    public function testAutoconfiguredInterfacesSkipAmbiguousBindingCheck(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        // CommandHandlerInterface has #[AutoconfigureTag] and 2 implementations — no error
        // EventListenerInterface is registered programmatically and has 2 implementations — no error
        $builder->registerForAutoconfiguration(EventListenerInterface::class)
            ->tag('event.listener');

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        // build() must succeed without explicit bind() for these interfaces
        $container = $builder->build();

        self::assertCount(2, \iterator_to_array($container->getTagged('command.handler')));
        self::assertCount(2, \iterator_to_array($container->getTagged('event.listener')));
    }

    /**
     * excludeFromAutoBinding() must suppress the ambiguous auto-binding
     * error for the given interface, even when multiple implementations exist.
     */
    public function testExcludeFromAutoBindingSkipsAmbiguousCheck(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->excludeFromAutoBinding(PaymentInterface::class);
        $builder->scan(__DIR__ . '/../FixturesAmbiguous');

        // build() must succeed without explicit bind() for PaymentInterface
        $container = $builder->build();

        self::assertInstanceOf(Container::class, $container);

        // PaymentInterface must NOT appear in bindings
        $bindings = $builder->getBindings();
        self::assertArrayNotHasKey(PaymentInterface::class, $bindings);
    }

    /**
     * excludeFromAutoBinding() must return $this for fluent chaining.
     */
    public function testExcludeFromAutoBindingReturnsSelfForFluent(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);

        $result = $builder->excludeFromAutoBinding(PaymentInterface::class);

        self::assertSame($builder, $result);
    }

    /**
     * excludeFromAutoBinding() must accept multiple interfaces at once.
     */
    public function testExcludeFromAutoBindingAcceptsMultipleInterfaces(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->excludeFromAutoBinding(
            PaymentInterface::class,
            EventListenerInterface::class,
        );

        $builder->scan(__DIR__ . '/../FixturesAmbiguous');
        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        // build() must succeed — both interfaces are excluded
        $container = $builder->build();

        self::assertInstanceOf(Container::class, $container);
    }

    /**
     * Built-in PHP interfaces (Throwable, Stringable, etc.) must not
     * trigger the ambiguous auto-binding error, even when multiple
     * classes implementing them are scanned.
     */
    public function testBuiltInPhpInterfacesSkipAmbiguousBindingCheck(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../FixturesInternalInterfaces');

        // build() must succeed — Throwable and Stringable should be ignored
        $container = $builder->build();

        $bindings = $builder->getBindings();

        // Built-in interfaces must NOT appear in bindings
        self::assertArrayNotHasKey(\Throwable::class, $bindings);
        self::assertArrayNotHasKey(\Stringable::class, $bindings);
    }

    // --- Circular dependency detection tests ---

    /**
     * Two eager services with a circular dependency must be detected at build time.
     */
    public function testBuildDetectsEagerCircularDependency(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->defaultLazy(false);
        $builder->register(CircularA::class)->eager();
        $builder->register(CircularB::class)->eager();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected/');
        $this->expectExceptionMessageMatches('/not lazy/');

        $builder->build();
    }

    /**
     * Two lazy singletons with a circular dependency are safe — build must succeed.
     */
    public function testBuildAllowsLazySingletonCircularDependency(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(CircularA::class)->lazy()->singleton();
        $builder->register(CircularB::class)->lazy()->singleton();

        $container = $builder->build();

        self::assertInstanceOf(Container::class, $container);
    }

    /**
     * Two lazy transient services with a circular dependency are unsafe —
     * proxy is not cached, leading to infinite recursion at runtime.
     */
    public function testBuildDetectsLazyTransientCircularDependency(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(CircularA::class)->lazy()->transient();
        $builder->register(CircularB::class)->lazy()->transient();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/not a singleton/');

        $builder->build();
    }

    /**
     * Mixed: one lazy singleton + one eager → unsafe cycle.
     */
    public function testBuildDetectsMixedLazyEagerCircularDependency(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(CircularA::class)->lazy()->singleton();
        $builder->register(CircularB::class)->eager()->singleton();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/CircularB.*not lazy/');

        $builder->build();
    }

    /**
     * Mixed: one lazy singleton + one lazy transient → unsafe cycle.
     */
    public function testBuildDetectsMixedSingletonTransientCircularDependency(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(CircularA::class)->lazy()->singleton();
        $builder->register(CircularB::class)->lazy()->transient();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/CircularB.*not a singleton/');

        $builder->build();
    }

    /**
     * The exception message must contain the full dependency chain.
     */
    public function testCircularDependencyExceptionContainsChain(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(CircularA::class)->eager();
        $builder->register(CircularB::class)->eager();

        try {
            $builder->build();
            self::fail('Expected CircularDependencyException');
        } catch (CircularDependencyException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('CircularA', $message);
            self::assertStringContainsString('CircularB', $message);
            self::assertStringContainsString('->', $message);
        }
    }

    /**
     * Compile must also detect unsafe circular dependencies.
     */
    public function testCompileDetectsEagerCircularDependency(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(CircularA::class)->eager();
        $builder->register(CircularB::class)->eager();

        $this->expectException(CircularDependencyException::class);

        $builder->compile($this->tmpDir . '/Compiled.php');
    }

    /**
     * No circular dependency → no exception, even with complex graphs.
     */
    public function testBuildSucceedsWithoutCircularDependency(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(SimpleService::class)->eager();
        $builder->register(ServiceWithDeps::class)->eager();

        $container = $builder->build();

        self::assertInstanceOf(Container::class, $container);
    }

    /**
     * Factory-based definitions are skipped (dependencies are opaque).
     */
    public function testBuildSkipsFactoryDefinitionsInCycleDetection(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        // CircularA depends on CircularB via constructor,
        // but CircularB is created by a factory — no static analysis possible
        $builder->register(CircularA::class)->eager();
        $factory = static fn (Container $c): CircularB => new CircularB(
            /** @phpstan-ignore argument.type */
            $c->get(CircularA::class),
        );
        $builder->register(CircularB::class, $factory)->eager();

        // Should NOT throw — factory deps are opaque
        $container = $builder->build();

        self::assertInstanceOf(Container::class, $container);
    }

    /**
     * Default-lazy builder makes circular singletons safe automatically.
     */
    public function testDefaultLazyMakesCircularSingletonsSafe(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->defaultLazy(true); // default, but explicit for clarity
        $builder->register(CircularA::class); // no explicit lazy/eager → will inherit default
        $builder->register(CircularB::class);

        // resolveDefaultLazy() sets lazy=true, both are singletons → safe
        $container = $builder->build();

        self::assertInstanceOf(Container::class, $container);
    }

    /**
     * Lazy singleton circular deps actually resolve correctly at runtime.
     */
    public function testLazySingletonCircularDepsResolveAtRuntime(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(CircularA::class)->lazy()->singleton();
        $builder->register(CircularB::class)->lazy()->singleton();

        $container = $builder->build();

        $a = $container->get(CircularA::class);
        self::assertInstanceOf(CircularA::class, $a);

        // Accessing $a->b triggers initialization of A, which gets proxy B
        self::assertInstanceOf(CircularB::class, $a->b);

        // Accessing $a->b->a triggers initialization of B, which gets proxy A from cache
        self::assertInstanceOf(CircularA::class, $a->b->a);

        // The proxy identity is preserved: $a->b->a is the same proxy as $a
        self::assertSame($a, $a->b->a);
    }

    public function testScanReadsEagerAttribute(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures');
        $builder->bind(LoggerInterface::class, FileLogger::class);

        $definitions = $builder->getDefinitions();

        self::assertFalse($definitions[EagerService::class]->isLazy());
    }

    public function testProgrammaticAutoconfigurationAppliesEager(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->registerForAutoconfiguration(EventListenerInterface::class)
            ->eager();

        $builder->scan(__DIR__ . '/../FixturesAutoconfigure');

        $definitions = $builder->getDefinitions();

        self::assertFalse($definitions[UserCreatedListener::class]->isLazy());
        self::assertFalse($definitions[OrderCreatedListener::class]->isLazy());
    }

    /**
     * Circular dependency through setter injection is detected at build time.
     *
     * SimpleService (method call) -> ServiceWithDeps (constructor) -> SimpleService
     */
    public function testBuildDetectsCircularDependencyThroughMethodCall(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->register(SimpleService::class)
            ->eager()
            ->call('setDeps', [ServiceWithDeps::class]);
        $builder->register(ServiceWithDeps::class)->eager();

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected/');

        $builder->build();
    }
}
