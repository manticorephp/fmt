<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\StringNotation\HeredocClosingMarkerFixer (priority 0). Every heredoc / nowdoc marker (opening and closing token) becomes the
 * configured one unless it is a reserved marker (kept, as spelled in the list; trailing `_` are peeled off to find it); `_` is appended while the body holds a line starting with the marker.
 */
final class HeredocClosingMarker extends GapRule
{
    /** @param string[] $reserved */
    public function __construct(private string $marker, private array $reserved, private bool $explicit) {}

    public function name(): string
    {
        return 'heredoc_closing_marker';
    }

    public function priority(): int
    {
        return 0;
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
        return self::F_TEXT;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_START_HEREDOC) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $upper = [];
        $original = [];
        foreach ($this->reserved as $v) {
            $key = \mb_strtoupper($v);
            $at = \array_search($key, $upper, true);
            if ($at === false) {
                $upper[] = $key;
                $original[] = $v;
            } else {
                $original[(int) $at] = $v;
            }
        }
        $start = -1;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_START_HEREDOC) {
                $start = $i;
                continue;
            }
            if ($start < 0 || $k !== \__McTokId::T_END_HEREDOC) {
                continue;
            }
            $existing = \mb_strtoupper(\trim($s->text($i)));
            $new = null;
            do {
                $at = \array_search($existing, $upper, true);
                $new = $at === false ? null : $original[(int) $at];
                if (!\str_ends_with($existing, '_')) {
                    break;
                }
                $existing = \substr($existing, 0, -1);
            } while ($new === null);
            if ($new === null) {
                $new = $this->marker;
            }
            $content = '';
            for ($j = $start + 1; $j <= $i; $j++) {
                $content .= $s->gapBefore[$j];
                if ($j < $i) {
                    $content .= $s->text($j);
                }
            }
            while (Preg::match('~(^|[\r\n])\s*' . \preg_quote($new, '~') . '(?!\w)~', $content)) {
                $new .= '_';
            }
            $startText = $s->text($start);
            $isNowdoc = \str_contains($startText, '\'');
            $quote = $isNowdoc ? '\'' : ($this->explicit ? '"' : '');
            $s->setText($start, Preg::replace('/<<<\h*\K["\']?[^\s"\']+["\']?/', $quote . $new . $quote, $startText));
            $s->setText($i, Preg::replace('/\S+/', $new, $s->text($i)));
            $start = -1;
        }
    }
}
