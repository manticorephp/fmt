<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\NoExtraBlankLinesFixer (priority -20), every `tokens` option value.
 *
 * Upstream walks the tokens backwards and lets a trigger token (`use`, `{`, `throw`, ...) collapse the blank lines of
 * the first whitespace token holding "\n" after its line (removeEmptyLinesAfterLineWithTokenAt). Every decision reads only
 * WHERE "\n" occurs, which none of the rewrites changes, and a trigger only rewrites tokens after itself, so the walk is
 * split in two: (1) the triggers mark whitespace pieces (gap, piece index) to collapse; (2) each gap is rewritten once,
 * `extra` first (upstream reaches a whitespace token before any trigger in front of it), then the collapse.
 */
final class NoExtraBlankLines extends GapRule
{
    private bool $extra;
    private bool $comma;
    private bool $curly;
    private bool $paren;
    private bool $square;
    private bool $attribute;
    private bool $break;
    private bool $case;
    private bool $continue;
    private bool $default;
    private bool $return;
    private bool $switch;
    private bool $throw;
    private bool $use;
    private bool $useTrait;
    private bool $anyTrigger;

    /** @var array<int, int[]> gap index => whitespace piece indices to collapse to one line ending */
    private array $hits = [];
    /** @var array<int, bool>|null */
    private ?array $switchColons = null;

    /** @param string[] $tokens */
    public function __construct(private string $lineEnding, array $tokens)
    {
        $known = ['attribute', 'break', 'case', 'comma', 'continue', 'curly_brace_block', 'default', 'extra', 'parenthesis_brace_block',
            'return', 'square_brace_block', 'switch', 'throw', 'use', 'use_trait'];
        foreach ($tokens as $t) {
            if (!\in_array($t, $known, true)) {
                throw new \InvalidArgumentException("no_extra_blank_lines: unknown token $t");
            }
        }
        $this->extra = \in_array('extra', $tokens, true);
        $this->comma = \in_array('comma', $tokens, true);
        $this->curly = \in_array('curly_brace_block', $tokens, true);
        $this->paren = \in_array('parenthesis_brace_block', $tokens, true);
        $this->square = \in_array('square_brace_block', $tokens, true);
        $this->attribute = \in_array('attribute', $tokens, true);
        $this->break = \in_array('break', $tokens, true);
        $this->case = \in_array('case', $tokens, true);
        $this->continue = \in_array('continue', $tokens, true);
        $this->default = \in_array('default', $tokens, true);
        $this->return = \in_array('return', $tokens, true);
        $this->switch = \in_array('switch', $tokens, true);
        $this->throw = \in_array('throw', $tokens, true);
        $this->use = \in_array('use', $tokens, true);
        $this->useTrait = \in_array('use_trait', $tokens, true);
        $this->anyTrigger = \count($tokens) > ($this->extra ? 1 : 0);
    }

    public function name(): string
    {
        return 'no_extra_blank_lines';
    }

    public function priority(): int
    {
        return -20;
    }

    public function needsMask(): int
    {
        return Gap::G_NL2;
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
        $this->hits = [];
        $this->switchColons = null;
        $n = $s->n;
        if ($this->anyTrigger) {
            for ($t = 1; $t < $n; $t++) {
                $this->trigger($s, $t);
            }
        }
        $op = \__McTokId::T_OPEN_TAG;
        for ($g = 1; $g <= $n; $g++) {
            if (($this->extra && (($s->gapFlags[$g] & Gap::G_NL2) !== 0 || $s->kind[$g - 1] === $op)) || isset($this->hits[$g])) {
                $this->rewrite($s, $g);
            }
        }
    }

