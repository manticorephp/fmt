<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\ArrayPush;
use Fmt\Rule\CombineNestedDirname;
use Fmt\Rule\DeclareStrictTypes;
use Fmt\Rule\DirConstant;
use Fmt\Rule\EregToPreg;
use Fmt\Rule\ErrorSuppression;
use Fmt\Rule\FopenFlagOrder;
use Fmt\Rule\FopenFlags;
use Fmt\Rule\FunctionToConstant;
use Fmt\Rule\GetClassToClassKeyword;
use Fmt\Rule\ImplodeCall;
use Fmt\Rule\IsNull;
use Fmt\Rule\LogicalOperators;
use Fmt\Rule\LongToShorthandOperator;
use Fmt\Rule\ModernSerializationMethods;
use Fmt\Rule\ModernizeStrpos;
use Fmt\Rule\ModernizeTypesCasting;
use Fmt\Rule\NativeConstantInvocation;
use Fmt\Rule\NativeFunctionInvocation;
use Fmt\Rule\NoAliasFunctions;
use Fmt\Rule\NoHomoglyphNames;
use Fmt\Rule\NoPhp4Constructor;
use Fmt\Rule\NoTrailingWhitespaceInString;
use Fmt\Rule\NoUnneededFinalMethod;
use Fmt\Rule\NoUnreachableDefaultArgumentValue;
use Fmt\Rule\NoUselessSprintf;
use Fmt\Rule\NonPrintableCharacter;
use Fmt\Rule\OrderedTraits;
use Fmt\Rule\PhpUnitConstruct;
use Fmt\Rule\PhpUnitMockShortWillReturn;
use Fmt\Rule\PhpUnitSetUpTearDownVisibility;
use Fmt\Rule\PhpUnitTestAnnotation;
use Fmt\Rule\PowToExponentiation;
use Fmt\Rule\PsrAutoloading;
use Fmt\Rule\SelfAccessor;
use Fmt\Rule\SetTypeToCast;
use Fmt\Rule\StaticLambda;
use Fmt\Rule\StringLengthToEmpty;
use Fmt\Rule\StringLineEnding;
use Fmt\Rule\TernaryToElvisOperator;
use Fmt\Rule\VoidReturn;

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
        if ($name === 'long_to_shorthand_operator') {
            return new LongToShorthandOperator();
        }
        if ($name === 'string_length_to_empty') {
            return new StringLengthToEmpty();
        }
        if ($name === 'pow_to_exponentiation') {
            return new PowToExponentiation();
        }
        if ($name === 'modernize_strpos') {
            Rules::only($name, $opt, ['modernize_stripos']);
            return new ModernizeStrpos(Rules::bool($name, $opt, 'modernize_stripos', false));
        }
        if ($name === 'static_lambda') {
            return new StaticLambda();
        }
        if ($name === 'void_return') {
            Rules::only($name, $opt, ['fix_lambda']);
            return new VoidReturn(Rules::bool($name, $opt, 'fix_lambda', true));
        }
        if ($name === 'self_accessor') {
            return new SelfAccessor();
        }
        if ($name === 'no_unreachable_default_argument_value') {
            return new NoUnreachableDefaultArgumentValue();
        }
        if ($name === 'no_homoglyph_names') {
            return new NoHomoglyphNames();
        }
        if ($name === 'declare_strict_types') {
            Rules::only($name, $opt, ['strategy', 'preserve_existing_declaration']);
            if (isset($opt['preserve_existing_declaration'])) {
                throw new \InvalidArgumentException("$name: the deprecated option preserve_existing_declaration is not supported, use strategy");
            }
            return new DeclareStrictTypes(Rules::enum($name, $opt, 'strategy', ['remove', 'add_when_missing', 'enforce'], 'enforce'), $lineEnding);
        }
        if ($name === 'php_unit_construct') {
            Rules::only($name, $opt, ['assertions']);
            $all = ['assertEquals', 'assertNotEquals', 'assertNotSame', 'assertSame'];
            return new PhpUnitConstruct(Rules::subset($name, $opt, 'assertions', $all, $all));
        }
        if ($name === 'php_unit_mock_short_will_return') {
            return new PhpUnitMockShortWillReturn();
        }
        if ($name === 'php_unit_set_up_tear_down_visibility') {
            return new PhpUnitSetUpTearDownVisibility();
        }
        if ($name === 'native_function_invocation') {
            Rules::only($name, $opt, ['exclude', 'include', 'scope', 'strict']);
            $exclude = isset($opt['exclude']) ? Rules::strList($name, $opt['exclude']) : [];
            $include = isset($opt['include']) ? Rules::strList($name, $opt['include']) : ['@compiler_optimized'];
            foreach ([$exclude, $include] as $list) {
                foreach ($list as $f) {
                    if ($f === '' || \trim($f) !== $f) {
                        throw new \InvalidArgumentException("$name: each element must be a non-empty, trimmed string");
                    }
                }
            }
            foreach ($include as $f) {
                if ($f[0] === '@' && !\in_array($f, ['@all', '@internal', '@compiler_optimized'], true)) {
                    throw new \InvalidArgumentException("$name: unknown set \"$f\", known sets are @all, @internal and @compiler_optimized");
                }
            }
            return new NativeFunctionInvocation($include, $exclude, Rules::enum($name, $opt, 'scope', ['all', 'namespaced'], 'all'), Rules::bool($name, $opt, 'strict', true));
        }
        if ($name === 'native_constant_invocation') {
            Rules::only($name, $opt, ['fix_built_in', 'include', 'exclude', 'scope', 'strict']);
            $include = isset($opt['include']) ? Rules::strList($name, $opt['include']) : [];
            $exclude = isset($opt['exclude']) ? Rules::strList($name, $opt['exclude']) : ['null', 'false', 'true'];
            foreach ([$include, $exclude] as $list) {
                foreach ($list as $f) {
                    if (\trim($f) !== $f) {
                        throw new \InvalidArgumentException("$name: each element must be a trimmed string");
                    }
                }
            }
            return new NativeConstantInvocation(Rules::bool($name, $opt, 'fix_built_in', true), $include, $exclude, Rules::enum($name, $opt, 'scope', ['all', 'namespaced'], 'all'),
                Rules::bool($name, $opt, 'strict', true));
        }
        if ($name === 'no_unneeded_final_method') {
            Rules::only($name, $opt, ['private_methods']);
            return new NoUnneededFinalMethod(Rules::bool($name, $opt, 'private_methods', true));
        }
        if ($name === 'ordered_traits') {
            Rules::only($name, $opt, ['case_sensitive']);
            return new OrderedTraits(Rules::bool($name, $opt, 'case_sensitive', false));
        }
        if ($name === 'modern_serialization_methods') {
            return new ModernSerializationMethods();
        }
        if ($name === 'php_unit_test_annotation') {
            Rules::only($name, $opt, ['style']);
            return new PhpUnitTestAnnotation(Rules::enum($name, $opt, 'style', ['prefix', 'annotation'], 'prefix') === 'annotation', $lineEnding);
        }
        if ($name === 'psr_autoloading') {
            Rules::only($name, $opt, ['dir']);
            $dir = null;
            if (isset($opt['dir'])) {
                if (!\is_string($opt['dir'])) {
                    throw new \InvalidArgumentException("$name: dir must be a string or null");
                }
                $dir = $opt['dir'];
            }
            return new PsrAutoloading($dir);
        }
        if ($name === 'no_php4_constructor') {
            return new NoPhp4Constructor();
        }
        if ($name === 'ereg_to_preg') {
            return new EregToPreg();
        }
        if ($name === 'non_printable_character') {
            Rules::only($name, $opt, ['use_escape_sequences_in_strings']);
            return new NonPrintableCharacter(Rules::bool($name, $opt, 'use_escape_sequences_in_strings', true));
        }
        return null;
    }
}
