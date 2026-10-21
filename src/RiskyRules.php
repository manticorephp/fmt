<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\DirConstant;
use Fmt\Rule\IsNull;
use Fmt\Rule\LogicalOperators;
use Fmt\Rule\ModernizeTypesCasting;
use Fmt\Rule\NoTrailingWhitespaceInString;
use Fmt\Rule\NoUselessSprintf;
use Fmt\Rule\StringLineEnding;

/** The registry of the risky rules (upstream `isRisky()`: the run refuses them unless risky rules are allowed, see RulePlan). */
final class RiskyRules
{
    /**
     * @param array<string, mixed> $opt
     */
    public static function make(string $name, string $lineEnding, array $opt, string $indent): ?GapRule
    {
        if ($name === 'logical_operators') {
            return new LogicalOperators();
        }
        if ($name === 'string_line_ending') {
            return new StringLineEnding($lineEnding);
        }
        if ($name === 'dir_constant') {
            return new DirConstant();
        }
        if ($name === 'no_useless_sprintf') {
            return new NoUselessSprintf();
        }
        if ($name === 'is_null') {
            return new IsNull();
        }
        if ($name === 'modernize_types_casting') {
            return new ModernizeTypesCasting();
        }
        if ($name === 'no_trailing_whitespace_in_string') {
            return new NoTrailingWhitespaceInString();
        }
        return null;
    }
}
