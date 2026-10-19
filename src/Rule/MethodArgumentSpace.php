<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer (priority 30), every option of the presets
 * (on_multiline ignore / ensure_fully_multiline, after_heredoc, keep_multiple_spaces_after_comma, attribute_placement
 * ignore / standalone). Upstream visits every plain `(` in file order and each call's commas in reverse; the second stage
 * (ensure_fully_multiline) of an outer call changes the gaps the inner calls then read, so this is a file rule: one walk,
 * gaps only (setGap), the upstream token-index arithmetic done on the trivia pieces of the gaps (Gap::pieces).
 */
final class MethodArgumentSpace extends GapRule
{
    /** @var array<int, bool> Token::getKeywords() by effective id */
    private array $keywords = [];
    /** @var array<int, bool> */
    private array $expected = [];

    public function __construct(
        private string $lineEnding,
        private bool $keepMultiple,
        private bool $fullyMultiline,
        private bool $afterHeredoc,
        private bool $standaloneAttributes,
        private string $indent = "    ",
    ) {
        foreach ([\__McTokId::T_ABSTRACT, \__McTokId::T_ARRAY, \__McTokId::T_AS, \__McTokId::T_BREAK, \__McTokId::T_CALLABLE, \__McTokId::T_CASE,
            \__McTokId::T_CATCH, \__McTokId::T_CLASS, \__McTokId::T_CLONE, \__McTokId::T_CONST, \__McTokId::T_CONTINUE, \__McTokId::T_DECLARE,
            \__McTokId::T_DEFAULT, \__McTokId::T_DO, \__McTokId::T_ECHO, \__McTokId::T_ELSE, \__McTokId::T_ELSEIF, \__McTokId::T_EMPTY,
            \__McTokId::T_ENDDECLARE, \__McTokId::T_ENDFOR, \__McTokId::T_ENDFOREACH, \__McTokId::T_ENDIF, \__McTokId::T_ENDSWITCH,
            \__McTokId::T_ENDWHILE, \__McTokId::T_EVAL, \__McTokId::T_EXIT, \__McTokId::T_EXTENDS, \__McTokId::T_FINAL, \__McTokId::T_FINALLY,
            \__McTokId::T_FN, \__McTokId::T_FOR, \__McTokId::T_FOREACH, \__McTokId::T_FUNCTION, \__McTokId::T_GLOBAL, \__McTokId::T_GOTO,
            \__McTokId::T_HALT_COMPILER, \__McTokId::T_IF, \__McTokId::T_IMPLEMENTS, \__McTokId::T_INCLUDE, \__McTokId::T_INCLUDE_ONCE,
            \__McTokId::T_INSTANCEOF, \__McTokId::T_INSTEADOF, \__McTokId::T_INTERFACE, \__McTokId::T_ISSET, \__McTokId::T_LIST,
            \__McTokId::T_LOGICAL_AND, \__McTokId::T_LOGICAL_OR, \__McTokId::T_LOGICAL_XOR, \__McTokId::T_NAMESPACE, \__McTokId::T_NEW,
            \__McTokId::T_PRINT, \__McTokId::T_PRIVATE, \__McTokId::T_PROTECTED, \__McTokId::T_PUBLIC, \__McTokId::T_REQUIRE,
            \__McTokId::T_REQUIRE_ONCE, \__McTokId::T_RETURN, \__McTokId::T_STATIC, \__McTokId::T_SWITCH, \__McTokId::T_THROW,
            \__McTokId::T_TRAIT, \__McTokId::T_TRY, \__McTokId::T_UNSET, \__McTokId::T_USE, \__McTokId::T_VAR, \__McTokId::T_WHILE,
            \__McTokId::T_YIELD, \__McTokId::T_YIELD_FROM, CT::ARRAY_TYPEHINT, CT::CLASS_CONSTANT, CT::CONST_IMPORT,
            CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE, CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED, CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC,
            CT::FUNCTION_IMPORT, CT::NAMESPACE_OPERATOR, CT::USE_LAMBDA, CT::USE_TRAIT, \__McTokId::T_ENUM, \__McTokId::T_MATCH,
            \__McTokId::T_PRIVATE_SET, \__McTokId::T_PROTECTED_SET, \__McTokId::T_PUBLIC_SET, \__McTokId::T_READONLY] as $k) {
            $this->keywords[$k] = true;
        }
        foreach ([\__McTokId::T_LIST, \__McTokId::T_FUNCTION, CT::USE_LAMBDA, \__McTokId::T_FN, \__McTokId::T_CLASS] as $k) {
            $this->expected[$k] = true;
        }
    }

