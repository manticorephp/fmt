<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocSingleLineVarSpacingFixer (priority -10): a single-line `@var` docblock gets single spaces. It
 * runs on every comment, so `/**@var T` (a T_COMMENT: no whitespace after `/**`) becomes a docblock too.
 */
final class PhpdocSingleLineVarSpacing extends GapRule
{
    public function name(): string
    {
        return 'phpdoc_single_line_var_spacing';
    }

    public function priority(): int
    {
        return -10;
    }

    public function needsMask(): int
    {
        return Gap::G_COMMENT;
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
        if (($s->gapFlags[$i] & Gap::G_COMMENT) === 0 || !\str_contains($gap, '@var') || !\str_contains($gap, '/**')) {
            return $gap;
        }
        $p = Gap::pieces($gap);
        $changed = false;
        foreach ($p as $k => $x) {
            if (!\str_starts_with($x, '/**') || !\str_contains($x, '@var')) {
                continue;
            }
            $new = Preg::replaceCallback('#^/\*\*\h*@var\h+(\S+)\h*(\$\S+)?\h*([^\n]*)\*/$#', static function (array $m): string {
                $c = '/** @var';
                $n = \count($m);
                for ($j = 1; $j < $n; $j++) {
                    if ((string) $m[$j] !== '') {
                        $c .= ' ' . $m[$j];
                    }
                }
                return \rtrim($c) . ' */';
            }, $x);
            if ($new !== $x) {
                $p[$k] = $new;
                $changed = true;
            }
        }
        return $changed ? \implode('', $p) : $gap;
    }
}
