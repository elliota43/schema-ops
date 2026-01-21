<?php

namespace Atlas\Schema\Discovery;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ClassFinder
{
    /**
     * Finds all classes with the Table attribute.
     * @param string $directory
     * @return array
     */
    public function findInDirectory(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $classes = [];

        foreach ($this->phpFiles($directory) as $file) {
            if (! $this->hasTableAttribute($file)) {
                continue;
            }

            if ($className = $this->extractClassNameFromFile($file)) {
                $classes[] = $className;
            }
        }

        sort($classes);
        return $classes;
    }

    /**
     * Get all PHP files in a directory recursively.
     *
     * @param string $directory
     * @return array
     */
    protected function phpFiles(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($this->isPhpFile($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Checks if a file is a PHP file.
     * @param SplFileInfo $file
     * @return bool
     */
    protected function isPhpFile(SplFileInfo $file): bool
    {
        return $file->isFile() && $file->getExtension() === 'php';
    }

    /**
     * Checks if file contains a class with a Table attribute.
     *
     * @param SplFileInfo $file
     * @return bool
     */
    protected function hasTableAttribute(SplFileInfo $file): bool
    {
        $tokens = $this->tokenize($file);

        return $this->hasAttributeBeforeClass($tokens, 'Table');
    }

    /**
     * Tokenizes a PHP file.
     * @param SplFileInfo $file
     * @return array
     */
    protected function tokenize(SplFileInfo $file): array
    {
        return token_get_all(file_get_contents($file->getPathname()));
    }

    /**
     * Checks if token contains a specific attribute before a class.
     *
     * @param array $tokens
     * @param string $attributeName
     * @return bool
     */
    protected function hasAttributeBeforeClass(array $tokens, string $attributeName): bool
    {
        $sawAttribute = false;

        foreach ($tokens as $i => $token) {
            if ($this->isAttributeToken($token) && $this->isTargetAttribute($tokens, $i, $attributeName)) {
                $sawAttribute = true;
            }

            if ($this->isClassToken($token)) {
                if ($sawAttribute) {
                    return true;
                }

                $sawAttribute = false;
            }
        }

        return false;
    }

    /**
     * Checks if a token is an attribute (#[).
     * @param mixed $token
     * @return bool
     */
    protected function isAttributeToken(mixed $token): bool
    {
        return is_array($token) && $token[0] === T_ATTRIBUTE;
    }

    /**
     * Checks if token is a class declaration.
     *
     * @param mixed $token
     * @return bool
     */
    protected function isClassToken(mixed $token): bool
    {
        return is_array($token) && $token[0] === T_CLASS;
    }

    /**
     * Checks if the attribute matches the target name.
     *
     * @param array $tokens
     * @param int $position
     * @param string $targetName
     * @return bool
     */
    protected function isTargetAttribute(array $tokens, int $position, string $targetName): bool
    {
        $attributeName = $this->getAttributeName($tokens, $position);

        if (! $attributeName) {
            return false;
        }

        return strcasecmp($attributeName, $targetName) === 0
            || str_ends_with($attributeName, "\\{$targetName}");
    }

    /**
     * Extract the attribute name from tokens starting at a position.
     * @param array $tokens
     * @param int $start
     * @return string|null
     */
    protected function getAttributeName(array $tokens, int $start): ?string
    {
        for ($i = $start + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($this->isStringToken($token)) {
                return $token[1];
            }

            if ($this->isClosingBracket($token)) {
                break;
            }
        }

        return null;
    }

    /**
     * Checks if a token is a string/name.
     *
     * @param mixed $token
     * @return bool
     */
    protected function isStringToken(mixed $token): bool
    {
        return is_array($token) && $token[0] === T_STRING;
    }

    /**
     * Checks if a token is a closing bracket.
     * @param mixed $token
     * @return bool
     */
    protected function isClosingBracket(mixed $token): bool
    {
        return $token === ']';
    }

    /**
     * Extract the fully qualified class name from a file.
     *
     * @param SplFileInfo $file
     * @return string|null
     */
    protected function extractClassNameFromFile(SplFileInfo $file): ?string
    {
        $content = file_get_contents($file->getPathname());

        return $this->extractClassName($content);
    }

    /**
     * Extracts the fully qualified classname from PHP content.
     * @param string $content
     * @return string|null
     */
    protected function extractClassName(string $content): ?string
    {
        $tokens = token_get_all($content);

        $namespace = $this->extractNamespace($tokens);
        $class = $this->extractClass($tokens);

        if (! $class) {
            return null;
        }

        return $namespace ? "{$namespace}\\{$class}" : $class;
    }

    /**
     * Extracts namespace from tokens
     *
     * @param array $tokens
     * @return string
     */
    protected function extractNamespace(array $tokens): string
    {
        foreach ($tokens as $i => $token) {
            if (! is_array($token) || $token[0] !== T_NAMESPACE) {
                continue;
            }

            return $this->getNamespaceValue($tokens, $i);
        }

        return '';
    }

    /**
     * Get the namespace value starting from position.
     * @param array $tokens
     * @param int $start
     * @return string
     */
    protected function getNamespaceValue(array $tokens, int $start): string
    {
        for ($i = $start + 1; $i < count($tokens); $i++) {
            if (! is_array($tokens[$i])) {
                break;
            }

            if ($tokens[$i][0] === T_NAME_QUALIFIED || $tokens[$i][0] === T_STRING) {
                return $tokens[$i][1];
            }
        }

        return '';
    }

    /**
     * Extract class name from tokens.
     *
     * @param array $tokens
     * @return string
     */
    protected function extractClass(array $tokens): string
    {
        foreach ($tokens as $i => $token) {
            if (! is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            $className = $this->getClassValue($tokens, $i);

            if ($className) {
                return $className;
            }
        }

        return '';
    }

    /**
     * Get the class name value starting from a position(int).
     *
     * @param array $tokens
     * @param int $start
     * @return string|null
     */
    protected function getClassValue(array $tokens, int $start): ?string
    {
        for ($i = $start + 1; $i < count($tokens); $i++) {
            $nextToken = $tokens[$i];

            if (is_array($nextToken) && $nextToken[0] === T_WHITESPACE) {
                continue;
            }

            if ($nextToken === '{') {
                return null;
            }

            if (is_array($nextToken) && $nextToken[0] === T_STRING) {
                return $nextToken[1];
            }
        }

        return null;
    }
}