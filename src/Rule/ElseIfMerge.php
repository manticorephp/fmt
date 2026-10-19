<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\ElseifFixer (priority 40). `else if (..)` (not the alternative syntax) becomes
 * `elseif`: the trivia item right after `else` goes (a whitespace or a comment), `if` goes, and when a comment is then the last trivia
 * before the old `if`, the whitespace right after `if` goes too.
 */
final class ElseIfMerge extends EditRule
{
    public function name(): string
    {
        return 'elseif';
    }

    public function priority(): int
    {
        return 40;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 0; $i + 2 < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_ELSE || $s->kind[$i + 1] !== \__McTokId::T_IF) {
                continue;
            }
            $close = $s->pair[$i + 2];
            if ($close < 0 || ($close + 1 < $n && $s->kind[$close + 1] === 58)) {
                continue;
            }
            $g = $s->gapBefore[$i + 1];
            $pieces = Gap::hasComment($g) ? Gap::pieces($g) : [$g];
            $rest = '';
            $comment = false;
            for ($k = 1; $k < \count($pieces); $k++) {
                $rest .= $pieces[$k];
                $comment = $comment || !Gap::isSpace($pieces[$k]);
            }
            $after = $s->gapBefore[$i + 2];
            $split = -1;
            if (!$comment && $rest !== '' && $after !== '' && Gap::isSpace(Gap::hasComment($after) ? Gap::pieces($after)[0] : $after)) {
                $split = 6 + \strlen($rest); // `else/* c */ if  (`: the blank before `if` and the one after it stay two tokens upstream
            }
            if ($comment && $after !== '') {
                $ap = Gap::hasComment($after) ? Gap::pieces($after) : [$after];
                if (Gap::isSpace($ap[0])) {
                    \array_shift($ap);
                    $after = \implode('', $ap);
                }
            }
            $s->edit(Source::tokAt($i), Source::tokAt($i + 2), 'elseif' . $rest . $after, $split);
        }
    }
}
