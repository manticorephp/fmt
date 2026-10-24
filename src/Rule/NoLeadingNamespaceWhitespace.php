<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\NamespaceNotation\NoLeadingNamespaceWhitespaceFixer (priority 0). The whitespace token before a
 * `namespace` declaration keeps only what ends at its last line break; with none, it goes if the token before ends with whitespace
 * and becomes one space otherwise; no whitespace token at all: a line break is added unless the previous token ends with whitespace.
 */
final class NoLeadingNamespaceWhitespace extends GapRule
{
    public function __construct(private string $lineEnding) {}

    public function name(): string
    {
        return 'no_leading_namespace_whitespace';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function beforeKinds(): array
    {
        return [\__McTokId::T_NAMESPACE];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    private static function endsWs(string $t): bool
    {
        return $t !== '' && \trim(\substr($t, -1)) === '';
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($i === 0 || $i >= $s->n || $s->kind[$i] !== \__McTokId::T_NAMESPACE || $s->ct[$i] !== 0) {
            return $gap;
        }
        $p = $gap === '' ? [] : (Gap::hasComment($gap) ? Gap::pieces($gap) : [$gap]);
        $m = \count($p);
        if ($m === 0 || !Gap::isSpace($p[$m - 1])) {
            $prev = $m === 0 ? $s->text($i - 1) : $p[$m - 1];
            return self::endsWs($prev) ? $gap : $gap . $this->lineEnding;
        }
        $last = $p[$m - 1];
        $nl = \strrpos($last, "\n");
        if ($nl === false) {
            $before = $m >= 2 ? $p[$m - 2] : $s->text($i - 1);
            if (self::endsWs($before)) {
                \array_pop($p);
            } else {
                $p[$m - 1] = ' ';
            }
        } else {
            $p[$m - 1] = \substr($last, 0, $nl + 1);
        }
        return \implode('', $p);
    }
}
