<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\Unit;

use AsceticSoft\Wirebox\Env\DotEnvParser;
use PHPUnit\Framework\TestCase;

final class DotEnvParserTest extends TestCase
{
    private DotEnvParser $parser;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->parser = new DotEnvParser();
        $this->tmpDir = \sys_get_temp_dir() . '/wirebox_dotenv_test_' . \uniqid();
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

    // --- parse() method ---

    public function testParseValidFile(): void
    {
        $path = $this->tmpDir . '/.env';
        \file_put_contents($path, "APP_NAME=Wirebox\nDB_HOST=localhost\n");

        $result = $this->parser->parse($path);

        self::assertSame(['APP_NAME' => 'Wirebox', 'DB_HOST' => 'localhost'], $result);
    }

    public function testParseReturnsEmptyForNonExistentFile(): void
    {
        $result = $this->parser->parse('/nonexistent/path/.env');

        self::assertSame([], $result);
    }

    public function testParseReturnsEmptyForDirectory(): void
    {
        $result = $this->parser->parse($this->tmpDir);

        self::assertSame([], $result);
    }

    // --- parseString() method ---

    public function testParseStringBasicKeyValue(): void
    {
        $result = $this->parser->parseString("KEY=value\nANOTHER=test");

        self::assertSame(['KEY' => 'value', 'ANOTHER' => 'test'], $result);
    }

    public function testParseStringSkipsEmptyLines(): void
    {
        $result = $this->parser->parseString("KEY1=val1\n\n\nKEY2=val2");

        self::assertSame(['KEY1' => 'val1', 'KEY2' => 'val2'], $result);
    }

    public function testParseStringSkipsComments(): void
    {
        $result = $this->parser->parseString("# This is a comment\nKEY=value\n# Another comment");

        self::assertSame(['KEY' => 'value'], $result);
    }

    public function testParseStringStripsExportPrefix(): void
    {
        $result = $this->parser->parseString("export DB_HOST=localhost\nexport DB_PORT=5432");

        self::assertSame(['DB_HOST' => 'localhost', 'DB_PORT' => '5432'], $result);
    }

    public function testParseStringSkipsLinesWithoutEquals(): void
    {
        $result = $this->parser->parseString("INVALID_LINE\nKEY=value");

        self::assertSame(['KEY' => 'value'], $result);
    }

    public function testParseStringSkipsEmptyKey(): void
    {
        $result = $this->parser->parseString("=empty_key_value\nKEY=value");

        self::assertSame(['KEY' => 'value'], $result);
    }

    public function testParseStringHandlesCrLfLineEndings(): void
    {
        $result = $this->parser->parseString("KEY1=val1\r\nKEY2=val2\r\n");

        self::assertSame(['KEY1' => 'val1', 'KEY2' => 'val2'], $result);
    }

    // --- Quoted values ---

    public function testParseStringDoubleQuotedValue(): void
    {
        $result = $this->parser->parseString('KEY="hello world"');

        self::assertSame(['KEY' => 'hello world'], $result);
    }

    public function testParseStringDoubleQuotedWithEscapeSequences(): void
    {
        $result = $this->parser->parseString('KEY="line1\nline2\ttab"');

        self::assertSame(['KEY' => "line1\nline2\ttab"], $result);
    }

    public function testParseStringSingleQuotedValueNoInterpolation(): void
    {
        $result = $this->parser->parseString("BASE=/opt\nKEY='\${BASE}/no-interpolation'");

        self::assertSame(['BASE' => '/opt', 'KEY' => '${BASE}/no-interpolation'], $result);
    }

    public function testParseStringSingleQuotedLiteral(): void
    {
        $result = $this->parser->parseString("KEY='hello world'");

        self::assertSame(['KEY' => 'hello world'], $result);
    }

    // --- Unquoted values ---

    public function testParseStringUnquotedWithInlineComment(): void
    {
        $result = $this->parser->parseString('KEY=value # this is a comment');

        self::assertSame(['KEY' => 'value'], $result);
    }

