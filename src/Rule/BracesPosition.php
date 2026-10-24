<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\BracesPositionFixer (priority -2), every option.
 *
 * Upstream walks the tokens forward; per structure (class, function, control structure, property hook) it puts a line
 * break after `{`, places `{` on the signature line or the next one, moves `{` across comments, and puts `}` on its own
 * line. In this model comments are trivia, so all of that is a rewrite of three gaps: before `{` (A), after `{` (B),
 * before `}` (C). "Moving the brace across a comment" moves trivia pieces between A and B. The steps run in upstream
 * order on the live gaps (a later structure reads indentation an earlier one rewrote).
 *
 * One upstream move crosses real tokens (`{ code; // c` with `{` going to the next line): its output does not lint, so
 * upstream leaves the file alone; Source::$abort says the same here.
 */
final class BracesPosition extends GapRule
{
    /** first structure allowed on one line (upstream's allowSingleLineUntil, set once per file), -1 = none */
    private int $until = -1;

    public function __construct(
        private string $lineEnding,
        private bool $controlNext,
        private bool $functionsNext,
        private bool $anonFunctionsNext,
        private bool $classesNext,
        private bool $anonClassesNext,
        private bool $allowEmptyAnonClasses,
        private bool $allowSingleLineAnonFunctions,
    ) {}

    public function name(): string
    {
        return 'braces_position';
    }

