<?php

namespace Atlas\Database;

interface TypeNormalizerInterface
{
    /**
     * Normalizes a database-specific type string for comparison.
     *
     * @param string $type
     * @return string
     */
    public function normalize(string $type): string;
}