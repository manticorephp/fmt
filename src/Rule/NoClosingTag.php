<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Analyzer;
use Fmt\Gap;
use Fmt\Structure;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\PhpTag\NoClosingTagFixer (priority 0). In a monolithic PHP file (one open tag, no inline HTML but a
 * `#!` line) the first `?>` goes with the whitespace right before it, and a `;` is inserted after the last code token unless it is
 * `;`, a plain `}` or the open tag.
 */
final class NoClosingTag extends EditRule
{
    public function name(): string
    {
        return 'no_closing_tag';
    }

    public function priority(): int
    {
        return 0;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $open = 0;
        $html = 0;
        $close = -1;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                $open++;
            } elseif ($k === \__McTokId::T_INLINE_HTML) {
                $html++;
            } elseif ($k === \__McTokId::T_CLOSE_TAG && $close < 0) {
                $close = $i;
            }
        }
        if ($close < 1 || $open !== 1) {
            return;
        }
        if ($html > 1 || ($html === 1 && ($s->kind[0] !== \__McTokId::T_INLINE_HTML || \preg_match('/^#!.+$/', $s->text(0)) !== 1))) {
            return;
        }
        $g = $s->gapBefore[$close];
        if ($g !== '') {
            $p = Gap::hasComment($g) ? Gap::pieces($g) : [$g];
            if (Gap::isSpace($p[\count($p) - 1])) {
                \array_pop($p);
                $g = \implode('', $p);
            }
        }
        $pk = $s->kind[$close - 1];
        $semi = ($pk === 59 || ($pk === 125 && $s->ct[$close - 1] === 0) || $pk === \__McTokId::T_OPEN_TAG) ? '' : ';';
        if ($semi === '' && $pk === 125) {
            $ow = $s->structure()->owner[$close - 1];
            $o = $s->pair[$close - 1];
            if ($ow === Structure::OW_CLOSURE_BODY || $ow === Structure::OW_MATCH || ($ow === Structure::OW_CLASS_BODY && self::anonymous($s, $o))) {
                $s->abort = true; // an expression ends without `;`: upstream's result does not lint
                return;
            }
        }
        $s->edit(Source::gapAt($close), Source::tokAt($close) + 1, $semi . $g);
    }

    /** the class body opened at $o belongs to `new class` */
    private static function anonymous(Source $s, int $o): bool
    {
        for ($j = $o - 1; $j >= 0; $j--) {
            $k = $s->kind[$j];
            if ($k === \__McTokId::T_CLASS) {
                return Analyzer::isAnonymousClass($s, $j);
            }
            if ($k === 59 || $k === 123 || $k === 125) {
                return false;
            }
        }
        return false;
    }
}
