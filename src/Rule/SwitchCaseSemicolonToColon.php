<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\SwitchCaseSemicolonToColonFixer (priority 0). The `;` that closes a `case` /
 * `default` label of a switch becomes `:` (same token count, one byte). Text rule over the colons Analyzer::switchColons finds.
 */
final class SwitchCaseSemicolonToColon extends GapRule
{
    public function name(): string
    {
        return 'switch_case_semicolon_to_colon';
    }

    public function priority(): int
    {
        return 0;
    }

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
        return $s->switchColons() !== [];
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [59];
    }

    public function fixText(Source $s, int $i): ?string
    {
        return $s->kind[$i] === 59 && isset($s->switchColons()[$i]) ? ':' : null;
    }
}
