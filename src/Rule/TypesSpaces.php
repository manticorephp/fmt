<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\TypesSpacesFixer (priority -1) with space_multiple_catch following space. Around a
 * union / intersection operator: none = a whitespace token without a line break goes, single = it becomes one space (or one is
 * inserted); a token holding a line break stays.
 */
final class TypesSpaces extends GapRule
{
    public function __construct(private bool $single = false)
    {
    }

    public function name(): string
    {
        return 'types_spaces';
    }

    public function priority(): int
    {
        return -1;
    }

    public function needsMask(): int
    {
        return 0;
    }

    /** @return int[] */
    public function afterKinds(): array
    {
        return [124, 38, \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG];
    }

    /** @return int[] */
    public function beforeKinds(): array
    {
        return $this->afterKinds();
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    private static function isOp(Source $s, int $j): bool
    {
        $c = $s->ct[$j];
        return $c === CT::TYPE_ALTERNATION || $c === CT::TYPE_INTERSECTION;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        // upstream: the whitespace behind the operator first, then the one ahead of it
        if ($i > 0 && self::isOp($s, $i - 1)) {
            $lead = Gap::lead($gap);
            if ($this->single) {
                if ($lead === '' || ($lead !== ' ' && !Gap::pieceHasR($lead))) {
                    $gap = Gap::withLead($gap, ' ');
                }
            } elseif ($lead !== '' && !Gap::pieceHasR($lead)) {
                $gap = Gap::withLead($gap, '');
            }
        }
        if ($i < $s->n && self::isOp($s, $i)) {
            $trail = Gap::trail($gap);
            if ($this->single) {
                if ($trail === '') {
                    $gap .= ' ';
                } elseif ($trail !== ' ' && !Gap::pieceHasR($trail)) {
                    $gap = Gap::withTrail($gap, ' ');
                }
            } elseif ($trail !== '' && !Gap::pieceHasR($trail)) {
                $gap = Gap::withTrail($gap, '');
            }
        }
        return $gap;
    }
}
