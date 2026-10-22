<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\MultilinePromotedPropertiesFixer (priority 1, experimental). A constructor with promoted properties (and at least
 * minimum_number_of_parameters parameters) gets one parameter per line; one with fewer is made single-line again (unless it holds a comment or attribute). Whole-file
 * FlatTokens port: the methods are visited by their load-time indexes, which the whitespace it inserts shifts, as upstream's.
 */
final class MultilinePromotedProperties extends FlatRule
{
    public function __construct(private bool $keepBlankLines, private int $minimum, private string $lineEnding, private string $indentUnit)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'multiline_promoted_properties';
    }

    public function priority(): int
    {
        return 1;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            $c = $s->ct[$i];
            if ($c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE) {
                return true;
            }
        }
        return false;
    }

    private static function promoted(int $k): bool
    {
        return $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var int[] $methods */
        $methods = [];
        foreach (Analyzer::classyElements($s) as $idx => [$type, $cls]) {
            if ($type === Analyzer::E_METHOD) {
                $methods[] = $t->pos[$idx];
            }
        }
        foreach ($methods as $index) {
            $open = self::nextOf($t, $index, 40);
            if ($open < 0) {
                return;
            }
            $close = $t->blockEnd($open, 41);
            if ($this->shouldBeMultiline($t, $open, $close)) {
                $this->makeMultiline($t, $open, $close);
            } elseif ($this->shouldBeSingleline($t, $open, $close)) {
                $this->makeSingleline($t, $open, $close);
            }
        }
    }

    private function shouldBeMultiline(FlatTokens $t, int $open, int $close): bool
    {
        $found = false;
        $count = 0;
        for ($i = $open + 1; $i < $close; ++$i) {
            if ($t->i[$i] === -1) {
                continue;
            }
            if ($t->k[$i] === \__McTokId::T_VARIABLE) {
                ++$count;
            }
            if (self::promoted($t->k[$i])) {
                $found = true;
            }
        }
        return $found && $count >= $this->minimum;
    }

    private function shouldBeSingleline(FlatTokens $t, int $open, int $close): bool
    {
        $found = false;
        $count = 0;
        $multiline = false;
        for ($i = $open + 1; $i < $close; ++$i) {
            if ($t->k[$i] === FlatTokens::COMMENT) {
                return false;
            }
            if ($t->i[$i] !== -1) {
                if ($t->k[$i] === \__McTokId::T_VARIABLE) {
                    ++$count;
                }
                if (self::promoted($t->k[$i])) {
                    $found = true;
                }
                if ($t->k[$i] === \__McTokId::T_ATTRIBUTE) {
                    return false;
                }
            } elseif ($t->k[$i] === FlatTokens::WS && \str_contains($t->c[$i], "\n")) {
                $multiline = true;
            }
        }
        return $found && $multiline && $count < $this->minimum;
    }

    private function makeSingleline(FlatTokens $t, int $open, int $close): void
    {
        for ($i = $close - 1; $i > $open; --$i) {
            if ($t->k[$i] !== FlatTokens::WS || !\str_contains($t->c[$i], "\n")) {
                continue;
            }
            if ($i === $open + 1 || $i === $close - 1) {
                $t->clearAt($i);
            } else {
                $t->c[$i] = ' ';
            }
        }
    }

    private function makeMultiline(FlatTokens $t, int $open, int $close): void
    {
        $indent = self::detectIndent($t, $open);
        self::ensureWs($t, $close - 1, 1, $this->lineEnding . $indent);
        $index = $t->meaningful($close, -1);
        if (!self::is($t, $index, 44)) {
            $index = $close;
        }
        while ($index > $open) {
            $index = $t->meaningful($index, -1);
            if ($index < 0) {
                return;
            }
            $det = self::blockEdge($t->k[$index]);
            if ($det !== null && $det[1] === 0) {
                $index = $t->blockStart($index, $det[0]);
                continue;
            }
            if (!(self::is($t, $index, 40) || self::is($t, $index, 44))) {
                continue;
            }
            $this->fixParameter($t, $index + 1, $indent);
        }
    }

    private function fixParameter(FlatTokens $t, int $index, string $indent): void
    {
        if ($this->keepBlankLines && $t->k[$index] === FlatTokens::WS && \str_contains($t->c[$index], "\n")) {
            return;
        }
        self::ensureWs($t, $index, 0, $this->lineEnding . $indent . $this->indentUnit);
    }
}