    private function trigger(Source $s, int $t): void
    {
        $k = $s->kind[$t];
        $c = $s->ct[$t];
        if ($c !== 0) {
            if ($c === CT::ATTRIBUTE_CLOSE) {
                if ($this->attribute) {
                    $this->fixAfterToken($s, $t);
                }
            } elseif ($c === CT::ARRAY_BRACKET_OPEN) {
                if ($this->square) {
                    $this->fixStructure($s, $t);
                }
            } elseif ($c === CT::USE_TRAIT) {
                if ($this->useTrait) {
                    $this->removeBetweenUse($s, $t);
                }
            }
            return;
        }
        if ($k === 44) {
            if ($this->comma) {
                $this->fixAfterToken($s, $t);
            }
        } elseif ($k === 123) {
            if ($this->curly) {
                $this->fixStructure($s, $t);
            }
        } elseif ($k === 40) {
            if ($this->paren) {
                $this->fixStructure($s, $t);
            }
        } elseif ($k === \__McTokId::T_BREAK) {
            if ($this->break) {
                $this->fixAfterToken($s, $t);
            }
        } elseif ($k === \__McTokId::T_CASE) {
            if ($this->case) {
                for ($j = $t - 1; $j >= 0; $j--) {
                    $kj = $s->kind[$j];
                    if (($kj === \__McTokId::T_SWITCH || $kj === \__McTokId::T_ENUM) && $s->ct[$j] === 0) {
                        if ($kj === \__McTokId::T_SWITCH) {
                            $this->removeAfterToken($s, $t);
                        }
                        break;
                    }
                }
            }
        } elseif ($k === \__McTokId::T_CONTINUE) {
            if ($this->continue) {
                $this->fixAfterToken($s, $t);
            }
        } elseif ($k === \__McTokId::T_DEFAULT) {
            if ($this->default) {
                $this->fixAfterToken($s, $t);
            }
        } elseif ($k === \__McTokId::T_RETURN) {
            if ($this->return) {
                $this->fixAfterToken($s, $t);
            }
        } elseif ($k === \__McTokId::T_SWITCH) {
            if ($this->switch) {
                $this->fixAfterToken($s, $t);
            }
        } elseif ($k === \__McTokId::T_THROW) {
            if ($this->throw) {
                $this->fixAfterThrow($s, $t);
            }
        } elseif ($k === \__McTokId::T_USE) {
            if ($this->use) {
                $this->removeBetweenUse($s, $t);
            }
        }
    }

    /** Does a whitespace piece of gap $g hold "\n"? */
    private static function wsHasNl(Source $s, int $g): bool
    {
        $f = $s->gapFlags[$g];
        if (($f & Gap::G_NL) === 0) {
            return false;
        }
        if (($f & Gap::G_COMMENT) === 0) {
            return true;
        }
        foreach (self::piecesOf($s, $g) as $p) {
            if (Gap::isSpace($p) && \str_contains($p, "\n")) {
                return true;
            }
        }
        return false;
    }

    /** Can token $i's own text hold "\n"? (Only these kinds can; a rewritten text is read as is.) */
    private static function tokHasNl(Source $s, int $i): bool
    {
        if (isset($s->over[$i])) {
            return \str_contains($s->over[$i], "\n");
        }
        $k = $s->kind[$i];
        if ($k === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $k === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $k === \__McTokId::T_INLINE_HTML
            || $k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_CLOSE_TAG || $k === \__McTokId::T_START_HEREDOC) {
            $p = \strpos($s->code, "\n", $s->start[$i]);
            return $p !== false && $p < $s->end[$i];
        }
        return false;
    }

    private function fixAfterToken(Source $s, int $t): void
    {
        for ($j = $t - 1; $j > 0; $j--) {
            if (self::wsHasNl($s, $j + 1)) {
                break;
            }
            $k = $s->kind[$j];
            $c = $s->ct[$j];
            if ($c === 0) {
                if ($k === \__McTokId::T_FUNCTION && Analyzer::isLambda($s, $j)) {
                    return;
                }
                if ($k === \__McTokId::T_CLASS && Analyzer::isAnonymousClass($s, $j)) {
                    return;
                }
            } elseif ($c === CT::ARRAY_BRACKET_OPEN || $c === CT::DESTRUCTURING_BRACKET_OPEN) {
                return;
            }
        }
        $this->removeAfterToken($s, $t);
    }

