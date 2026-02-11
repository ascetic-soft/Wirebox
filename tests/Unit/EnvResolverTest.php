<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Env\DotEnvParser;
use AsceticSoft\Wirebox\Env\EnvResolver;
use PHPUnit\Framework\TestCase;

final class EnvResolverTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/wirebox_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files (including dotfiles like .env)
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

    public function testParseDotEnvFile(): void
    {
        file_put_contents($this->tmpDir . '/.env', <<<'ENV'
APP_NAME=Wirebox
DB_HOST=localhost
DB_PORT=5432
# This is a comment
APP_DEBUG=true

QUOTED="hello world"
SINGLE='no interpolation'
ENV);

        $resolver = new EnvResolver($this->tmpDir);

        self::assertSame('Wirebox', $resolver->get('APP_NAME'));
        self::assertSame('localhost', $resolver->get('DB_HOST'));
        self::assertSame('5432', $resolver->get('DB_PORT'));
        self::assertSame('true', $resolver->get('APP_DEBUG'));
        self::assertSame('hello world', $resolver->get('QUOTED'));
        self::assertSame('no interpolation', $resolver->get('SINGLE'));
    }

    public function testDumpEnvOverridesDotEnv(): void
    {
        file_put_contents($this->tmpDir . '/.env', "DB_HOST=from_dotenv\n");
        file_put_contents($this->tmpDir . '/.env.local.php', "<?php\nreturn ['DB_HOST' => 'from_dump'];\n");

        $resolver = new EnvResolver($this->tmpDir);

        self::assertSame('from_dump', $resolver->get('DB_HOST'));
    }

    public function testResolveParameterExpression(): void
    {
        file_put_contents($this->tmpDir . '/.env', "DB_HOST=localhost\nAPP_DEBUG=true\nPORT=8080\nRATE=1.5\n");

        $resolver = new EnvResolver($this->tmpDir);

        // String
        self::assertSame('localhost', $resolver->resolveParameter('%env(DB_HOST)%'));

        // Bool cast
        self::assertTrue($resolver->resolveParameter('%env(bool:APP_DEBUG)%'));

        // Int cast
        self::assertSame(8080, $resolver->resolveParameter('%env(int:PORT)%'));

        // Float cast
        self::assertSame(1.5, $resolver->resolveParameter('%env(float:RATE)%'));

        // Embedded in string
        self::assertSame('host: localhost', $resolver->resolveParameter('host: %env(DB_HOST)%'));

        // Non-env value passes through
        self::assertSame('plain value', $resolver->resolveParameter('plain value'));
    }

    public function testNullForUndefinedVariable(): void
    {
        $resolver = new EnvResolver($this->tmpDir);

        self::assertNull($resolver->get('DOES_NOT_EXIST'));
    }

    public function testVariableInterpolation(): void
    {
        file_put_contents($this->tmpDir . '/.env', "BASE=/opt\nPATH_FULL=\"\${BASE}/app\"\n");

        $resolver = new EnvResolver($this->tmpDir);

        self::assertSame('/opt/app', $resolver->get('PATH_FULL'));
    }
}
