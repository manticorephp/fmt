<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer (priority 1). A file rule: whether the file has a line break
 * (a whitespace token or the open tag) is a decision over the whole file as the earlier rules left it. Only for a monolithic PHP
 * file (one open tag that is not `<?=`, no inline HTML but a shebang line): the open tag ends in a line break and the whitespace
 * token behind it starts with one (a "\n" is inserted ahead of it, or as a token of its own).
 */
final class BlankLineAfterOpeningTag extends GapRule
{
    public function __construct(private string $le = "\n")
    {
    }

    public function name(): string
    {
        return 'blank_line_after_opening_tag';
    }

    public function priority(): int
    {
        return 1;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_TEXT;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $opens = 0;
        $html = 0;
        $open = -1;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_OPEN_TAG) {
                $opens++;
                $open = $i;
            } elseif ($k === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                return;
            } elseif ($k === \__McTokId::T_INLINE_HTML) {
                $html++;
            }
        }
        if ($opens !== 1 || $html > 1 || ($html === 1 && ($s->kind[0] !== \__McTokId::T_INLINE_HTML || \preg_match('/^#!.+$/', $s->text(0)) !== 1))) {
            return;
        }
        $found = \strpos($s->text($open), "\n") !== false;
        for ($i = 0; $i <= $n && !$found; $i++) {
            $f = $s->gapFlags[$i];
            if (($f & Gap::G_NL) === 0) {
                continue;
            }
            if (($f & Gap::G_COMMENT) === 0) {
                $found = true;
                continue;
            }
            foreach (Gap::pieces($s->gapBefore[$i]) as $p) {
                if (Gap::isSpace($p) && \strpos($p, "\n") !== false) {
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            return;
        }
        $t = $s->text($open);
        if (\strpos($t, "\n") === false) {
            $s->setText($open, \rtrim($t) . $this->le);
        }
        $g = $s->gapBefore[$open + 1];
        if ($g === '') {
            if ($open + 1 < $n) {
                $s->setGap($open + 1, $this->le);
            }
            return;
        }
        $lead = Gap::lead($g);
        if ($lead === '') {
            $s->setGap($open + 1, "\n" . $g);
        } elseif (\strpos($lead, "\n") === false) {
            $s->setGap($open + 1, "\n" . $g);
        }
    }
}
