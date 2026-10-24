<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\FunctionDeclarationFixer (priority 31), a file rule: it rewrites the gaps
 * around a `function` / `fn` keyword, its parameter parentheses (blanks inside them, before `(` of a named function), the `{` /
 * `=>` that follows, and the `use (...)` of a closure. Upstream walks the keywords from the end; so does fixFile.
 * trailing_comma_single_line=false would DELETE a trailing comma of a single-line signature (a token): that file is reported
 * through Source::$unsupported and left alone (needs the edit phase).
 */
final class FunctionDeclaration extends GapRule
{
    public function __construct(private bool $closureFunctionOne = true, private bool $closureFnOne = true, private bool $trailingCommaSingleLine = false) {}

    public function name(): string
    {
        return 'function_declaration';
    }

    public function priority(): int
    {
        return 31;
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
            $k = $s->kind[$j];
            if (($k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_FN) && $s->ct[$j] === 0) {
                return true;
            }
        }
        return false;
    }

    private static function blank(string $t): bool
    {
        return \strspn($t, " \t") === \strlen($t);
    }

    /** upstream fixParenthesisInnerEdge: blank-only whitespace tokens right behind `(` and right before `)` go */
    private static function innerEdge(Source $s, int $open, int $close): void
    {
        $g = $s->gapBefore[$close];
        $t = Gap::trail($g);
        if ($t !== '' && self::blank($t)) {
            $s->setGap($close, Gap::withTrail($g, ''));
        }
        $g = $s->gapBefore[$open + 1];
        $t = Gap::lead($g);
        if ($t !== '' && self::blank($t)) {
            $s->setGap($open + 1, \substr($g, \strlen($t)));
        }
    }

    /** Tokens::isPartialCodeMultiline over the tokens (and the gaps between them) from $a to $b */
    private static function multiline(Source $s, int $a, int $b): bool
    {
        for ($j = $a; $j <= $b; $j++) {
            if ($j > $a && ($s->gapFlags[$j] & Gap::G_NL) !== 0 && self::gapTokenHasNl($s->gapBefore[$j])) {
                return true;
            }
            if (\strpos($s->text($j), "\n") !== false) {
                return true;
            }
        }
        return false;
    }

    /** a trivia TOKEN holds a "\n": whitespace, or a comment token (a block comment may span lines) */
    private static function gapTokenHasNl(string $g): bool
    {
        return \strpos($g, "\n") !== false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($f = $n - 1; $f >= 0; $f--) {
            $fk = $s->kind[$f];
            if (($fk !== \__McTokId::T_FUNCTION && $fk !== \__McTokId::T_FN) || $s->ct[$f] !== 0) {
                continue;
            }
            $start = -1;
            for ($j = $f + 1; $j < $n; $j++) {
                $k = $s->kind[$j];
                if ($k === 40 && $s->ct[$j] === 0) {
                    $start = $j;
                    break;
                }
                if ($k === 59 || $k === \__McTokId::T_CLOSE_TAG) {
                    break;
                }
            }
            if ($start < 0) {
                continue;
            }
            $end = $s->pair[$start];
            if (!$this->trailingCommaSingleLine && $s->kind[$end - 1] === 44 && $s->ct[$end - 1] === 0 && !self::multiline($s, $f, $end)) {
                $s->unsupported = 'function_declaration: trailing comma in a single-line signature (needs the edit phase)';
                return;
            }
            $brace = -1;
            for ($j = $end + 1; $j < $n; $j++) {
                $k = $s->kind[$j];
                if (($k === 59 || $k === 123 || $k === \__McTokId::T_DOUBLE_ARROW) && $s->ct[$j] === 0) {
                    $brace = $j;
                    break;
                }
            }
            if ($brace >= 0 && $s->kind[$brace] !== 59) {
                $g = $s->gapBefore[$brace];
                $t = Gap::trail($g);
                if ($t === '') {
                    $s->setGap($brace, $g . ' ');
                } elseif (self::blank($t)) {
                    $s->setGap($brace, Gap::withTrail($g, ' '));
                }
            }
            $after = $end + 1;
            if ($after < $n && $s->ct[$after] === CT::USE_LAMBDA && !Gap::hasComment($s->gapBefore[$after])) {
                $g = $s->gapBefore[$after + 1];
                $s->setGap($after + 1, Gap::withLead($g, ' '));
                $us = $after + 1;
                $ue = $s->pair[$us];
                if (!$this->trailingCommaSingleLine && $s->kind[$ue - 1] === 44 && $s->ct[$ue - 1] === 0 && !self::multiline($s, $f, $ue)) {
                    $s->unsupported = 'function_declaration: trailing comma in a single-line use list (needs the edit phase)';
                    return;
                }
                self::innerEdge($s, $us, $ue);
                $s->setGap($after, ' ');
            }
            self::innerEdge($s, $start, $end);
            $lambda = Analyzer::isLambda($s, $f);
            if (!$lambda && $s->gapBefore[$start] !== '' && !Gap::hasComment($s->gapBefore[$start])) {
                $s->setGap($start, '');
            }
            $one = $fk === \__McTokId::T_FN ? $this->closureFnOne : $this->closureFunctionOne;
            $g = $s->gapBefore[$f + 1];
            if ($lambda && !$one) {
                if (Gap::lead($g) !== '') {
                    $s->setGap($f + 1, Gap::withLead($g, ''));
                }
            } else {
                $s->setGap($f + 1, Gap::withLead($g, ' '));
            }
            if ($lambda && $f > 0 && $s->kind[$f - 1] === \__McTokId::T_STATIC && $s->ct[$f - 1] === 0) {
                $s->setGap($f, Gap::withLead($s->gapBefore[$f], ' '));
            }
        }
    }
}