    private function fixAfterThrow(Source $s, int $t): void
    {
        $p = $t - 1;
        $k = $s->kind[$p];
        $c = $s->ct[$p];
        if (!($k === 59 || $k === \__McTokId::T_OPEN_TAG || ($c === 0 && ($k === 123 || $k === 125 || $k === 58)))) {
            return;
        }
        if ($k === 58) {
            if ($this->switchColons === null) {
                $this->switchColons = Analyzer::switchColons($s);
            }
            if (!isset($this->switchColons[$p])) {
                return;
            }
        }
        $this->fixAfterToken($s, $t);
    }

    /** `{` `(` `[`: when anything from the last "\n" inside back to the opener, collapse after it and after the opener. */
    private function fixStructure(Source $s, int $o): void
    {
        $close = $s->pair[$o];
        for ($j = $close; $j > $o; $j--) {
            // gap $j (pieces last to first), then token $j - 1
            $f = $s->gapFlags[$j];
            if (($f & Gap::G_NL) !== 0) {
                if (($f & Gap::G_COMMENT) === 0 && !isset($s->wsSplit[$j])) {
                    $this->hit($j, 0);
                } else {
                    $pieces = self::piecesOf($s, $j);
                    for ($k = \count($pieces) - 1; $k >= 0; $k--) {
                        if (\str_contains($pieces[$k], "\n")) {
                            if (Gap::isSpace($pieces[$k])) {
                                $this->hit($j, $k);
                            }
                            break;
                        }
                    }
                }
                $this->removeAfterToken($s, $o);
                return;
            }
            if ($j - 1 > $o && self::tokHasNl($s, $j - 1)) {
                $this->removeAfterToken($s, $j - 1);
                $this->removeAfterToken($s, $o);
                return;
            }
        }
    }

    private function removeBetweenUse(Source $s, int $t): void
    {
        $n = $s->n;
        $next = -1;
        for ($j = $t + 1; $j < $n; $j++) {
            $k = $s->kind[$j];
            if ($k === 59 || $k === \__McTokId::T_CLOSE_TAG) {
                $next = $j;
                break;
            }
        }
        if ($next < 0 || $s->kind[$next] === \__McTokId::T_CLOSE_TAG) {
            return;
        }
        $nu = $next + 1;
        if ($nu >= $n || $s->kind[$nu] !== $s->kind[$t] || $s->ct[$nu] !== $s->ct[$t]) {
            return;
        }
        $lb = false;
        for ($j = $nu; $j > $t; $j--) {
            if (Gap::hasR($s->gapBefore[$j], $s->gapFlags[$j]) || ($j < $nu && \strpbrk($s->text($j), "\r\n") !== false)) {
                $lb = true;
                break;
            }
        }
        if ($lb) {
            $this->removeAfterToken($s, $next);
        }
    }

    /**
     * removeEmptyLinesAfterLineWithTokenAt($t): the first token at/after $t that is `}` or holds "\n" (an unbalanced `)`
     * aborts); when it is a whitespace piece, it is collapsed.
     */
    private function removeAfterToken(Source $s, int $t): void
    {
        $n = $s->n;
        $depth = 0;
        for ($j = $t; $j < $n; $j++) {
            $k = $s->kind[$j];
            $c = $s->ct[$j];
            if ($k === 40 && $c === 0) {
                $depth++;
            } elseif ($k === 41 && $c === 0) {
                $depth--;
                if ($depth < 0) {
                    return;
                }
            } elseif (($k === 125 && $c === 0) || self::tokHasNl($s, $j)) {
                return;
            }
            if ($this->gapStop($s, $j + 1)) {
                return;
            }
        }
    }

