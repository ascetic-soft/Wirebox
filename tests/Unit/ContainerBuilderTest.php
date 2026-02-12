<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\ContainerBuilder;
use AsceticSoft\Wirebox\Tests\Fixtures\DatabaseLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\ExcludedService;
use AsceticSoft\Wirebox\Tests\Fixtures\FileLogger;
use AsceticSoft\Wirebox\Tests\Fixtures\LoggerInterface;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithDeps;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithInject;
use AsceticSoft\Wirebox\Tests\Fixtures\ServiceWithParam;
use AsceticSoft\Wirebox\Tests\Fixtures\SimpleService;
use AsceticSoft\Wirebox\Tests\Fixtures\TransientService;
use PHPUnit\Framework\TestCase;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\ConcreteClass;
use AsceticSoft\Wirebox\Tests\Fixtures\Scan\Sub\SubConcreteClass;

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
        self::assertArrayHasKey(\AsceticSoft\Wirebox\Tests\Fixtures\Scan\SomeInterface::class, $bindings);
        self::assertSame(
            \AsceticSoft\Wirebox\Tests\Fixtures\Scan\ConcreteClass::class,
            $bindings[\AsceticSoft\Wirebox\Tests\Fixtures\Scan\SomeInterface::class],
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

        $this->expectException(\AsceticSoft\Wirebox\Exception\ContainerException::class);
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
        } catch (\AsceticSoft\Wirebox\Exception\ContainerException $e) {
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
            \AsceticSoft\Wirebox\Tests\FixturesAmbiguous\PaymentInterface::class,
            \AsceticSoft\Wirebox\Tests\FixturesAmbiguous\StripePayment::class,
        );

        $container = $builder->build();

        $payment = $container->get(\AsceticSoft\Wirebox\Tests\FixturesAmbiguous\PaymentInterface::class);
        self::assertInstanceOf(\AsceticSoft\Wirebox\Tests\FixturesAmbiguous\StripePayment::class, $payment);
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

    public function testScanSkipsNonLoadableClass(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures/Scan');

        $definitions = $builder->getDefinitions();

        // NonLoadable.php has a wrong namespace (BogusNamespace), so class_exists() returns false
        // and it should be skipped
        self::assertArrayNotHasKey(
            'AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\BogusNamespace\\NonLoadable',
            $definitions,
        );
    }
}
