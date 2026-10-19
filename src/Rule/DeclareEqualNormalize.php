<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\DeclareEqualNormalizeFixer, space=none (priority 0). The blanks around an `=`
 * inside `declare( ... )` go: before it unless a comment precedes the blank, after it always (a whitespace token right behind `=`
 * is the leading blank run of the gap, even when a comment follows). `space=single` is refused (Rules).
 */
final class DeclareEqualNormalize extends GapRule
{
    /** @var array<int, bool> */
    private array $eq = [];

    public function name(): string
    {
        return 'declare_equal_normalize';
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
        $this->eq = [];
        $any = false;
        $n = $s->n;
        for ($j = 0; $j < $n; $j++) {
            if ($s->kind[$j] === \__McTokId::T_DECLARE && $s->ct[$j] === 0 && $j + 1 < $n && $s->kind[$j + 1] === 40) {
                $e = $s->pair[$j + 1];
                for ($q = $j + 2; $q < $e; $q++) {
                    if ($s->kind[$q] === 61 && $s->ct[$q] === 0) {
                        $this->eq[$q] = true;
                        $any = true;
                    }
                }
            }
        }
        return $any;
    }

    public function afterKinds(): array
    {
        return [61];
    }

    public function beforeKinds(): array
    {
        return [61];
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
        if ($gap === '') {
            return $gap;
        }
        if ($i < $s->n && isset($this->eq[$i]) && !Gap::hasComment($gap)) {
            return '';
        }
        if ($i > 0 && isset($this->eq[$i - 1])) {
            $k = \strcspn($gap, '#/');
            return $k === 0 ? $gap : \substr($gap, $k);
        }
        return $gap;
    }
}