    public function name(): string
    {
        return 'method_argument_space';
    }

    public function priority(): int
    {
        return 30;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_NB;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    /**
     * Upstream fixes `$tokenCount = $tokens->count()` once and walks `$index < $tokenCount` while its own fixes INSERT whitespace
     * tokens: the tail of the file (as many tokens as were inserted) is never visited. $ins counts the insertions so far,
     * $adj[i] = (current - original) trivia piece count of gap i, a cleared whitespace token still counting (clearAt keeps it).
     */
    private int $ins = 0;
    /** @var array<int, int> */
    private array $adj = [];

    private static function cnt(string $g): int
    {
        return $g === '' ? 0 : (Gap::hasComment($g) ? \count(Gap::pieces($g)) : 1);
    }

    /** setGap + the upstream token-count bookkeeping; $clr = whitespace tokens upstream clears (they stay in the list) */
    private function put(Source $s, int $i, string $new, int $clr = 0): void
    {
        $d = self::cnt($new) - self::cnt($s->gapBefore[$i]);
        $this->ins += $d + $clr;
        if ($d !== 0) {
            $this->adj[$i] = ($this->adj[$i] ?? 0) + $d;
        }
        $s->setGap($i, $new);
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $kind = $s->kind;
        $ct = $s->ct;
        $this->ins = 0;
        $this->adj = [];
        $total = $n;
        for ($g = 0; $g <= $n; $g++) {
            $total += self::cnt($s->gapBefore[$g]);
        }
        $acc = self::cnt($s->gapBefore[0]);
        for ($i = 1; $i < $n; $i++) {
            $acc += self::cnt($s->gapBefore[$i]) - ($this->adj[$i] ?? 0);
            if ($kind[$i] !== 40 || $ct[$i] !== 0) {
                continue;
            }
            if ($i + $acc + $this->ins >= $total) {
                break;
            }
            $p = $i - 1;
            $pid = $ct[$p] !== 0 ? $ct[$p] : $kind[$p];
            if (isset($this->keywords[$pid]) && !isset($this->expected[$pid])) {
                continue;
            }
            $this->call($s, $i, $pid === \__McTokId::T_LIST);
        }
    }

    /** fixFunction + ensureFunctionFullyMultiline for the `(` at $o */
    private function call(Source $s, int $o, bool $isList): void
    {
        $e = $s->pair[$o];
        if ($e <= $o) {
            return;
        }
        $multi = false;
        if ($this->fullyMultiline && !$isList) {
            $multi = $this->boundaryHasBreak($s, $o, $e);
        }
        $r = $e - 1;
        while ($r > $o) {
            $c = $s->ct[$r];
            $k = $s->kind[$r];
            if ($c === 0) {
                if ($k === 41 || $k === 125) {
                    $r = $s->pair[$r] - 1;
                    continue;
                }
                if ($k === 44) {
                    $this->fixSpace($s, $r);
                    if (!$multi && $this->fullyMultiline && !$isList) {
                        $g = $s->gapBefore[$r + 1];
                        if ($g !== '' && ($s->gapFlags[$r + 1] & Gap::G_NL) !== 0 && self::firstIsNewline($g)) {
                            $multi = true;
                        }
                    }
                }
            } elseif ($c === CT::ARRAY_BRACKET_CLOSE) {
                $r = $s->pair[$r] - 1;
                continue;
            }
            $r--;
        }
        if ($multi) {
            $this->ensureMultiline($s, $o, $e);
        }
    }

    /** the whitespace token right after `(` / right before `)` holds a line break (with upstream's index quirks for empty parentheses) */
    private function boundaryHasBreak(Source $s, int $o, int $e): bool
    {
        if ($e === $o + 1) {
            $g = $s->gapBefore[$e];
            if ($g === '' || ($s->gapFlags[$e] & (Gap::G_NL | Gap::G_CR | Gap::G_ODD)) === 0) {
                return false;
            }
            $p = self::pcs($g);
            $m = \count($p);
            for ($k = 0; $k < $m - 1; $k++) {
                if (Gap::isSpace($p[$k])) {
                    if (Gap::pieceHasR($p[$k])) {
                        return true;
                    }
                    break;
                }
            }
            for ($k = $m - 1; $k >= 1; $k--) {
                if (Gap::isSpace($p[$k])) {
                    return Gap::pieceHasR($p[$k]);
                }
            }
            return false;
        }
        $g = $s->gapBefore[$o + 1];
        if ($g !== '' && ($s->gapFlags[$o + 1] & (Gap::G_NL | Gap::G_CR | Gap::G_ODD)) !== 0) {
            foreach (self::pcs($g) as $x) {
                if (Gap::isSpace($x)) {
                    if (Gap::pieceHasR($x)) {
                        return true;
                    }
                    break;
                }
            }
        }
        $g = $s->gapBefore[$e];
        if ($g !== '' && ($s->gapFlags[$e] & (Gap::G_NL | Gap::G_CR | Gap::G_ODD)) !== 0) {
            $p = self::pcs($g);
            for ($k = \count($p) - 1; $k >= 0; $k--) {
                if (Gap::isSpace($p[$k])) {
                    return Gap::pieceHasR($p[$k]);
                }
            }
        }
        return false;
    }

    /** @return string[] */
    private static function pcs(string $g): array
    {
        if ($g === '') {
            return [];
        }
        return Gap::hasComment($g) ? Gap::pieces($g) : [$g];
    }

    private static function firstIsNewline(string $g): bool
    {
        $c = \ord($g[0]);
        if ($c === 35 || $c === 47) {
            return false;
        }
        $ws = \strcspn($g, '#/');
        $nl = \strpos($g, "\n");
        return $nl !== false && $nl < $ws;
    }

    /** @param string[] $p pieces; upstream's isCommentLastLineToken for the piece at $k */
    private static function commentLastLine(array $p, int $k): bool
    {
        if (!isset($p[$k + 1]) || Gap::isSpace($p[$k]) || !Gap::isSpace($p[$k + 1])) {
            return false;
        }
        $c = $p[$k + 1][0];
        return $c === "\n" || $c === "\r";
    }

    private function fixSpace(Source $s, int $c): void
    {
        $g = $s->gapBefore[$c];
        if ($g !== '' && !Gap::hasComment($g)) {
            $pk = $s->kind[$c - 1];
            if (!($pk === 44 && $s->ct[$c - 1] === 0) && ($this->afterHeredoc || $pk !== \__McTokId::T_END_HEREDOC)) {
                $this->put($s, $c, '', 1);
            }
        }
        $g = $s->gapBefore[$c + 1];
        if ($g === '') {
            $this->put($s, $c + 1, ' ');
            return;
        }
        if (!Gap::hasComment($g)) {
            $new = $g;
            if (!$this->keepMultiple || Gap::pieceHasR($g)) {
                $new = \ltrim($g, " \t");
            }
            $new = $new === '' ? ' ' : $new;
            if ($new !== $g) {
                $this->put($s, $c + 1, $new);
            }
            return;
        }
        $p = Gap::pieces($g);
        if (Gap::isSpace($p[0])) {
            $new = $p[0];
            if ((!$this->keepMultiple || Gap::pieceHasR($new)) && !self::commentLastLine($p, 1)) {
                $new = \ltrim($new, " \t");
            }
            $p[0] = $new === '' ? ' ' : $new;
            $out = \implode('', $p);
            if ($out !== $g) {
                $this->put($s, $c + 1, $out);
            }
            return;
        }
        if (!self::commentLastLine($p, 0)) {
            $this->put($s, $c + 1, ' ' . $g);
        }
    }

    private function ensureMultiline(Source $s, int $o, int $e): void
    {
        // the indentation of the line the call starts on: the nearest whitespace token before `(` that holds a "\n"
        $exist = null;
        $j = $o;
        while (true) {
            $g = $s->gapBefore[$j];
            if ($g !== '' && ($s->gapFlags[$j] & (Gap::G_NL | Gap::G_COMMENT)) !== 0) {
                $p = self::pcs($g);
                for ($k = \count($p) - 1; $k >= 0; $k--) {
                    if (Gap::isSpace($p[$k]) && \strpos($p[$k], "\n") !== false) {
                        $exist = \substr($p[$k], \strrpos($p[$k], "\n") + 1);
                        break 2;
                    }
                }
            }
            if ($j === 0) {
                $exist = '';
                break;
            }
            $t = $j - 1;
            $kd = $s->kind[$t];
            if (($kd === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $kd === \__McTokId::T_INLINE_HTML) && \strpos($s->text($t), "\n") !== false) {
                return;
            }
            $j = $t;
        }
        $indent = $exist . $this->indent;
        $le = $this->lineEnding;
        $p = self::pcs($s->gapBefore[$e]);
        $m = \count($p);
        if ($m > 0 && Gap::isSpace($p[$m - 1])) {
            $p[$m - 1] = $le . $exist;
        } else {
            $p[] = $le . $exist;
        }
        $this->put($s, $e, \implode('', $p));

        $r = $e - 1;
        while ($r > $o) {
            $c = $s->ct[$r];
            $k = $s->kind[$r];
            if ($c === 0) {
                if ($k === 41 || $k === 125) {
                    $r = $s->pair[$r] - 1;
                    continue;
                }
            } elseif ($c === CT::ARRAY_BRACKET_CLOSE) {
                $r = $s->pair[$r] - 1;
                continue;
            }
            if ($c === CT::ATTRIBUTE_CLOSE || ($c === 0 && $k === 44)) {
                if ($s->kind[$r + 1] === 41 && $s->ct[$r + 1] === 0) {
                    $r--;
                    continue;
                }
                if ($c === CT::ATTRIBUTE_CLOSE) {
                    if ($this->standaloneAttributes) {
                        $this->fixNewline($s, $r, $le . $indent);
                    }
                    $r = $s->pair[$r] - 1;
                    continue;
                }
                $this->fixNewline($s, $r, $le . $indent);
            }
            $r--;
        }
        $this->fixNewline($s, $o, $le . $indent);
    }

    /** upstream fixNewline: a line break plus indentation after the real token $r (comma, attribute close or `(`) */
    private function fixNewline(Source $s, int $r, string $ws): void
    {
        $p1 = self::pcs($s->gapBefore[$r + 1]);
        $c1 = \count($p1);
        if ($c1 > 0 && !Gap::isSpace($p1[0])) {
            return; // a comment directly after
        }
        // the item two after $r: a piece of this gap, or of the next one when this gap is empty
        $x = $r + 1;
        $kb = -1;
        $p2 = $p1;
        if ($c1 > 1) {
            $kb = 1;
        } elseif ($c1 === 1) {
            $kb = -1;
        } else {
            $p2 = self::pcs($s->gapBefore[$r + 2]);
            $x = $r + 2;
            $kb = $p2 !== [] ? 0 : -1;
        }
        if ($kb >= 0 && !Gap::isSpace($p2[$kb])) {
            // tokens[idx + 2] is a comment: the whitespace before the next meaningful token must be a line break
            $m = \count($p2);
            $z = $p2[$m - 1];
            if (Gap::isSpace($z) && \strpos($z, "\n") !== false) {
                return;
            }
            $clr = 0;
            if (Gap::isSpace($z)) {
                $p2[$m - 1] = $ws;
                $clr = 1; // upstream clears the old whitespace token and inserts a new one
            } else {
                $p2[] = $ws;
            }
            $this->put($s, $x, \implode('', $p2), $clr);
            return;
        }
        if ($s->kind[$r + 1] === 41 && $s->ct[$r + 1] === 0) {
            return;
        }
        if ($c1 > 0) {
            $p1[0] = $ws;
            $this->put($s, $r + 1, \implode('', $p1));
        } else {
            $this->put($s, $r + 1, $ws);
        }
    }
}
