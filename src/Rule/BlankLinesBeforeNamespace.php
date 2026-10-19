<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\NamespaceNotation\BlankLinesBeforeNamespaceFixer (priority -31). Line breaks are counted over the
 * (at most two) tokens ahead of `namespace`: a whitespace token, then another whitespace token or the open tag (its own "\n" counts).
 * Outside [min, max] the whitespace token is rewritten (or one is inserted) and the open tag is made to end in a line break.
 */
final class BlankLinesBeforeNamespace extends GapRule
{
    public function __construct(private int $min = 2, private int $max = 2)
    {
    }

    public function name(): string
    {
        return 'blank_lines_before_namespace';
    }

    public function priority(): int
    {
        return -31;
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
        return self::F_GAP | self::F_NB_OPEN;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_NB_OPEN;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($i === 0 || $i >= $s->n || $s->kind[$i] !== \__McTokId::T_NAMESPACE || $s->ct[$i] !== 0) {
            return $gap;
        }
        $pre = 0;
        $openIdx = -1;
        $newlineInOpening = false;
        $prevWs = '';
        $pieces = null;
        if ($gap === '') {
            if ($s->kind[$i - 1] === \__McTokId::T_OPEN_TAG) {
                $openIdx = $i - 1;
            }
        } else {
            $trail = Gap::trail($gap);
            if ($trail !== '') {
                $prevWs = $trail;
                $pre = \substr_count($trail, "\n");
                if ($trail === $gap && $s->kind[$i - 1] === \__McTokId::T_OPEN_TAG) {
                    $openIdx = $i - 1;
                }
            }
        }
        if ($openIdx >= 0) {
            $newlineInOpening = \strpos($s->text($openIdx), "\n") !== false;
            if ($newlineInOpening) {
                $pre++;
            }
        }
        if ($pre >= $this->min && $pre <= $this->max) {
            return $gap;
        }
        if ($this->max === 0) {
            if ($prevWs !== '') {
                $gap = Gap::withTrail($gap, '');
            }
            if ($newlineInOpening) {
                $s->setText($openIdx, \rtrim($s->text($openIdx)) . ' ');
            }
            return $gap;
        }
        $k = $pre >= $this->max ? $this->max : \max($pre, $this->min);
        if ($openIdx >= 0) {
            $s->setText($openIdx, \rtrim($s->text($openIdx)) . "\n");
            $k--;
        }
        if ($k === 0) {
            return $prevWs !== '' ? Gap::withTrail($gap, '') : $gap;
        }
        if ($prevWs !== '') {
            $pos = \strrpos($prevWs, "\n");
            return Gap::withTrail($gap, \str_repeat("\n", $k) . ($pos === false ? '' : \substr($prevWs, $pos + 1)));
        }
        return $gap . \str_repeat("\n", $k);
    }
}
