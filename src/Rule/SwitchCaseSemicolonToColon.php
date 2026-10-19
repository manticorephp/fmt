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
    /** @var array<int, bool> */
    private array $colons = [];

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
        $this->colons = $s->switchColons(); // before the first kind change drops the Source's copy
        return $this->colons !== [];
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [59];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== 59 || !isset($this->colons[$i])) {
            return null;
        }
        $s->setKind($i, 58);
        return ':';
    }
}
