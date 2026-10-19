<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\SingleLineAfterImportsFixer (priority -11), a file rule walking the `use` tokens in order
 * (each import sees the gap the previous one left). Behind the `;`: a same-line comment is skipped (after a blank-only whitespace
 * token), then the whitespace token there is rewritten to "\n" + indent (+ one more "\n" unless the next statement is a `use`), or
 * one is inserted. A `use ... ?>` (upstream inserts a `;` token) is reported through Source::$unsupported.
 */
final class SingleLineAfterImports extends GapRule
{
    public function name(): string
    {
        return 'single_line_after_imports';
    }

    public function priority(): int
    {
        return -11;
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
            if ($s->kind[$j] === \__McTokId::T_USE && $s->ct[$j] === 0) {
                return true;
            }
        }
        return false;
    }

    private static function blank(string $t): bool
    {
        return \strspn($t, " \t") === \strlen($t);
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($u = 1; $u < $n; $u++) {
            if ($s->kind[$u] !== \__McTokId::T_USE || $s->ct[$u] !== 0) {
                continue;
            }
            $indent = '';
            $g = $s->gapBefore[$u];
            if ($g !== '') {
                $p = Gap::hasComment($g) ? Gap::pieces($g) : [$g];
                $m = \count($p);
                $last = $p[$m - 1];
                if (Gap::isSpace($last)) {
                    $prevIsComment = $m >= 2 && self::isPlainComment($p[$m - 2]);
                    if (self::blank($last) && $prevIsComment) {
                        $indent = $last;
                    } else {
                        $str = \strrchr(\str_replace(["\r\n", "\r"], "\n", $last), "\n");
                        $indent = $str === false ? '' : \ltrim($str, "\n");
                    }
                }
            }
            $sem = -1;
            for ($j = $u + 1; $j < $n; $j++) {
                if ($s->kind[$j] === \__McTokId::T_CLOSE_TAG) {
                    $s->unsupported = 'single_line_after_imports: `use` ended by a close tag (needs the edit phase)';
                    return;
                }
                if ($s->kind[$j] === 59 && $s->ct[$j] === 0) {
                    $sem = $j;
                    break;
                }
            }
            if ($sem < 0) {
                continue;
            }
            $gi = $sem + 1;
            $g = $s->gapBefore[$gi];
            if ($sem === $n - 1 && $g === '') {
                $s->setGap($gi, "\n\n" . $indent);
                continue;
            }
            $p = $g === '' ? [] : (Gap::hasComment($g) ? Gap::pieces($g) : [$g]);
            $m = \count($p);
            $k = 0;
            if ($m > 1 && Gap::isSpace($p[0]) && self::blank($p[0]) && !Gap::isSpace($p[1])) {
                $k = 1;
            }
            if ($k < $m && !Gap::isSpace($p[$k])) {
                $k++;
            }
            if ($gi === $n && (($m === 1 && Gap::isSpace($p[0]) && self::blank($p[0])) || $k >= $m)) {
                $s->abort = true; // upstream reads a token past the end of the file and throws: the file stays as it is
                return;
            }
            $nextUse = $gi < $n && $s->kind[$gi] === \__McTokId::T_USE && $s->ct[$gi] === 0;
            $newline = $nextUse ? "\n" : "\n\n";
            if ($k < $m && Gap::isSpace($p[$k])) {
                $c = $p[$k];
                if (\substr_count($c, "\n") === 2) {
                    continue;
                }
                if (!$nextUse || \substr_count($c, "\n") < 1) {
                    $p[$k] = $newline . $indent . \ltrim($c);
                    $s->setGap($gi, \implode('', $p));
                }
                continue;
            }
            \array_splice($p, $k, 0, [$newline . $indent]);
            $s->setGap($gi, \implode('', $p));
        }
    }

    /** a T_COMMENT (not a doc comment) piece */
    private static function isPlainComment(string $p): bool
    {
        if (Gap::isSpace($p)) {
            return false;
        }
        return !(\strlen($p) >= 4 && \substr($p, 0, 3) === '/**' && ($p[3] === ' ' || $p[3] === "\t" || $p[3] === "\n" || $p[3] === "\r"));
    }
}
