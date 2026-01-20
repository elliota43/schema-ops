<?php

namespace SchemaOps\Discovery\Discovery;

class ClassFinder
{
    public function findInDirectory(string $directory): array
    {
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if (!str_contains($content, '#[Table') && !str_contains($content, 'SchemaOps\Attribute\Table')) {
                continue;
            }

            if ($class = $this->extractClassName($content)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function extractClassName(string $content): ?string
    {
        $tokens = token_get_all($content);
        $namespace = '';
        $class = '';

        // @TODO

        return $namespace ? $namespace . '\\' . $class : $class;
    }
}