<?php

namespace Atlas\Schema\Discovery;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class YamlSchemaFinder
{
    /**
     * Find all YAML schema files in the given directory.
     * 
     * @param string $directory The root directory to search.
     * @param string $pattern The file pattern to match (default: *.schema.yaml)
     * @return array<string> Array of absolute file paths
     */
    public function findInDirectory(string $directory, string $pattern = '*.schema.yaml'): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $this->matchesPattern($file->getFilename(), $pattern)) {
                $files[] = $file->getRealPath();
            }
        }
        
        sort($files);
        return $files;
    }

    /**
     * Check if a filename matches the given pattern.
     * 
     * @param string $filename The file to check.
     * @param string $pattern The pattern to check if the file matches.
     * @return bool 
     */
    private function matchesPattern(string $filename, string $pattern)
    {
        $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($regex, $filename);
    }
}