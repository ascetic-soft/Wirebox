<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Env\EnvResolver;
use PHPUnit\Framework\TestCase;

final class EnvResolverTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/wirebox_test_' . \uniqid();
        \mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files (including dotfiles like .env)
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

    public function testParseDotEnvFile(): void
    {
        \file_put_contents($this->tmpDir . '/.env', <<<'ENV'
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
        \file_put_contents($this->tmpDir . '/.env', "DB_HOST=from_dotenv\n");
        \file_put_contents($this->tmpDir . '/.env.local.php', "<?php\nreturn ['DB_HOST' => 'from_dump'];\n");

        $resolver = new EnvResolver($this->tmpDir);

        self::assertSame('from_dump', $resolver->get('DB_HOST'));
    }

    public function testResolveParameterExpression(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "DB_HOST=localhost\nAPP_DEBUG=true\nPORT=8080\nRATE=1.5\n");

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
        \file_put_contents($this->tmpDir . '/.env', "BASE=/opt\nPATH_FULL=\"\${BASE}/app\"\n");

        $resolver = new EnvResolver($this->tmpDir);

        self::assertSame('/opt/app', $resolver->get('PATH_FULL'));
    }

    public function testAllReturnsAllResolvedVars(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "KEY1=val1\nKEY2=val2\n");

        $resolver = new EnvResolver($this->tmpDir);

        $all = $resolver->all();
        self::assertArrayHasKey('KEY1', $all);
        self::assertArrayHasKey('KEY2', $all);
        self::assertSame('val1', $all['KEY1']);
        self::assertSame('val2', $all['KEY2']);
    }

    public function testResolveParameterNonStringPassesThrough(): void
    {
        $resolver = new EnvResolver($this->tmpDir);

        self::assertSame(42, $resolver->resolveParameter(42));
        self::assertSame(3.14, $resolver->resolveParameter(3.14));
        self::assertTrue($resolver->resolveParameter(true));
        self::assertNull($resolver->resolveParameter(null));
        self::assertSame(['a', 'b'], $resolver->resolveParameter(['a', 'b']));
    }

    public function testResolveParameterStringCast(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "MY_VAR=hello\n");

        $resolver = new EnvResolver($this->tmpDir);

        self::assertSame('hello', $resolver->resolveParameter('%env(string:MY_VAR)%'));
    }

    public function testResolveParameterUndefinedVarThrows(): void
    {
        $resolver = new EnvResolver($this->tmpDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not defined/');

        $resolver->resolveParameter('%env(UNDEFINED_VAR)%');
    }

    public function testResolveParameterUndefinedCastVarThrows(): void
    {
        $resolver = new EnvResolver($this->tmpDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not defined/');

        $resolver->resolveParameter('%env(int:UNDEFINED_VAR)%');
    }

    public function testResolveParameterUnknownCastTypeThrows(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "MY_VAR=value\n");

        $resolver = new EnvResolver($this->tmpDir);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown env cast type/');

        $resolver->resolveParameter('%env(invalid:MY_VAR)%');
    }

    public function testResolveParameterFloatCast(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "RATE=3.14\n");

        $resolver = new EnvResolver($this->tmpDir);

        self::assertSame(3.14, $resolver->resolveParameter('%env(float:RATE)%'));
    }

    public function testResolveParameterEmbeddedCastInString(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "HOST=localhost\nPORT=3306\n");

        $resolver = new EnvResolver($this->tmpDir);

        $result = $resolver->resolveParameter('mysql://%env(HOST)%:%env(PORT)%/db');

        self::assertSame('mysql://localhost:3306/db', $result);
    }

    public function testEnsureLoadedOnlyRunsOnce(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "KEY=value\n");

        $resolver = new EnvResolver($this->tmpDir);

        // Call twice — second call should use cached data
        $first = $resolver->get('KEY');
        $second = $resolver->get('KEY');

        self::assertSame($first, $second);
        self::assertSame('value', $first);
    }

    public function testDumpEnvWithNonArrayReturnsBaseEnv(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "KEY=from_env\n");
        \file_put_contents($this->tmpDir . '/.env.local.php', "<?php\nreturn 'not an array';\n");

        $resolver = new EnvResolver($this->tmpDir);

        // Should still have the .env value since .env.local.php is not a valid array
        self::assertSame('from_env', $resolver->get('KEY'));
    }

    public function testNoDotEnvFileReturnsEmpty(): void
    {
        $resolver = new EnvResolver($this->tmpDir);

        self::assertNull($resolver->get('ANY_KEY'));
        self::assertSame([], $resolver->all());
    }

    public function testSystemEnvOverridesDotEnvViaEnvSuperglobal(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "WIREBOX_TEST_KEY=from_file\n");

        $originalEnv = $_ENV['WIREBOX_TEST_KEY'] ?? null;
        $_ENV['WIREBOX_TEST_KEY'] = 'from_system_env';

        try {
            $resolver = new EnvResolver($this->tmpDir);

            self::assertSame('from_system_env', $resolver->get('WIREBOX_TEST_KEY'));
        } finally {
            if ($originalEnv === null) {
                unset($_ENV['WIREBOX_TEST_KEY']);
            } else {
                $_ENV['WIREBOX_TEST_KEY'] = $originalEnv;
            }
        }
    }

    public function testSystemEnvOverridesDotEnvViaServerSuperglobal(): void
    {
        \file_put_contents($this->tmpDir . '/.env', "WIREBOX_TEST_SRV=from_file\n");

        $originalEnv = $_ENV['WIREBOX_TEST_SRV'] ?? null;
        $originalServer = $_SERVER['WIREBOX_TEST_SRV'] ?? null;

        // Make sure $_ENV does NOT have this key so we fall through to $_SERVER
        unset($_ENV['WIREBOX_TEST_SRV']);
        $_SERVER['WIREBOX_TEST_SRV'] = 'from_server';

        try {
            $resolver = new EnvResolver($this->tmpDir);

            self::assertSame('from_server', $resolver->get('WIREBOX_TEST_SRV'));
        } finally {
            if ($originalEnv !== null) {
                $_ENV['WIREBOX_TEST_SRV'] = $originalEnv;
            }
            if ($originalServer === null) {
                unset($_SERVER['WIREBOX_TEST_SRV']);
            } else {
                $_SERVER['WIREBOX_TEST_SRV'] = $originalServer;
            }
        }
    }
}