    /** Scans gap $g for the first piece holding "\n"; true when found (a whitespace piece is marked). */
    private function gapStop(Source $s, int $g): bool
    {
        $f = $s->gapFlags[$g];
        if (($f & Gap::G_NL) === 0) {
            return false;
        }
        if (($f & Gap::G_COMMENT) === 0 && !isset($s->wsSplit[$g])) {
            $this->hit($g, 0);
            return true;
        }
        $pieces = self::piecesOf($s, $g);
        $cnt = \count($pieces);
        for ($k = 0; $k < $cnt; $k++) {
            if (\str_contains($pieces[$k], "\n")) {
                if (Gap::isSpace($pieces[$k])) {
                    $this->hit($g, $k);
                }
                return true;
            }
        }
        return false;
    }

    private function hit(int $g, int $k): void
    {
        if (!isset($this->hits[$g])) {
            $this->hits[$g] = [$k];
        } elseif (!\in_array($k, $this->hits[$g], true)) {
            $this->hits[$g][] = $k;
        }
    }

    /** @return string[] the whitespace/comment pieces of gap $g; a gap upstream holds as two whitespace tokens is split there */
    private static function piecesOf(Source $s, int $g): array
    {
        $gap = $s->gapBefore[$g];
        $p = Gap::hasComment($gap) ? Gap::pieces($gap) : [$gap];
        return isset($s->wsSplit[$g]) ? Gap::splitPieces($p, $s->wsSplit[$g]) : $p;
    }

    private function rewrite(Source $s, int $g): void
    {
        $gap = $s->gapBefore[$g];
        if ($gap === '') {
            return;
        }
        $pieces = self::piecesOf($s, $g);
        $cnt = \count($pieces);
        $hits = $this->hits[$g] ?? [];
        $out = '';
        for ($k = 0; $k < $cnt; $k++) {
            $p = $pieces[$k];
            if (Gap::isSpace($p)) {
                if ($this->extra) {
                    $expected = 2;
                    if ($k === 0 && $s->kind[$g - 1] === \__McTokId::T_OPEN_TAG) {
                        $tag = $s->text($g - 1);
                        $last = \ord($tag[\strlen($tag) - 1]);
                        if ($last === 10 || $last === 13) {
                            $expected = 1;
                        }
                    }
                    $p = self::removeMultipleBlankLines($p, $expected);
                }
                if ($hits !== [] && \in_array($k, $hits, true)) {
                    $q = \max((int) \strrpos($p, "\n"), \strrpos($p, "\r") === false ? 0 : (int) \strrpos($p, "\r"));
                    $p = $this->lineEnding . \substr($p, $q + 1);
                }
            }
            $out .= $p;
        }
        if ($out !== $gap) {
            $s->setGap($g, $out);
        }
    }

    /**
     * `Preg::split('/(.*\R)/', ...)` (DELIM_CAPTURE | NO_EMPTY): a part per "\n"-ended line; after the last "\n" a part up to
     * the last "\r", then the rest. More than $expected parts: keep the first $expected and the last one minus its \r\n.
     */
    private static function removeMultipleBlankLines(string $p, int $expected): string
    {
        if (\strpos($p, "\r") === false && \substr_count($p, "\n") + (\str_ends_with($p, "\n") ? 0 : 1) <= $expected) {
            return $p;
        }
        /** @var string[] $parts */
        $parts = [];
        $from = 0;
        $len = \strlen($p);
        while (true) {
            $q = \strpos($p, "\n", $from);
            if ($q === false) {
                break;
            }
            $parts[] = \substr($p, $from, $q + 1 - $from);
            $from = $q + 1;
        }
        if ($from < $len) {
            $r = \strrpos($p, "\r", $from);
            if ($r !== false) {
                $parts[] = \substr($p, $from, $r + 1 - $from);
                $from = $r + 1;
            }
            if ($from < $len) {
                $parts[] = \substr($p, $from);
            }
        }
        $count = \count($parts);
        if ($count <= $expected) {
            return $p;
        }
        $out = '';
        for ($k = 0; $k < $expected; $k++) {
            $out .= $parts[$k];
        }
        return $out . \rtrim($parts[$count - 1], "\r\n");
    }
}
