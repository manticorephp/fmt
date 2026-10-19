<?php

declare(strict_types=1);

namespace Fmt;

/** A rule that rewrites the bytes of tokens of given kinds and nothing else (priority per rule, `fixText` per token). */
abstract class TextRule extends GapRule
{
    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_TEXT;
    }

    public function usesText(Source $s): bool
    {
        return true;
    }
}
