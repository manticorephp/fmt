<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\PhpTag\FullOpeningTagFixer (priority 98). Upstream replaces `<?` + optional mixed-case `php` + a blank
 * in the whole file, re-lexes, and puts the replaced text back everywhere but in the open tags: net effect, an open tag spelled
 * `<?PHP`, `<?Php` ... becomes `<?php` (a text rule). A short `<?` that the lexer took for inline HTML would become an open tag
 * (a new token): such a file is reported through Source::$unsupported (needs the edit phase).
 */
final class FullOpeningTag extends GapRule
{
    public function name(): string
    {
        return 'full_opening_tag';
    }

    public function priority(): int
    {
        return 98;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_TEXT;
    }

    public function writes(): int
    {
        return self::F_TEXT;
    }

    public function prepare(Source $s): bool
    {
        $n = $s->n;
        for ($j = 0; $j < $n; $j++) {
            if ($s->kind[$j] === \__McTokId::T_INLINE_HTML
                && \preg_match('/<\?(?:phP|pHp|pHP|Php|PhP|PHp|PHP)?(\s|$)/', $s->text($j)) === 1) {
                $s->unsupported = 'full_opening_tag: a short open tag inside inline HTML (needs the edit phase)';
                return false;
            }
        }
        return true;
    }

    public function usesText(Source $s): bool
    {
        return true;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_OPEN_TAG];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_OPEN_TAG) {
            return null;
        }
        $t = $s->text($i);
        if (\strlen($t) < 5 || \strncmp($t, '<?php', 5) === 0 || \strcasecmp(\substr($t, 0, 5), '<?php') !== 0) {
            return null;
        }
        return '<?php' . \substr($t, 5);
    }
}
