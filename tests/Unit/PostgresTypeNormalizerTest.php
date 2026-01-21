<?php

namespace Tests\Unit;

use Atlas\Database\Normalizers\PostgresTypeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PostgresTypeNormalizerTest extends TestCase
{
    private PostgresTypeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PostgresTypeNormalizer();
    }

    #[Test]
    #[DataProvider('caseInsensitivityProvider')]
    public function testCaseInsensitivity(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function caseInsensitivityProvider(): array
    {
        return [
            ['INTEGER', 'integer'],
            ['Int', 'integer'],
            ['VARCHAR', 'varchar'],
            ['BIGINT', 'bigint'],
            ['SMALLINT', 'smallint'],
            ['TEXT', 'text'],
            ['BOOLEAN', 'boolean'],
        ];
    }

    #[Test]
    #[DataProvider('integerTypeProvider')]
    public function testIntegerTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function integerTypeProvider(): array
    {
        return [
            // tinyint maps to smallint (PostgreSQL has no tinyint)
            ['tinyint', 'smallint'],
            ['TINYINT', 'smallint'],

            // smallint stays as smallint
            ['smallint', 'smallint'],
            ['SMALLINT', 'smallint'],

            // mediumint, int, integer all map to integer
            ['mediumint', 'integer'],
            ['int', 'integer'],
            ['integer', 'integer'],
            ['INT', 'integer'],
            ['INTEGER', 'integer'],

            // bigint stays as bigint
            ['bigint', 'bigint'],
            ['BIGINT', 'bigint'],
        ];
    }

    #[Test]
    #[DataProvider('stringTypeProvider')]
    public function testStringTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function stringTypeProvider(): array
    {
        return [
            // varchar with length
            ['varchar(255)', 'varchar(255)'],
            ['VARCHAR(100)', 'varchar(100)'],
            ['varchar(50)', 'varchar(50)'],

            // string maps to varchar
            ['string', 'varchar'],

            // text types all map to text
            ['text', 'text'],
            ['TEXT', 'text'],
            ['mediumtext', 'text'],
            ['longtext', 'text'],
            ['MEDIUMTEXT', 'text'],
            ['LONGTEXT', 'text'],

            // character varying (PostgreSQL's verbose form)
            ['character varying', 'varchar'],
        ];
    }

    #[Test]
    #[DataProvider('booleanTypeProvider')]
    public function testBooleanTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function booleanTypeProvider(): array
    {
        return [
            ['boolean', 'boolean'],
            ['BOOLEAN', 'boolean'],
            ['bool', 'boolean'],
            ['BOOL', 'boolean'],
        ];
    }

    #[Test]
    #[DataProvider('dateTimeTypeProvider')]
    public function testDateTimeTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function dateTimeTypeProvider(): array
    {
        return [
            // datetime maps to timestamp
            ['datetime', 'timestamp'],
            ['DATETIME', 'timestamp'],

            // timestamp stays as timestamp
            ['timestamp', 'timestamp'],
            ['TIMESTAMP', 'timestamp'],

            // date stays as date
            ['date', 'date'],
            ['DATE', 'date'],

            // time stays as time
            ['time', 'time'],
            ['TIME', 'time'],
        ];
    }

    #[Test]
    #[DataProvider('numericTypeProvider')]
    public function testNumericTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function numericTypeProvider(): array
    {
        return [
            // decimal and numeric are equivalent in PostgreSQL
            ['decimal', 'numeric'],
            ['DECIMAL', 'numeric'],
            ['numeric', 'numeric'],
            ['NUMERIC', 'numeric'],

            // With precision and scale
            ['decimal(10,2)', 'numeric(10,2)'],
            ['DECIMAL(10,2)', 'numeric(10,2)'],
            ['numeric(8,4)', 'numeric(8,4)'],
            ['NUMERIC(8,4)', 'numeric(8,4)'],

            // Float types
            ['float', 'real'],
            ['FLOAT', 'real'],
            ['double', 'double precision'],
            ['DOUBLE', 'double precision'],
        ];
    }

    #[Test]
    #[DataProvider('jsonTypeProvider')]
    public function testJsonTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function jsonTypeProvider(): array
    {
        return [
            // json maps to jsonb for better performance
            ['json', 'jsonb'],
            ['JSON', 'jsonb'],
        ];
    }

    #[Test]
    #[DataProvider('binaryTypeProvider')]
    public function testBinaryTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function binaryTypeProvider(): array
    {
        return [
            // binary types map to bytea
            ['binary', 'bytea'],
            ['BINARY', 'bytea'],
            ['blob', 'bytea'],
            ['BLOB', 'bytea'],
        ];
    }

    #[Test]
    #[DataProvider('uuidTypeProvider')]
    public function testUuidTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function uuidTypeProvider(): array
    {
        return [
            ['uuid', 'uuid'],
            ['UUID', 'uuid'],
        ];
    }

    #[Test]
    #[DataProvider('preserveLengthProvider')]
    public function testPreserveLength(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function preserveLengthProvider(): array
    {
        return [
            // varchar preserves length
            ['varchar(255)', 'varchar(255)'],
            ['VARCHAR(100)', 'varchar(100)'],
            ['varchar(1)', 'varchar(1)'],

            // char preserves length
            ['char(36)', 'character(36)'],
            ['CHAR(10)', 'character(10)'],

            // numeric preserves precision/scale
            ['numeric(10,2)', 'numeric(10,2)'],
            ['decimal(8,4)', 'numeric(8,4)'],
        ];
    }

    #[Test]
    #[DataProvider('whitespaceTrimProvider')]
    public function testWhitespaceTrimming(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function whitespaceTrimProvider(): array
    {
        return [
            ['  varchar  ', 'varchar'],
            ['  INTEGER  ', 'integer'],
            [' text ', 'text'],
            ['   boolean   ', 'boolean'],
        ];
    }

    #[Test]
    #[DataProvider('unknownTypeProvider')]
    public function testUnknownTypesPassThrough(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function unknownTypeProvider(): array
    {
        return [
            // Unknown types should pass through lowercased
            ['custom_type', 'custom_type'],
            ['CUSTOM_TYPE', 'custom_type'],
            ['point', 'point'],
            ['geometry', 'geometry'],
            ['inet', 'inet'],
            ['cidr', 'cidr'],
        ];
    }

    #[Test]
    public function testComplexNumericWithSpaces(): void
    {
        // Test decimal with spaces
        $result = $this->normalizer->normalize('decimal(10,2)');
        $this->assertEquals('numeric(10,2)', $result);
    }

    #[Test]
    public function testSerialTypesPassThrough(): void
    {
        // Serial types are aliases handled by PostgreSQL DDL, not type normalization
        $this->assertEquals('serial', $this->normalizer->normalize('serial'));
        $this->assertEquals('bigserial', $this->normalizer->normalize('bigserial'));
    }

    #[Test]
    public function testCharacterVaryingLongForm(): void
    {
        // Test PostgreSQL's verbose form
        $result = $this->normalizer->normalize('character varying(100)');
        $this->assertEquals('varchar(100)', $result);
    }

    #[Test]
    public function testCharacterLongForm(): void
    {
        // Test character instead of char
        $result = $this->normalizer->normalize('character(10)');
        $this->assertEquals('character(10)', $result);
    }

    #[Test]
    #[DataProvider('realWorldTypesProvider')]
    public function testRealWorldPostgresTypes(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function realWorldTypesProvider(): array
    {
        return [
            // Common PostgreSQL types as they appear in information_schema
            ['int4', 'integer'],
            ['int8', 'bigint'],
            ['int2', 'smallint'],
            ['varchar', 'varchar'],
            ['bpchar', 'bpchar'], // blank-padded char - passes through
            ['timestamptz', 'timestamptz'], // timestamp with time zone - passes through
            ['bool', 'boolean'],
        ];
    }
}
