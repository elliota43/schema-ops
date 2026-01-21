<?php

declare(strict_types=1);

namespace Atlas\Support;

class ProjectRootFinder
{
    /**
     * Find the project root by locating composer.json.
     * 
     * Traverses up the directory tree from the given path until it finds
     * a composer.json file that is not inside a vendor directory.
     * 
     * @param string|null $startPath The path to start searching from (defaults to current working directory)
     * @return string|null The absolute path to the project root, or null if not found
     */
    public static function find(?string $startPath = null): ?string
    {
        $currentPath = $startPath ?? getcwd();
        
        if (! $currentPath || ! is_dir($currentPath)) {
            return null;
        }
        
        $currentPath = realpath($currentPath);
        $maxIterations = 50;
        $iteration = 0;

        while ($currentPath !== '/' && $iteration < $maxIterations) {
            if (self::isInsideVendor($currentPath)) {
                $currentPath = self::moveUpOneDirectory($currentPath);
                $iteration++;
                continue;
            }

            $composerPath = $currentPath . '/composer.json';
            
            if (file_exists($composerPath)) {
                return $currentPath;
            }
            
            $currentPath = self::moveUpOneDirectory($currentPath);
            $iteration++;
        }

        return null;
    }

    /**
     * Check if the current path is inside a vendor directory.
     */
    protected static function isInsideVendor(string $path): bool
    {
        return str_contains($path, '/vendor/') || str_ends_with($path, '/vendor');
    }

    /**
     * Move up one directory level.
     */
    protected static function moveUpOneDirectory(string $path): string
    {
        return dirname($path);
    }
}
