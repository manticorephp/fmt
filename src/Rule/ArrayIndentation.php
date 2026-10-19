<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\ArrayIndentationFixer (priority 29), a file rule. Upstream walks every token (trivia
 * included) with a stack of scopes (array / expression) and rewrites each whitespace token holding a line break. The walk here
 * is the same, gap by gap: outside a scope nothing happens (the indent of the line an array starts on is looked up when the
 * array opens), inside one the whitespace pieces with a line break of gap t are rewritten before token t is looked at.
 * Scope ends are code token indexes (the closing bracket, the last token of an expression).
 */
final class ArrayIndentation extends GapRule
{
    /** @var int[] 0 = array, 1 = expression */
    private array $stype = [];
    /** @var int[] code token index that ends the scope */
    private array $send = [];
    /** @var string[] */
    private array $sinit = [];
    /** @var string[] */
    private array $snew = [];
    private int $sp = 0;
    private string $pli = '';
    private string $pln = '';

    public function __construct(private string $indent = '    ')
    {
    }

    public function name(): string
    {
        return 'array_indentation';
    }

    public function priority(): int
    {
        return 29;
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
            $c = $s->ct[$j];
            if ($c === CT::ARRAY_BRACKET_OPEN || $c === CT::DESTRUCTURING_BRACKET_OPEN) {
                return true;
            }
            $k = $s->kind[$j];
            if ($c === 0 && ($k === \__McTokId::T_ARRAY || $k === \__McTokId::T_LIST)) {
                return true;
            }
        }
        return false;
    }

    private static function isOpener(Source $s, int $t): bool
    {
        $c = $s->ct[$t];
        if ($c === CT::ARRAY_BRACKET_OPEN || $c === CT::DESTRUCTURING_BRACKET_OPEN) {
            return true;
        }
        if ($c !== 0 || $s->kind[$t] !== 40 || $t === 0) {
            return false;
        }
        $pk = $s->kind[$t - 1];
        return $s->ct[$t - 1] === 0 && ($pk === \__McTokId::T_ARRAY || $pk === \__McTokId::T_LIST);
    }

    private function push(int $type, int $end, string $init, string $new): void
    {
        $this->stype[$this->sp] = $type;
        $this->send[$this->sp] = $end;
        $this->sinit[$this->sp] = $init;
        $this->snew[$this->sp] = $new;
        $this->sp++;
    }

    private static function isTag(int $k): bool
    {
        return $k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_CLOSE_TAG;
    }

    /** IndentationTrait::computeNewLineContent for a trivia piece / inline html behind a tag */
    private static function tagPrefix(Source $s, int $tagIdx): string
    {
        return \preg_replace('/\S/', '', $s->text($tagIdx)) ?? '';
    }

    /** The indent of the nearest newline token (IndentationTrait::isNewLineToken) before token $t, '' when there is none. */
    private static function indentBefore(Source $s, int $t): string
    {
        for ($g = $t; $g >= 0; $g--) {
            $gap = $s->gapBefore[$g];
            if ($gap !== '') {
                $p = Gap::hasComment($gap) ? Gap::pieces($gap) : [$gap];
                for ($k = \count($p) - 1; $k >= 0; $k--) {
                    if (!Gap::isSpace($p[$k])) {
                        continue;
                    }
                    $content = $p[$k];
                    if ($k === 0 && $g > 0 && self::isTag($s->kind[$g - 1])) {
                        $content = self::tagPrefix($s, $g - 1) . $content;
                    }
                    if (Gap::pieceHasR($content)) {
                        return Analyzer::extractIndent($content);
                    }
                }
            }
            if ($g === 0) {
                break;
            }
            $kk = $s->kind[$g - 1];
            if ($kk === \__McTokId::T_INLINE_HTML) {
                $content = $s->text($g - 1);
                if ($g >= 2 && $s->gapBefore[$g - 1] === '' && self::isTag($s->kind[$g - 2])) {
                    $content = self::tagPrefix($s, $g - 2) . $content;
                }
                if (Gap::pieceHasR($content)) {
                    return Analyzer::extractIndent($content);
                }
            } elseif ($kk === \__McTokId::T_OPEN_TAG) {
                $text = $s->text($g - 1);
                $nextIsWs = $gap !== '' && Gap::isSpace(Gap::hasComment($gap) ? Gap::pieces($gap)[0] : $gap);
                if (!$nextIsWs && Gap::pieceHasR($text)) {
                    return Analyzer::extractIndent($text);
                }
            }
        }
        return '';
    }

    private static function preg(string $pattern, string $replacement, string $subject): string
    {
        $r = \preg_replace($pattern . 'u', $replacement, $subject);
        if ($r === null || \preg_last_error() !== \PREG_NO_ERROR) {
            $r = \preg_replace($pattern, $replacement, $subject);
        }
        return (string) $r;
    }

    /** @param string[] $p pieces of gap $t; $k = index of the whitespace piece being rewritten */
    private function rewrite(Source $s, int $t, array $p, int $k): string
    {
        $cur = $this->sp - 1;
        $content = $p[$k];
        if ($this->stype[$cur] === 0) {
            $ind = $t < $s->n && $t < $this->send[$cur];
            if (!$ind) {
                for ($q = $k + 1; $q < \count($p); $q++) {
                    if (Gap::isSpace($p[$q]) && Gap::pieceHasR($p[$q])) {
                        $ind = true;
                        break;
                    }
                }
            }
            $new = self::preg('/(\R+)\h*$/', '${1}' . $this->sinit[$cur] . ($ind ? $this->indent : ''), $content);
            $this->pli = Analyzer::extractIndent($content);
            $this->pln = Analyzer::extractIndent($new);
            return $new;
        }
        return self::preg('/(\R)' . \preg_quote($this->sinit[$cur], '/') . '(\h*)$/', '${1}' . $this->snew[$cur] . '${2}', $content);
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $this->sp = 0;
        $this->pli = '';
        $this->pln = '';
        for ($t = 0; $t < $n; $t++) {
            if ($this->sp > 0 && ($s->gapFlags[$t] & (Gap::G_NL | Gap::G_CR | Gap::G_ODD)) !== 0) {
                $g = $s->gapBefore[$t];
                $p = Gap::hasComment($g) ? Gap::pieces($g) : [$g];
                $changed = false;
                $m = \count($p);
                for ($k = 0; $k < $m; $k++) {
                    if (!Gap::isSpace($p[$k]) || !Gap::pieceHasR($p[$k])) {
                        continue;
                    }
                    $new = $this->rewrite($s, $t, $p, $k);
                    if ($new !== $p[$k]) {
                        $p[$k] = $new;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $s->setGap($t, \implode('', $p));
                }
            }
            if (self::isOpener($s, $t)) {
                $this->push(0, $s->pair[$t], self::indentBefore($s, $t), '');
                continue;
            }
            if ($this->sp === 0) {
                continue;
            }
            $cur = $this->sp - 1;
            if ($t === $this->send[$cur]) {
                while ($this->sp > 0 && $t === $this->send[$this->sp - 1]) {
                    $this->sp--;
                }
                continue;
            }
            if ($s->kind[$t] === 44 && $s->ct[$t] === 0) {
                continue;
            }
            if ($this->stype[$cur] !== 1) {
                $e = $this->expressionEnd($s, $t, $this->send[$cur]);
                if ($e === $t) {
                    continue;
                }
                $this->push(1, $e, $this->pli, $this->pln);
            }
        }
    }

    private function expressionEnd(Source $s, int $t, int $parentEnd): int
    {
        for ($j = $t + 1; $j < $parentEnd; $j++) {
            $c = $s->ct[$j];
            $k = $s->kind[$j];
            if (($c === 0 && ($k === 40 || $k === 123)) || $c === CT::ARRAY_BRACKET_OPEN || $c === CT::DESTRUCTURING_BRACKET_OPEN) {
                $j = $s->pair[$j];
                continue;
            }
            if ($k === 44 && $c === 0) {
                return $j - 1;
            }
        }
        return $parentEnd - 1;
    }
}
