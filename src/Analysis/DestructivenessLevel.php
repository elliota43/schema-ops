<?php

namespace Atlas\Analysis;

enum DestructivenessLevel
{
    case SAFE;
    case POTENTIALLY_DESTRUCTIVE;
    case DEFINITELY_DESTRUCTIVE;

    public function isSafe(): bool
    {
        return $this === self::SAFE;
    }

    public function isDestructive(): bool
    {
        return $this === self::DEFINITELY_DESTRUCTIVE;
    }

    public function requiresWarning(): bool
    {
        return $this !== self::SAFE;
    }
}