    public function priority(): int
    {
        return -2;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_TEXT | self::F_NB;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function fixFile(Source $s): void
    {
        $this->until = -1;
        $n = $s->n;
        $hasHook = false;
        for ($i = 0; $i < $n; $i++) {
            if ($s->ct[$i] === CT::PROPERTY_HOOK_BRACE_OPEN) {
                $hasHook = true;
                break;
            }
        }
        for ($i = 0; $i < $n; $i++) {
            if ($s->ct[$i] !== 0) {
                continue;
            }
            $k = $s->kind[$i];
            $allowSL = false;
            $allowSLE = false;
            if ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_ENUM) {
                $open = -1;
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($s->kind[$j] === 123 && $s->ct[$j] === 0) {
                        $open = $j;
                        break;
                    }
                }
                if ($open < 0) {
                    continue;
                }
                if (Analyzer::isAnonymousClass($s, $i)) {
                    $allowSLE = $this->allowEmptyAnonClasses;
                    $next = $this->anonClassesNext;
                } else {
                    $next = $this->classesNext;
                }
            } elseif ($k === \__McTokId::T_FUNCTION) {
                $open = -1;
                for ($j = $i + 1; $j < $n; $j++) {
                    $kj = $s->kind[$j];
                    if ($kj === 123 || $kj === 59) {
                        if ($kj === 123 && $s->ct[$j] === 0) {
                            $open = $j;
                            break;
                        }
                        if ($kj === 59 || $s->ct[$j] === CT::PROPERTY_HOOK_BRACE_OPEN) {
                            break;
                        }
                    }
                }
                if ($open < 0) {
                    continue;
                }
                if (Analyzer::isLambda($s, $i)) {
                    $allowSL = $this->allowSingleLineAnonFunctions;
                    $next = $this->anonFunctionsNext;
                } else {
                    $next = $this->functionsNext;
                }
            } elseif ($k === \__McTokId::T_DECLARE || $k === \__McTokId::T_DO || $k === \__McTokId::T_ELSE || $k === \__McTokId::T_ELSEIF
                || $k === \__McTokId::T_FINALLY || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_IF
                || $k === \__McTokId::T_WHILE || $k === \__McTokId::T_TRY || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_SWITCH
                || $k === \__McTokId::T_MATCH) {
                $open = $this->afterParens($s, $i);
                if ($open >= $n || $s->kind[$open] !== 123 || $s->ct[$open] !== 0) {
                    continue;
                }
                $next = $this->controlNext;
            } elseif ($k === \__McTokId::T_VARIABLE) {
                if (!$hasHook) {
                    continue;
                }
                $j = $i;
                if ($i + 2 < $n && $s->kind[$i + 1] === 61) {
                    $m = $i + 2;
                    if ($s->ct[$m] === CT::ARRAY_BRACKET_OPEN) {
                        $j = $s->pair[$m];
                    } elseif ($s->kind[$m] === \__McTokId::T_ARRAY && $s->ct[$m] === 0 && $m + 1 < $n && $s->kind[$m + 1] === 40 && $s->ct[$m + 1] === 0) {
                        $j = $s->pair[$m + 1];
                    }
                }
                $open = -1;
                for ($o = $j + 1; $o < $n; $o++) {
                    $ko = $s->kind[$o];
                    $co = $s->ct[$o];
                    if (($ko === 123 && $co === 0) || $ko === 59 || $ko === 46 || $co === CT::CURLY_CLOSE || $co === CT::PROPERTY_HOOK_BRACE_OPEN
                        || $ko === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $ko === \__McTokId::T_CLOSE_TAG) {
                        if ($co === CT::PROPERTY_HOOK_BRACE_OPEN) {
                            $open = $o;
                        }
                        break;
                    }
                }
                if ($open < 0 || !$this->multiline($s, $open, $s->pair[$open])) {
                    continue;
                }
                $next = $this->controlNext;
            } elseif ($k === \__McTokId::T_STRING) {
                $open = $this->afterParens($s, $i);
                if ($open >= $n || $s->kind[$open] !== 123 || $s->ct[$open] !== 0 || $i === 0) {
                    continue;
                }
                $kp = $s->kind[$i - 1];
                $cp = $s->ct[$i - 1];
                if (!((($kp === 125 && $cp === 0) || $kp === 59 || $cp === CT::ATTRIBUTE_CLOSE || $cp === CT::PROPERTY_HOOK_BRACE_OPEN))) {
                    continue;
                }
                $allowSL = $this->allowSingleLineAnonFunctions;
                $next = $this->controlNext;
            } else {
                continue;
            }
            $this->fixBrace($s, $i, $open, $allowSL, $allowSLE, $next);
            if ($s->abort) {
                return;
            }
        }
    }

    /** findParenthesisEnd + getNextMeaningfulToken: the token after the `( ... )` that follows $i, or after $i. */
    private function afterParens(Source $s, int $i): int
    {
        if ($i + 1 < $s->n && $s->kind[$i + 1] === 40 && $s->ct[$i + 1] === 0) {
            return $s->pair[$i + 1] + 1;
        }
        return $i + 1;
    }

    /** Tokens::isPartialCodeMultiline($a, $b): a "\n" in any token or trivia from $a to $b. */
    private function multiline(Source $s, int $a, int $b): bool
    {
        for ($j = $a; $j <= $b; $j++) {
            if ($j > $a && ($s->gapFlags[$j] & Gap::G_NL) !== 0) {
                return true;
            }
            if (\str_contains($s->text($j), "\n")) {
                return true;
            }
        }
        return false;
    }

    /** A whitespace piece holding \R in any gap from $a to $b. */
    private static function anyWsR(Source $s, int $a, int $b): bool
    {
        for ($g = $a; $g <= $b; $g++) {
            $f = $s->gapFlags[$g];
            if (($f & (Gap::G_NL | Gap::G_CR)) === 0) {
                continue;
            }
            if (($f & Gap::G_COMMENT) === 0) {
                return true;
            }
            foreach (Gap::pieces($s->gapBefore[$g]) as $p) {
                if (Gap::isSpace($p) && Gap::pieceHasR($p)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function isWs(string $p): bool
    {
        return $p === '' || Gap::isSpace($p);
    }

    /** @param string[] $pieces */
    private static function put(Source $s, int $g, array $pieces): void
    {
        $out = \implode('', $pieces);
        if ($out !== $s->gapBefore[$g]) {
            $s->setGap($g, $out);
        }
    }

    private function fixBrace(Source $s, int $index, int $open, bool $allowSL, bool $allowSLE, bool $next): void
    {
        $close = $s->pair[$open];
        $le = $this->lineEnding;
        $add = true;
        if ($allowSL || $allowSLE || $index < $this->until) {
            $add = ($allowSLE && $close > $open + 1) || self::anyWsR($s, $open + 1, $close);
            if (!$add && $this->until < 0) {
                $this->until = $close;
            }
        }

        // a line break after `{`, unless one follows (comments skipped), a comment sits on its line, or a close tag follows
        if ($add && $s->kind[$open + 1] !== \__McTokId::T_CLOSE_TAG) {
            $b = Analyzer::pieces($s, $open + 1);
            $followed = false;
            foreach ($b as $p) {
                if (Gap::isSpace($p)) {
                    $followed = Gap::pieceHasR($p);
                    break;
                }
            }
            $cnt = \count($b);
            $commentSameLine = $cnt > 0 && (!Gap::isSpace($b[0]) || (!Gap::pieceHasR($b[0]) && $cnt > 1 && !Gap::isSpace($b[1])));
            if (!$followed && !$commentSameLine) {
                $ws = $le . Analyzer::lineIndentation($s, $open);
                if ($cnt > 0 && Gap::isSpace($b[0])) {
                    $b[0] = $ws;
                } else {
                    \array_unshift($b, $ws);
                }
                self::put($s, $open + 1, $b);
            }
        }

        $ws = ' ';
        if ($next) {
            $ws = $le . Analyzer::lineIndentation($s, $index);
            $p = $open - 1;
            while ($p > 0 && self::isTypeToken($s, $p)) {
                $p--;
            }
            if ($s->kind[$p] === 41 && $s->ct[$p] === 0) {
                $pp = Analyzer::pieces($s, $p);
                $cnt = \count($pp);
                if ($cnt > 0) {
                    $cand = $pp[$cnt - 1];
                    if (!Gap::isSpace($cand)) {
                        $cand = $cnt >= 2 ? $pp[$cnt - 2] : '/';
                    }
                    if (Gap::isSpace($cand) && Gap::pieceHasR($cand)) {
                        $ws = ' ';
                    }
                }
            }
        }

        $a = Analyzer::pieces($s, $open);
        $b = Analyzer::pieces($s, $open + 1);
        $m = \count($a);
        $moved = false;
        if ($ws === ' ') {
            if (($s->gapFlags[$open] & Gap::G_COMMENT) !== 0) {
                // the leftmost comment before `{` that has whitespace (or the previous token) before it: `{` goes in front of that
                $target = -1;
                for ($x = $m - 1; $x >= 0; $x--) {
                    if (Gap::isSpace($a[$x])) {
                        continue;
                    }
                    $x--;
                    if ($x < 0) {
                        $target = 0;
                    } elseif (Gap::isSpace($a[$x])) {
                        $target = $x;
                    }
                }
                if ($target >= 0) {
                    if (Gap::isSpace($a[$m - 1])) {
                        if (Gap::pieceHasR($a[$m - 1])) {
                            if ($b !== [] && Gap::isSpace($b[0])) {
                                $b[0] = (string) \preg_replace('/^(\h*?\R)?\h*/', '', $b[0]);
                            }
                        } else {
                            $a[$m - 1] = '';
                        }
                    }
                    $newB = \array_merge(\array_slice($a, $target), $b);
                    $a = \array_slice($a, 0, $target);
                    $b = $newB;
                    $m = \count($a);
                    $moved = true;
                }
            }
        } elseif (!($m > 0 && Gap::isSpace($a[$m - 1]) && Gap::pieceHasR($a[$m - 1]))) {
            // `{` goes after `//` comments that share its line
            $target = -1;
            $stop = false;
            $cnt = \count($b);
            for ($q = 0; $q < $cnt; $q++) {
                $p = $b[$q];
                if (Gap::isSpace($p)) {
                    if (Gap::pieceHasR($p)) {
                        $stop = true;
                        break;
                    }
                } elseif (!\str_starts_with($p, '/*')) {
                    $target = $q + 1;
                }
            }
            if (!$stop && $open + 1 < $close && $this->lineCommentBeforeBreak($s, $open + 2, $close)) {
                $s->abort = true; // the move crosses code: upstream's result does not lint
                return;
            }
            if ($target >= 0) {
                // the rotation puts `{` AT the piece after the comment: that piece shifts in front of `{` too
                if ($target >= $cnt) {
                    $s->abort = true; // no trivia after the comment: `{` would swap with the next token
                    return;
                }
                if (Gap::isSpace($b[0]) && $m > 0 && Gap::isSpace($a[$m - 1])) {
                    $a[$m - 1] = '';
                }
                $a = \array_merge($a, \array_slice($b, 0, $target + 1));
                $b = \array_slice($b, $target + 1);
                $m = \count($a);
                $moved = true;
            }
        }
        // ensureWhitespaceAtIndex(open - 1, 1, ws): replace the whitespace before `{` (an emptied one too), else add one
        $last = -1;
        for ($x = $m - 1; $x >= 0; $x--) {
            $last = $x;
            break;
        }
        $closeIndent = null;
        if ($last >= 0 && self::isWs($a[$last])) {
            $a[$last] = $ws;
        } else {
            // upstream keeps its brace index on the whitespace it just inserted, so the close brace's indent is read
            // from the line before that whitespace
            if ($ws !== ' ') {
                self::put($s, $open, $a);
                $closeIndent = Analyzer::lineIndentation($s, $open);
            }
            $a[] = $ws;
        }
        self::put($s, $open, $a);
        if ($moved) {
            self::put($s, $open + 1, $b);
        }

        if (!$add || $s->kind[$close - 1] === \__McTokId::T_OPEN_TAG) {
            return;
        }
        $c = Analyzer::pieces($s, $close);
        $cnt = \count($c);
        if ($cnt > 0 && Gap::isSpace($c[$cnt - 1]) && Gap::pieceHasR($c[$cnt - 1])) {
            return;
        }
        $ws = $le . ($closeIndent ?? Analyzer::lineIndentation($s, $open));
        if ($cnt > 0 && Gap::isSpace($c[$cnt - 1])) {
            $c[$cnt - 1] = $ws;
        } else {
            $c[] = $ws;
        }
        self::put($s, $close, $c);
    }

    /** Upstream's scan after `{` continues past code: a `//` / `#` comment before the next line break in gaps $a..$b. */
    private function lineCommentBeforeBreak(Source $s, int $a, int $b): bool
    {
        for ($g = $a; $g <= $b; $g++) {
            $f = $s->gapFlags[$g];
            if ($f === 0 || $f === Gap::G_TAB) {
                continue;
            }
            foreach (Analyzer::pieces($s, $g) as $p) {
                if (Gap::isSpace($p)) {
                    if (Gap::pieceHasR($p)) {
                        return false;
                    }
                } elseif (!\str_starts_with($p, '/*')) {
                    return true;
                }
            }
        }
        return false;
    }

    /** The return-type tokens upstream skips back over before `{` (CT::T_TYPE_COLON, T_STRING, `?`, `|`, `&`, DNF parens, ...). */
    private static function isTypeToken(Source $s, int $p): bool
    {
        $c = $s->ct[$p];
        if ($c !== 0) {
            return $c === CT::TYPE_COLON || $c === CT::NULLABLE_TYPE || $c === CT::ARRAY_TYPEHINT || $c === CT::TYPE_ALTERNATION
                || $c === CT::TYPE_INTERSECTION || $c === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN
                || $c === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE;
        }
        $k = $s->kind[$p];
        return $k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_CALLABLE;
    }
}
