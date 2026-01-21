<?php

namespace Tests\Unit;

use Atlas\Database\MySqlTypeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MySqlTypeNormalizerTest extends TestCase
{
    private MySqlTypeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MySqlTypeNormalizer();
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
            ['INT', 'int'],
            ['Int', 'int'],
            ['VARCHAR', 'varchar'],
            ['BIGINT UNSIGNED', 'bigint unsigned'],
            ['BigInt Unsigned', 'bigint unsigned'],
        ];
    }

    #[Test]
    #[DataProvider('displayWidthProvider')]
    public function testDisplayWidthRemoval(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function displayWidthProvider(): array
    {
        return [
            // Should remove display width
            ['int(11)', 'int'],
            ['INT(11)', 'int'],
            ['bigint(20)', 'bigint'],
            ['tinyint(4)', 'tinyint'],
            ['smallint(6)', 'smallint'],
            ['mediumint(9)', 'mediumint'],
            ['int(11) unsigned', 'int unsigned'],
            ['bigint(20) unsigned', 'bigint unsigned'],

            // Should NOT remove length
            ['varchar(255)', 'varchar(255)'],
            ['char(36)', 'char(36)'],
            ['decimal(10,2)', 'decimal(10,2)'],
            ['DECIMAL(10, 2)', 'decimal(10, 2)'],  // Note: also normalizes spacing
        ];
    }

    #[Test]
    #[DataProvider('booleanNormalizationProvider')]
    public function testBooleanNormalization(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function booleanNormalizationProvider(): array
    {
        return [
            ['tinyint(1)', 'tinyint'],
            ['TINYINT(1)', 'tinyint'],
            ['tinyint(1) unsigned', 'tinyint unsigned'],
            ['boolean', 'tinyint'],
            ['bool', 'tinyint'],
            ['BOOLEAN', 'tinyint'],
        ];
    }

    #[Test]
    #[DataProvider('aliasProvider')]
    public function testTypeAliases(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function aliasProvider(): array
    {
        return [
            ['integer', 'int'],
            ['INTEGER', 'int'],
            ['integer(11)', 'int'],
            ['integer unsigned', 'int unsigned'],
            ['INTEGER UNSIGNED', 'int unsigned'],
        ];
    }

    #[Test]
    #[DataProvider('unsignedModifierProvider')]
    public function testUnsignedModifier(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function unsignedModifierProvider(): array
    {
        return [
            ['int unsigned', 'int unsigned'],
            ['INT  UNSIGNED', 'int unsigned'],  // Multiple spaces
            ['bigint(20) unsigned', 'bigint unsigned'],
            ['BIGINT(20)  UNSIGNED', 'bigint unsigned'],
        ];
    }

    #[Test]
    #[DataProvider('stringTypesProvider')]
    public function testStringTypesPreserveLengths(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function stringTypesProvider(): array
    {
        return [
            ['varchar(255)', 'varchar(255)'],
            ['VARCHAR(100)', 'varchar(100)'],
            ['char(36)', 'char(36)'],
            ['CHAR(10)', 'char(10)'],
            ['text', 'text'],
            ['TEXT', 'text'],
            ['longtext', 'longtext'],
        ];
    }

    #[Test]
    #[DataProvider('decimalTypesProvider')]
    public function testDecimalTypesPreservePrecision(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function decimalTypesProvider(): array
    {
        return [
            ['decimal(10,2)', 'decimal(10,2)'],
            ['DECIMAL(10,2)', 'decimal(10,2)'],
            ['decimal(10, 2)', 'decimal(10, 2)'],  // Spaces after comma preserved
            ['DECIMAL(8, 4)', 'decimal(8, 4)'],
        ];
    }

    #[Test]
    #[DataProvider('enumTypesProvider')]
    public function testEnumTypesPreserveValues(string $input, string $expected): void
    {
        $this->assertEquals($expected, $this->normalizer->normalize($input));
    }

    public static function enumTypesProvider(): array
    {
        return [
            ["enum('a','b','c')", "enum('a','b','c')"],
            ["ENUM('draft','published')", "enum('draft','published')"],
            ["enum('active', 'inactive')", "enum('active', 'inactive')"],  // With spaces
        ];
    }

    #[Test]
    public function testWhitespaceNormalization(): void
    {
        $this->assertEquals(
            'bigint unsigned',
            $this->normalizer->normalize('  BIGINT   UNSIGNED  ')
        );
    }
}