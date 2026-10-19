<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\AttributeNotation\AttributeBlockNoSpacesFixer (priority 0). Per `#[ ... ]` group (a group inside the
 * arguments of an outer one is never visited: upstream continues behind the outer `]`): the whitespace right behind `#[` and right
 * before `]` goes, but ONE collected list serves both sides, so a comment on the `]` side cancels the `#[` side too, while a comment
 * on the `#[` side only cancels itself. Decided in prepare() (comments never change).
 */
final class AttributeBlockNoSpaces extends GapRule
{
    /** @var array<int, bool> */
    private array $clear = [];

    public function name(): string
    {
        return 'attribute_block_no_spaces';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        $this->clear = [];
        $any = false;
        $n = $s->n;
        $until = -1;
        for ($j = 0; $j < $n; $j++) {
            if ($j <= $until || $s->kind[$j] !== \__McTokId::T_ATTRIBUTE) {
                continue;
            }
            $c = $s->pair[$j];
            if ($c < 0) {
                continue;
            }
            $until = $c;
            if (!Gap::hasComment($s->gapBefore[$c])) {
                $this->clear[$c] = true;
                $any = true;
                if (!Gap::hasComment($s->gapBefore[$j + 1])) {
                    $this->clear[$j + 1] = true;
                }
            }
        }
        return $any;
    }

    public function afterKinds(): array
    {
        return [\__McTokId::T_ATTRIBUTE];
    }

    public function beforeKinds(): array
    {
        return [93];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        return isset($this->clear[$i]) ? '' : $gap;
    }
}
