<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\BlankLineBetweenImportGroupsFixer (priority -40), a file rule. Per namespace the `use`
 * statements are walked from the last; where the import type (class / function / const) differs from the next statement's, the
 * trivia piece behind the `;` that holds the first line break (else the last piece, else the `;` itself) becomes "\n\n" + the
 * indent found by WhitespacesAnalyzer::detectIndent, or a whitespace piece with that text is inserted after it.
 */
final class BlankLineBetweenImportGroups extends GapRule
{
    public function __construct(private string $le = "\n")
    {
    }

    public function name(): string
    {
        return 'blank_line_between_import_groups';
    }

    public function priority(): int
    {
        return -40;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        $n = $s->n;
        for ($j = 0; $j < $n; $j++) {
            if ($s->kind[$j] === \__McTokId::T_USE && $s->ct[$j] === 0) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        /** @var int[][] $groups */
        $groups = [[]];
        $cur = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_NAMESPACE && $s->ct[$i] === 0) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($s->ct[$j] === 0 && ($s->kind[$j] === 59 || $s->kind[$j] === 123)) {
                        if ($s->kind[$j] === 123) {
                            $i = $j;
                        }
                        break;
                    }
                }
                $cur++;
                $groups[$cur] = [];
                continue;
            }
            if ($s->kind[$i] === \__McTokId::T_USE && $s->ct[$i] === 0) {
                $groups[$cur][] = $i;
            }
        }
        for ($g = $cur; $g >= 0; $g--) {
            $uses = $groups[$g];
            $cnt = \count($uses);
            if ($cnt < 2) {
                continue;
            }
            $prev = -1;
            for ($u = $cnt - 1; $u >= 0; $u--) {
                $idx = $uses[$u];
                $start = $s->gapBefore[$idx + 1] === '' ? $idx + 2 : $idx + 1;
                if ($start >= $n) {
                    continue;
                }
                $end = -1;
                for ($j = $start + 1; $j < $n; $j++) {
                    if (($s->kind[$j] === 59 && $s->ct[$j] === 0) || $s->kind[$j] === \__McTokId::T_CLOSE_TAG) {
                        $end = $j;
                        break;
                    }
                }
                if ($end < 0) {
                    continue;
                }
                $type = $s->ct[$start] === CT::CONST_IMPORT ? 1 : ($s->ct[$start] === CT::FUNCTION_IMPORT ? 2 : 0);
                if ($prev >= 0 && $type !== $prev) {
                    $this->ensureLine($s, $end);
                }
                $prev = $type;
            }
        }
    }

    private function ensureLine(Source $s, int $semi): void
    {
        $n = $s->n;
        $gi = $semi + 1;
        $p = $gi <= $n ? Analyzer::pieces($s, $gi) : [];
        $m = \count($p);
        $target = -2; // -2: the `;` itself
        for ($q = 0; $q < $m; $q++) {
            $target = $q;
            if (\strpos($p[$q], "\n") !== false) {
                break;
            }
        }
        if ($target === -2) {
            $indent = Analyzer::detectIndent($s, $semi, \PHP_INT_MAX);
            $s->setGap($gi, $this->le . $this->le . $indent . ($gi <= $n ? $s->gapBefore[$gi] : ''));
            return;
        }
        $indent = Analyzer::detectIndent($s, $gi, $target);
        $text = $this->le . $this->le . $indent;
        if (Gap::isSpace($p[$target])) {
            $p[$target] = $text;
        } else {
            \array_splice($p, $target + 1, 0, [$text]);
        }
        $s->setGap($gi, \implode('', $p));
    }
}
