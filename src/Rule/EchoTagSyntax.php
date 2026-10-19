<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\PhpTag\EchoTagSyntaxFixer, format=long (priority 0). `<?=` becomes `<?php echo` (or `print`), plus a space when no
 * whitespace token follows. format=short is refused (Rules).
 */
final class EchoTagSyntax extends EditRule
{
    public function __construct(private string $fn)
    {
    }

    public function name(): string
    {
        return 'echo_tag_syntax';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        for ($i = 0; $i + 1 < $s->n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                continue;
            }
            $g = $s->gapBefore[$i + 1];
            $ws = $g !== '' && $g[0] !== '#' && $g[0] !== '/';
            $s->edit(Source::tokAt($i), Source::tokAt($i) + 1, '<?php ' . $this->fn . ($ws ? '' : ' '));
        }
    }
}