    public function testParseStringUnquotedTrimsWhitespace(): void
    {
        $result = $this->parser->parseString('  KEY  =  value  ');

        self::assertSame(['KEY' => 'value'], $result);
    }

    // --- Variable interpolation ---

    public function testParseStringInterpolateCurlyBraceSyntax(): void
    {
        $result = $this->parser->parseString("BASE=/opt\nFULL=\"\${BASE}/app\"");

        self::assertSame(['BASE' => '/opt', 'FULL' => '/opt/app'], $result);
    }

    public function testParseStringInterpolateDollarSignSyntax(): void
    {
        $result = $this->parser->parseString("BASE=/opt\nFULL=\"\$BASE/app\"");

        self::assertSame(['BASE' => '/opt', 'FULL' => '/opt/app'], $result);
    }

    public function testParseStringInterpolateUnquotedValue(): void
    {
        $result = $this->parser->parseString("HOST=localhost\nURL=\${HOST}:8080");

        self::assertSame(['HOST' => 'localhost', 'URL' => 'localhost:8080'], $result);
    }

    public function testParseStringInterpolateMultipleVariables(): void
    {
        $result = $this->parser->parseString("HOST=localhost\nPORT=5432\nURL=\"\${HOST}:\${PORT}\"");

        self::assertSame([
            'HOST' => 'localhost',
            'PORT' => '5432',
            'URL' => 'localhost:5432',
        ], $result);
    }

    public function testParseStringUndefinedVariableResolvesToEmpty(): void
    {
        $result = $this->parser->parseString('KEY=${UNDEFINED}');

        self::assertSame(['KEY' => ''], $result);
    }

    public function testParseStringEmptyValue(): void
    {
        $result = $this->parser->parseString('KEY=');

        self::assertSame(['KEY' => ''], $result);
    }

    public function testParseStringEmptyQuotedValues(): void
    {
        $result = $this->parser->parseString("KEY1=\"\"\nKEY2=''");

        self::assertSame(['KEY1' => '', 'KEY2' => ''], $result);
    }

    public function testParseStringComplexExample(): void
    {
        $content = <<<'ENV'
            # Application settings
            APP_NAME=Wirebox
            APP_ENV=production
            APP_DEBUG=false

            # Database
            export DB_HOST=localhost
            export DB_PORT=5432
            DB_NAME="my_database"
            DB_URL="${DB_HOST}:${DB_PORT}/${DB_NAME}"

            # Secrets
            SECRET_KEY='s3cr3t!@#$%'
            ENV;

        $result = $this->parser->parseString($content);

        self::assertSame('Wirebox', $result['APP_NAME']);
        self::assertSame('production', $result['APP_ENV']);
        self::assertSame('false', $result['APP_DEBUG']);
        self::assertSame('localhost', $result['DB_HOST']);
        self::assertSame('5432', $result['DB_PORT']);
        self::assertSame('my_database', $result['DB_NAME']);
        self::assertSame('localhost:5432/my_database', $result['DB_URL']);
        self::assertSame('s3cr3t!@#$%', $result['SECRET_KEY']);
    }

    public function testParseStringValueWithEqualsSign(): void
    {
        $result = $this->parser->parseString('KEY=value=with=equals');

        self::assertSame(['KEY' => 'value=with=equals'], $result);
    }

    public function testLookupVarFromEnvSuperglobal(): void
    {
        $originalEnv = $_ENV['WIREBOX_PARSER_EXT'] ?? null;
        $_ENV['WIREBOX_PARSER_EXT'] = 'from_env';

        try {
            $result = $this->parser->parseString('KEY=${WIREBOX_PARSER_EXT}');

            self::assertSame(['KEY' => 'from_env'], $result);
        } finally {
            if ($originalEnv === null) {
                unset($_ENV['WIREBOX_PARSER_EXT']);
            } else {
                $_ENV['WIREBOX_PARSER_EXT'] = $originalEnv;
            }
        }
    }
}
