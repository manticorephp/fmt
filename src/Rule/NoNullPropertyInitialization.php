<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\NoNullPropertyInitializationFixer (priority 0). In a class / trait body (directly, not in a method),
 * `public $a = null;` -> `public $a;`: the `=` and the `null` (any case, `\null` too) are cleared together with the blanks between them, comments and
 * whitespace with a line break stay. Typed properties are not touched (the type stops the scan); a list `$a = null, $b = null` goes on only when
 * a comma directly follows a `null`.
 */
final class NoNullPropertyInitialization extends EditRule
{
    public function name(): string
    {
        return 'no_null_property_initialization';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        $c = false;
        $v = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            $c = $c || $k === \__McTokId::T_CLASS || $k === \__McTokId::T_TRAIT;
            $v = $v || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_VAR || $k === \__McTokId::T_STATIC;
        }
        return $c && $v;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $in = [];
        $level = 0;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            $plain = $s->ct[$i] === 0;
            if ($plain && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_TRAIT)) {
                $level++;
                $in[$level] = 1;
                do {
                    $i++;
                } while ($i < $n && !($s->kind[$i] === 123 && $s->ct[$i] === 0));
                continue;
            }
            if ($level === 0) {
                continue;
            }
            if ($plain && $k === 123) {
                $in[$level]++;
                continue;
            }
            if ($plain && $k === 125) {
                $in[$level]--;
                if ($in[$level] === 0) {
                    unset($in[$level]);
                    $level--;
                }
                continue;
            }
            if ($in[$level] !== 1) {
                continue;
            }
            if (!$plain || !($k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_VAR || $k === \__McTokId::T_STATIC)) {
                continue;
            }
            while (true) {
                $i++;
                $v = $i;
                if ($i < $n && $s->kind[$i] === \__McTokId::T_STATIC && $s->ct[$i] === 0) {
                    $i++;
                    $v = $i;
                }
                if ($i >= $n || $s->kind[$i] !== \__McTokId::T_VARIABLE || $s->ct[$i] !== 0) {
                    break;
                }
                $i++;
                if ($i < $n && $s->kind[$i] === 61 && $s->ct[$i] === 0) {
                    $i++;
                    if ($i < $n && $s->kind[$i] === \__McTokId::T_NS_SEPARATOR) {
                        $i++;
                    }
                    if ($i < $n && $s->kind[$i] === \__McTokId::T_STRING && $s->ct[$i] === 0 && $s->end[$i] - $s->start[$i] === 4 && \strtolower($s->text($i)) === 'null') {
                        $this->clear($s, $v, $i);
                        if ($i + 1 < $n && !(($s->kind[$i + 1] === 44 || $s->kind[$i + 1] === 59) && $s->ct[$i + 1] === 0)) {
                            $s->abort = true; // `public $a = null ?? 1;` loses its `=`: upstream's lint step rejects the result
                        }
                    }
                    // upstream steps one element on: a comma must follow the `null` directly
                    if ($i + 1 < $n && $s->gapBefore[$i + 1] !== '') {
                        break;
                    }
                    $i++;
                }
                if ($i >= $n || $s->kind[$i] !== 44 || $s->ct[$i] !== 0) {
                    break;
                }
            }
        }
    }

    private function clear(Source $s, int $v, int $nullTok): void
    {
        $out = '';
        $split = -1;
        $lastWs = false;
        for ($k = $v + 1; $k <= $nullTok; $k++) {
            $g = $s->gapBefore[$k];
            if ($g === '') {
                $lastWs = false;
                continue;
            }
            foreach (Trivia::pieces($g) as $p) {
                if (Gap::isSpace($p)) {
                    if (\str_contains($p, "\n")) {
                        if ($lastWs && $split < 0) {
                            $split = \strlen($out);
                        }
                        $out .= $p;
                        $lastWs = true;
                    }
                } else {
                    $out .= $p;
                    $lastWs = false;
                }
            }
        }
        $s->edit(Source::gapAt($v + 1), Source::tokAt($nullTok) + 1, $out, $split);
    }
}
