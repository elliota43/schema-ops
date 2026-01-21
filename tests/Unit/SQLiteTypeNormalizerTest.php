<?php

namespace Tests\Unit;

use Atlas\Database\Normalizers\SQLiteTypeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SQLiteTypeNormalizerTest extends TestCase
{
    private SQLiteTypeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new SQLiteTypeNormalizer();
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
            ['TEXT', 'text'],
            ['REAL', 'real'],
            ['BLOB', 'blob'],
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
            // All integer types map to 'integer' (INTEGER affinity)
            ['tinyint', 'integer'],
            ['TINYINT', 'integer'],
            ['smallint', 'integer'],
            ['mediumint', 'integer'],
            ['int', 'integer'],
            ['integer', 'integer'],
            ['INTEGER', 'integer'],
            ['bigint', 'integer'],
            ['BIGINT', 'integer'],

            // PostgreSQL-style int types
            ['int2', 'integer'],
            ['int4', 'integer'],
            ['int8', 'integer'],
        ];
    }

    #[Test]
    #[DataProvider('textTypeProvider')]
    public function testTextTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function textTypeProvider(): array
    {
        return [
            // VARCHAR and string types map to TEXT
            ['varchar', 'text'],
            ['VARCHAR', 'text'],
            ['varchar(255)', 'text(255)'],
            ['VARCHAR(100)', 'text(100)'],
            ['string', 'text'],

            // Character types
            ['char', 'text'],
            ['character', 'text'],
            ['character varying', 'text'],

            // Text types
            ['text', 'text'],
            ['TEXT', 'text'],
            ['mediumtext', 'text'],
            ['longtext', 'text'],
            ['clob', 'text'],
        ];
    }

    #[Test]
    #[DataProvider('realTypeProvider')]
    public function testRealTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function realTypeProvider(): array
    {
        return [
            ['real', 'real'],
            ['REAL', 'real'],
            ['double', 'real'],
            ['DOUBLE', 'real'],
            ['double precision', 'real'],
            ['DOUBLE PRECISION', 'real'],
            ['float', 'real'],
            ['FLOAT', 'real'],
            ['float4', 'real'],
            ['float8', 'real'],
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
            ['numeric', 'numeric'],
            ['NUMERIC', 'numeric'],
            ['decimal', 'numeric'],
            ['DECIMAL', 'numeric'],
            ['numeric(10,2)', 'numeric(10,2)'],
            ['decimal(8,4)', 'numeric(8,4)'],
        ];
    }

    #[Test]
    #[DataProvider('blobTypeProvider')]
    public function testBlobTypeNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function blobTypeProvider(): array
    {
        return [
            ['blob', 'blob'],
            ['BLOB', 'blob'],
            ['binary', 'blob'],
            ['BINARY', 'blob'],
            ['varbinary', 'blob'],
            ['bytea', 'blob'], // PostgreSQL binary type
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
            // Boolean stored as INTEGER in SQLite
            ['boolean', 'integer'],
            ['BOOLEAN', 'integer'],
            ['bool', 'integer'],
            ['BOOL', 'integer'],
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
            // SQLite stores dates as TEXT, REAL, or INTEGER
            // We normalize to TEXT for consistency
            ['date', 'text'],
            ['DATE', 'text'],
            ['datetime', 'text'],
            ['DATETIME', 'text'],
            ['timestamp', 'text'],
            ['TIMESTAMP', 'text'],
            ['time', 'text'],
            ['TIME', 'text'],
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
            // JSON stored as TEXT in SQLite
            ['json', 'text'],
            ['JSON', 'text'],
            ['jsonb', 'text'], // PostgreSQL JSONB
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
            // UUID stored as TEXT in SQLite
            ['uuid', 'text'],
            ['UUID', 'text'],
        ];
    }

    #[Test]
    #[DataProvider('lengthPreservationProvider')]
    public function testLengthPreservation(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function lengthPreservationProvider(): array
    {
        return [
            // TEXT types preserve length for documentation
            ['varchar(255)', 'text(255)'],
            ['VARCHAR(100)', 'text(100)'],
            ['text(1000)', 'text(1000)'],

            // NUMERIC preserves precision/scale
            ['numeric(10,2)', 'numeric(10,2)'],
            ['decimal(8,4)', 'numeric(8,4)'],

            // INTEGER doesn't preserve display width (like MySQL int(11))
            ['integer(11)', 'integer'],
            ['int(11)', 'integer'],
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
            ['  integer  ', 'integer'],
            ['  TEXT  ', 'text'],
            [' real ', 'real'],
            ['   blob   ', 'blob'],
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
            ['geometry', 'geometry'],
            ['point', 'point'],
        ];
    }

    #[Test]
    public function testComplexNumericWithSpaces(): void
    {
        // Test numeric with spaces in precision/scale
        $result = $this->normalizer->normalize('numeric(10, 2)');
        $this->assertEquals('numeric(10, 2)', $result);
    }

    #[Test]
    public function testMultiWordTypes(): void
    {
        // Test multi-word type names
        $this->assertEquals('real', $this->normalizer->normalize('double precision'));
        $this->assertEquals('real', $this->normalizer->normalize('DOUBLE PRECISION'));
        $this->assertEquals('text', $this->normalizer->normalize('character varying'));
    }

    #[Test]
    #[DataProvider('typeAffinityProvider')]
    public function testTypeAffinityConsistency(string $input, string $expected): void
    {
        // Verify that types with the same affinity normalize consistently
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function typeAffinityProvider(): array
    {
        return [
            // INTEGER affinity
            ['int', 'integer'],
            ['tinyint', 'integer'],
            ['bigint', 'integer'],

            // TEXT affinity
            ['varchar', 'text'],
            ['char', 'text'],
            ['text', 'text'],

            // REAL affinity
            ['real', 'real'],
            ['float', 'real'],
            ['double', 'real'],

            // NUMERIC affinity
            ['numeric', 'numeric'],
            ['decimal', 'numeric'],

            // BLOB affinity
            ['blob', 'blob'],
            ['binary', 'blob'],
        ];
    }

    #[Test]
    public function testCaseSensitivityInLengths(): void
    {
        // Ensure case normalization doesn't affect length values
        $this->assertEquals('text(255)', $this->normalizer->normalize('VARCHAR(255)'));
        $this->assertEquals('text(100)', $this->normalizer->normalize('CHAR(100)'));
    }

    #[Test]
    public function testEmptyType(): void
    {
        // SQLite allows typeless columns - they have BLOB affinity
        $this->assertEquals('', $this->normalizer->normalize(''));
    }

    #[Test]
    #[DataProvider('realWorldSQLiteTypesProvider')]
    public function testRealWorldSQLiteTypes(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function realWorldSQLiteTypesProvider(): array
    {
        return [
            // Common SQLite types as they appear in schemas
            ['INTEGER', 'integer'],
            ['TEXT', 'text'],
            ['REAL', 'real'],
            ['BLOB', 'blob'],
            ['NUMERIC', 'numeric'],

            // Common variations
            ['INT', 'integer'],
            ['VARCHAR(255)', 'text(255)'],
            ['CHAR(36)', 'text(36)'], // UUID as CHAR
            ['DATETIME', 'text'],
            ['BOOLEAN', 'integer'],
        ];
    }

    #[Test]
    public function testTypeWithoutLength(): void
    {
        // Ensure types without length don't add empty parentheses
        $this->assertEquals('text', $this->normalizer->normalize('text'));
        $this->assertEquals('integer', $this->normalizer->normalize('integer'));
        $this->assertEquals('real', $this->normalizer->normalize('real'));
        $this->assertEquals('blob', $this->normalizer->normalize('blob'));
    }
}
