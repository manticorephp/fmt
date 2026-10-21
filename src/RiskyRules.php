<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\ArrayPush;
use Fmt\Rule\CombineNestedDirname;
use Fmt\Rule\DirConstant;
use Fmt\Rule\ErrorSuppression;
use Fmt\Rule\FopenFlagOrder;
use Fmt\Rule\FopenFlags;
use Fmt\Rule\FunctionToConstant;
use Fmt\Rule\GetClassToClassKeyword;
use Fmt\Rule\ImplodeCall;
use Fmt\Rule\IsNull;
use Fmt\Rule\LogicalOperators;
use Fmt\Rule\ModernizeTypesCasting;
use Fmt\Rule\NoAliasFunctions;
use Fmt\Rule\NoTrailingWhitespaceInString;
use Fmt\Rule\NoUselessSprintf;
use Fmt\Rule\SetTypeToCast;
use Fmt\Rule\StringLineEnding;
use Fmt\Rule\TernaryToElvisOperator;

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
        if ($name === 'set_type_to_cast') {
            return new SetTypeToCast();
        }
        if ($name === 'ternary_to_elvis_operator') {
            return new TernaryToElvisOperator();
        }
        if ($name === 'error_suppression') {
            Rules::only($name, $opt, ['mute_deprecation_error', 'noise_remaining_usages', 'noise_remaining_usages_exclude']);
            return new ErrorSuppression(Rules::bool($name, $opt, 'mute_deprecation_error', true), Rules::bool($name, $opt, 'noise_remaining_usages', false),
                isset($opt['noise_remaining_usages_exclude']) ? Rules::strList($name, $opt['noise_remaining_usages_exclude']) : []);
        }
        if ($name === 'function_to_constant') {
            Rules::only($name, $opt, ['functions']);
            $all = ['get_called_class', 'get_class', 'get_class_this', 'php_sapi_name', 'phpversion', 'pi'];
            return new FunctionToConstant(Rules::subset($name, $opt, 'functions', $all, $all));
        }
        if ($name === 'implode_call') {
            return new ImplodeCall();
        }
        if ($name === 'fopen_flags') {
            Rules::only($name, $opt, ['b_mode']);
            return new FopenFlags(Rules::bool($name, $opt, 'b_mode', true));
        }
        if ($name === 'fopen_flag_order') {
            return new FopenFlagOrder();
        }
        if ($name === 'combine_nested_dirname') {
            return new CombineNestedDirname();
        }
        if ($name === 'get_class_to_class_keyword') {
            return new GetClassToClassKeyword();
        }
        if ($name === 'no_alias_functions') {
            Rules::only($name, $opt, ['sets']);
            $allSets = ['@all', '@internal', '@exif', '@ftp', '@IMAP', '@ldap', '@mbreg', '@mysqli', '@oci', '@odbc', '@openssl', '@pcntl', '@pg', '@posix', '@snmp', '@sodium', '@time'];
            return new NoAliasFunctions(Rules::subset($name, $opt, 'sets', $allSets, ['@internal', '@IMAP', '@pg']));
        }
        if ($name === 'array_push') {
            return new ArrayPush();
        }
        return null;
    }
}
