<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\SwitchCaseSpaceFixer (priority 0). Per switch: a `default` followed by a blank and
 * `:` / `;` loses the blank; if it is NOT, upstream's `continue` skips that whole switch (its cases too). Every case colon behind a
 * whitespace-only gap loses the blank. The gaps are decided in prepare() from the switch analyses (neither comments nor kinds change).
 */
final class SwitchCaseSpace extends GapRule
{
    /** @var array<int, bool> */
    private array $clear = [];

    public function name(): string
    {
        return 'switch_case_space';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        $this->clear = [];
        $any = false;
        foreach (Analyzer::switchAnalyses($s) as $a) {
            $d = $a[0];
            if ($d >= 0) {
                $g = $d + 1;
                $ok = $g < $s->n && $s->gapBefore[$g] !== '' && !Gap::hasComment($s->gapBefore[$g])
                    && ($s->kind[$g] === 59 || ($s->kind[$g] === 58 && $s->ct[$g] === 0));
                if (!$ok) {
                    continue;
                }
                $this->clear[$g] = true;
                $any = true;
            }
            foreach ($a[2] as $c) {
                if ($s->gapBefore[$c] !== '' && !Gap::hasComment($s->gapBefore[$c])) {
                    $this->clear[$c] = true;
                    $any = true;
                }
            }
        }
        return $any;
    }

    public function beforeKinds(): array
    {
        return [58, 59];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        return isset($this->clear[$i]) ? '' : $gap;
    }
}
