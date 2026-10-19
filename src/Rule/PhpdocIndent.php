<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Gap;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocIndentFixer (priority 20): a docblock takes the indentation of the code after it.
 * All docblocks of a gap share the next code token and the whitespace before it; upstream walks backwards, so the gap's docblocks are
 * done last to first. A docblock right behind a comment or a code token gets the indentation inserted (upstream's insertAt).
 */
final class PhpdocIndent extends DocRule
{
    public function name(): string
    {
        return 'phpdoc_indent';
    }

    public function priority(): int
    {
        return 20;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB_OPEN;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        return $doc;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if (($s->gapFlags[$i] & Gap::G_DOC) === 0 || $i >= $s->n) {
            return $gap;
        }
        if ($s->kind[$i] === 125 && $s->ct[$i] === 0) {
            return $gap; // the next meaningful token is `}`
        }
        $p = Gap::pieces($gap);
        $m = \count($p);
        $last = $p[$m - 1];
        $indent = '';
        if (Gap::isSpace($last)) {
            $w = \str_replace(["\r\n", "\r"], "\n", $last);
            $r = \strrchr($w, "\n");
            $indent = $r === false ? '' : \ltrim($r, "\n");
        }
        $changed = false;
        for ($k = $m - 1; $k >= 0; $k--) {
            $x = $p[$k];
            if (!Gap::isDoc($x)) {
                continue;
            }
            if ($k === 0) {
                $prevKind = $i > 0 ? $s->kind[$i - 1] : 0;
                if ($prevKind === \__McTokId::T_OPEN_TAG || $prevKind === 59 || $prevKind === 44
                    || (($prevKind === 123 || $prevKind === 40) && $s->ct[$i - 1] === 0)) {
                    continue;
                }
                $prevWs = false;
            } else {
                $prevWs = Gap::isSpace($p[$k - 1]);
                if ($prevWs && \strspn($p[$k - 1], " \t") === \strlen($p[$k - 1])
                    && !($k === 1 && $i > 0 && $s->kind[$i - 1] === \__McTokId::T_OPEN_TAG)) {
                    continue;
                }
            }
            $new = \ltrim(Preg::replace('/^\h*\*/m', $indent . ' *', $x));
            if ($prevWs) {
                $p[$k - 1] = \rtrim($p[$k - 1], " \t") . $indent;
                $p[$k] = $new;
            } else {
                $p[$k] = $indent . $new;
            }
            $changed = true;
        }
        return $changed ? \implode('', $p) : $gap;
    }
}
