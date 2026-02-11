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

final class ContainerBuilderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/wirebox_builder_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/{,.}*', GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testScanRegistersConcreteClasses(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->scan(__DIR__ . '/../Fixtures/Scan');

        $definitions = $builder->getDefinitions();

        self::assertArrayHasKey('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\ConcreteClass', $definitions);
        self::assertArrayHasKey('AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\Sub\\SubConcreteClass', $definitions);
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

        $loggers = iterator_to_array($container->getTagged('logger'));

        self::assertNotEmpty($loggers);
    }

    public function testExcludePattern(): void
    {
        $builder = new ContainerBuilder($this->tmpDir);
        $builder->exclude('Sub/*');
        $builder->scan(__DIR__ . '/../Fixtures/Scan');

        $definitions = $builder->getDefinitions();

        self::assertArrayNotHasKey(
            'AsceticSoft\\Wirebox\\Tests\\Fixtures\\Scan\\Sub\\SubConcreteClass',
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
        $builder->register(SimpleService::class, fn() => new SimpleService());

        $container = $builder->build();

        self::assertInstanceOf(SimpleService::class, $container->get(SimpleService::class));
    }

    public function testParameterWithEnvExpression(): void
    {
        file_put_contents($this->tmpDir . '/.env', "DB_HOST=myhost\n");

        $builder = new ContainerBuilder($this->tmpDir);
        $builder->parameter('db.host', '%env(DB_HOST)%');

        $container = $builder->build();

        self::assertSame('myhost', $container->getParameter('db.host'));
    }

    public function testParameterWithCasting(): void
    {
        file_put_contents($this->tmpDir . '/.env', "PORT=3306\nDEBUG=true\n");

        $builder = new ContainerBuilder($this->tmpDir);
        $builder->parameter('port', '%env(int:PORT)%');
        $builder->parameter('debug', '%env(bool:DEBUG)%');

        $container = $builder->build();

        self::assertSame(3306, $container->getParameter('port'));
        self::assertTrue($container->getParameter('debug'));
    }

    public function testFullIntegrationWithScanAndParams(): void
    {
        file_put_contents($this->tmpDir . '/.env', "DB_HOST=testhost\nAPP_DEBUG=false\n");

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
        $injected = $container->get(ServiceWithInject::class);
        self::assertInstanceOf(FileLogger::class, $injected->logger);

        // Service with #[Param] attribute
        $paramService = $container->get(ServiceWithParam::class);
        self::assertSame('testhost', $paramService->dbHost);
        self::assertFalse($paramService->debug);
    }
}
