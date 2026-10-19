<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\SingleTraitInsertPerStatementFixer (priority 36). `use A, B;` in a class body becomes
 * `use A; use B;` (not a `use A, B { .. }`): each comma turns into `;` and `use ` is inserted before the next name; when the trivia
 * right before that name ends in whitespace it goes, and if that whitespace held a line break, a copy of whatever item precedes the
 * first `use` (its indentation, a comment, or a code token) is inserted first.
 */
final class SingleTraitInsertPerStatement extends EditRule
{
    public function name(): string
    {
        return 'single_trait_insert_per_statement';
    }

    public function priority(): int
    {
        return 36;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 1; $i < $n; $i++) {
            if ($s->ct[$i] !== CT::USE_TRAIT) {
                continue;
            }
            $commas = [];
            $j = $i + 1;
            for (; $j < $n; $j++) {
                $k = $s->kind[$j];
                if ($k === 59 || ($k === 123 && $s->ct[$j] === 0)) {
                    break;
                }
                if ($k === 44) {
                    $commas[] = $j;
                }
            }
            if ($j >= $n || $s->kind[$j] === 123 || $commas === []) {
                continue;
            }
            // the item right before `use`: the last trivia piece of its gap, or the previous code token
            $g = $s->gapBefore[$i];
            if ($g === '') {
                $before = $s->text($i - 1);
            } else {
                $p = Gap::hasComment($g) ? Gap::pieces($g) : [$g];
                $before = $p[\count($p) - 1];
            }
            foreach ($commas as $c) {
                $g = $s->gapBefore[$c + 1];
                $ins = 'use ';
                if ($g !== '') {
                    $p = Gap::hasComment($g) ? Gap::pieces($g) : [$g];
                    $last = $p[\count($p) - 1];
                    if (Gap::isSpace($last)) {
                        if (Gap::hasR($last, Gap::flags($last))) {
                            $ins = $before . $ins;
                        }
                        \array_pop($p);
                        $g = \implode('', $p);
                    }
                }
                $s->edit(Source::tokAt($c), Source::tokAt($c + 1), ';' . $g . $ins);
            }
            $i = $j;
        }
    }
}
