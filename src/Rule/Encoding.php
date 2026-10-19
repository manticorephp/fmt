<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\EncodingFixer (priority 100). A UTF-8 BOM is the first bytes of token 0 (inline HTML),
 * and upstream drops them: done on the raw file, before it is lexed (the rule is the first one upstream runs, so every other rule
 * sees the file without it).
 */
final class Encoding extends GapRule
{
    public function name(): string
    {
        return 'encoding';
    }

    public function priority(): int
    {
        return 100;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return 0;
    }

    public function writes(): int
    {
        return 0;
    }

    public function preParse(string $code): string
    {
        return \strncmp($code, "\xEF\xBB\xBF", 3) === 0 ? \substr($code, 3) : $code;
    }

    public function prepare(Source $s): bool
    {
        return false;
    }
}
