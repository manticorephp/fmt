<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpTag\LinebreakAfterOpeningTagFixer (priority 0). In a monolithic PHP file without `<?=` and with a
 * line break somewhere in a whitespace token, an open tag without one gets `rtrim(tag) . lineEnding`. Decided in prepare(); text rule.
 */
final class LinebreakAfterOpeningTag extends TextRule
{
    private int $tag = -1;

    public function __construct(private string $lineEnding) {}

    public function name(): string
    {
        return 'linebreak_after_opening_tag';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        $this->tag = -1;
        $n = $s->n;
        if ($n === 0) {
            return false;
        }
        $open = 0;
        $html = 0;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_OPEN_TAG) {
                $open++;
            } elseif ($k === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                return false;
            } elseif ($k === \__McTokId::T_INLINE_HTML) {
                $html++;
            }
        }
        if ($open !== 1 || $html > 1 || ($html === 1 && \preg_match('/^#!.+$/', $s->text(0)) !== 1)) {
            return false;
        }
        $t = $s->kind[0] === \__McTokId::T_INLINE_HTML ? 1 : 0;
        if ($t >= $n || \str_contains($s->text($t), "\n")) {
            return false;
        }
        for ($i = 0; $i <= $n; $i++) {
            if (($s->gapFlags[$i] & Gap::G_NL) === 0) {
                continue;
            }
            $g = $s->gapBefore[$i];
            if (!Gap::hasComment($g)) {
                $this->tag = $t;
                return true;
            }
            foreach (Gap::pieces($g) as $p) {
                if (Gap::isSpace($p) && \str_contains($p, "\n")) {
                    $this->tag = $t;
                    return true;
                }
            }
        }
        return false;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_OPEN_TAG];
    }

    public function fixText(Source $s, int $i): ?string
    {
        return $i === $this->tag ? \rtrim($s->text($i)) . $this->lineEnding : null;
    }
}
