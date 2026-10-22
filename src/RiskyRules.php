<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Rule\ArrayPush;
use Fmt\Rule\ClassKeyword;
use Fmt\Rule\CombineNestedDirname;
use Fmt\Rule\CommentToPhpdoc;
use Fmt\Rule\DateTimeCreateFromFormatCall;
use Fmt\Rule\DateTimeImmutable;
use Fmt\Rule\DeclareStrictTypes;
use Fmt\Rule\DirConstant;
use Fmt\Rule\EregToPreg;
use Fmt\Rule\ErrorSuppression;
use Fmt\Rule\FinalInternalClass;
use Fmt\Rule\FinalPublicMethodForAbstractClass;
use Fmt\Rule\FopenFlagOrder;
use Fmt\Rule\FopenFlags;
use Fmt\Rule\FunctionToConstant;
use Fmt\Rule\GetClassToClassKeyword;
use Fmt\Rule\ImplodeCall;
use Fmt\Rule\IsNull;
use Fmt\Rule\LogicalOperators;
use Fmt\Rule\LongToShorthandOperator;
use Fmt\Rule\MbStrFunctions;
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
use Fmt\Rule\NoUnsetOnProperty;
use Fmt\Rule\NoUselessPrintf;
use Fmt\Rule\NoUselessSprintf;
use Fmt\Rule\NonPrintableCharacter;
use Fmt\Rule\OrderedTraits;
use Fmt\Rule\PhpUnitAssertNewNames;
use Fmt\Rule\PhpUnitConstruct;
use Fmt\Rule\PhpUnitDataProviderName;
use Fmt\Rule\PhpUnitDataProviderReturnType;
use Fmt\Rule\PhpUnitDataProviderStatic;
use Fmt\Rule\PhpUnitDedicateAssert;
use Fmt\Rule\PhpUnitDedicateAssertInternalType;
use Fmt\Rule\PhpUnitExpectation;
use Fmt\Rule\PhpUnitMock;
use Fmt\Rule\PhpUnitMockShortWillReturn;
use Fmt\Rule\PhpUnitNamespaced;
use Fmt\Rule\PhpUnitNoExpectationAnnotation;
use Fmt\Rule\PhpUnitSetUpTearDownVisibility;
use Fmt\Rule\PhpUnitStrict;
use Fmt\Rule\PhpUnitTestAnnotation;
use Fmt\Rule\PhpUnitTestCaseStaticMethodCalls;
use Fmt\Rule\PhpdocArrayType;
use Fmt\Rule\PhpdocListType;
use Fmt\Rule\PhpdocReadonlyClassCommentToKeyword;
use Fmt\Rule\PhpdocToParamType;
use Fmt\Rule\PhpdocToPropertyType;
use Fmt\Rule\PhpdocToReturnType;
use Fmt\Rule\PowToExponentiation;
use Fmt\Rule\PsrAutoloading;
use Fmt\Rule\RandomApiMigration;
use Fmt\Rule\RegularCallableCall;
use Fmt\Rule\SelfAccessor;
use Fmt\Rule\SetTypeToCast;
use Fmt\Rule\StaticLambda;
use Fmt\Rule\StaticPrivateMethod;
use Fmt\Rule\StrictComparison;
use Fmt\Rule\StrictParam;
use Fmt\Rule\StringLengthToEmpty;
use Fmt\Rule\StringLineEnding;
use Fmt\Rule\TernaryToElvisOperator;
use Fmt\Rule\UseArrowFunctions;
use Fmt\Rule\VoidReturn;
use Fmt\Rule\YieldFromArrayToYields;

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
        if ($name === 'phpdoc_to_param_type' || $name === 'phpdoc_to_property_type' || $name === 'phpdoc_to_return_type') {
            Rules::only($name, $opt, ['scalar_types', 'union_types', 'types_map']);
            $map = [];
            if (isset($opt['types_map'])) {
                if (!\is_array($opt['types_map'])) {
                    throw new \InvalidArgumentException("$name: types_map must be a map of string => string");
                }
                foreach ($opt['types_map'] as $from => $to) {
                    if (!\is_string($to)) {
                        throw new \InvalidArgumentException("$name: types_map must be a map of string => string");
                    }
                    $map[(string) $from] = $to;
                }
            }
            $scalar = Rules::bool($name, $opt, 'scalar_types', true);
            $union = Rules::bool($name, $opt, 'union_types', true);
            if ($name === 'phpdoc_to_param_type') {
                return new PhpdocToParamType($scalar, $union, $map);
            }
            return $name === 'phpdoc_to_property_type' ? new PhpdocToPropertyType($scalar, $union, $map) : new PhpdocToReturnType($scalar, $union, $map);
        }
        if ($name === 'class_keyword') {
            return new ClassKeyword();
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
        if ($name === 'strict_comparison') {
            return new StrictComparison();
        }
        if ($name === 'strict_param') {
            return new StrictParam();
        }
        if ($name === 'php_unit_strict') {
            Rules::only($name, $opt, ['assertions']);
            $all = ['assertAttributeEquals', 'assertAttributeNotEquals', 'assertEquals', 'assertNotEquals'];
            return new PhpUnitStrict(Rules::subset($name, $opt, 'assertions', $all, $all));
        }
        if ($name === 'no_unset_on_property') {
            return new NoUnsetOnProperty();
        }
        if ($name === 'yield_from_array_to_yields') {
            return new YieldFromArrayToYields();
        }
        if ($name === 'php_unit_data_provider_name') {
            Rules::only($name, $opt, ['prefix', 'suffix']);
            foreach (['prefix', 'suffix'] as $o) {
                if (isset($opt[$o]) && !\is_string($opt[$o])) {
                    throw new \InvalidArgumentException("$name: $o must be a string");
                }
            }
            return new PhpUnitDataProviderName(isset($opt['prefix']) ? (string) $opt['prefix'] : 'provide', isset($opt['suffix']) ? (string) $opt['suffix'] : 'Cases');
        }
        if ($name === 'php_unit_data_provider_return_type') {
            return new PhpUnitDataProviderReturnType();
        }
        if ($name === 'php_unit_data_provider_static') {
            Rules::only($name, $opt, ['force']);
            return new PhpUnitDataProviderStatic(Rules::bool($name, $opt, 'force', false));
        }
        if ($name === 'final_internal_class') {
            Rules::only($name, $opt, ['include', 'exclude', 'consider_absent_docblock_as_internal_class', 'annotation_include', 'annotation_exclude']);
            if (isset($opt['annotation_include']) || isset($opt['annotation_exclude'])) {
                throw new \InvalidArgumentException("$name: the deprecated options annotation_include / annotation_exclude are not supported, use include / exclude");
            }
            $include = isset($opt['include']) ? Rules::strList($name, $opt['include']) : ['internal'];
            $exclude = isset($opt['exclude']) ? Rules::strList($name, $opt['exclude']) : ['final', 'Entity', 'ORM\Entity', 'ORM\Mapping\Entity', 'Mapping\Entity', 'Document', 'ODM\Document'];
            foreach ([$include, $exclude] as $list) {
                foreach ($list as $f) {
                    if ($f === '') {
                        throw new \InvalidArgumentException("$name: an element must not be empty");
                    }
                }
            }
            return new FinalInternalClass($include, $exclude, Rules::bool($name, $opt, 'consider_absent_docblock_as_internal_class', false));
        }
        if ($name === 'comment_to_phpdoc') {
            Rules::only($name, $opt, ['ignored_tags']);
            return new CommentToPhpdoc(isset($opt['ignored_tags']) ? Rules::strList($name, $opt['ignored_tags']) : [], $lineEnding);
        }
        if ($name === 'php_unit_test_case_static_method_calls') {
            Rules::only($name, $opt, ['call_type', 'methods', 'target']);
            $methods = [];
            if (isset($opt['methods'])) {
                if (!\is_array($opt['methods'])) {
                    throw new \InvalidArgumentException("$name: methods must be a map of method => call type");
                }
                foreach ($opt['methods'] as $m => $v) {
                    $m = (string) $m;
                    if (!PhpUnitTestCaseStaticMethodCalls::knownMethod($m)) {
                        throw new \InvalidArgumentException("$name: unexpected \"methods\" key \"$m\"");
                    }
                    if (!\is_string($v) || !\in_array($v, ['this', 'self', 'static'], true)) {
                        throw new \InvalidArgumentException("$name: unexpected value for method \"$m\", expected this, self or static");
                    }
                    $methods[$m] = $v;
                }
            }
            return new PhpUnitTestCaseStaticMethodCalls(Rules::enum($name, $opt, 'call_type', ['this', 'self', 'static'], 'static'), $methods,
                Rules::enum($name, $opt, 'target', ['10.0', '11.0', 'newest'], '10.0'));
        }
        if ($name === 'random_api_migration') {
            Rules::only($name, $opt, ['replacements']);
            $rep = ['getrandmax' => 'mt_getrandmax', 'rand' => 'mt_rand', 'srand' => 'mt_srand'];
            if (isset($opt['replacements'])) {
                if (!\is_array($opt['replacements'])) {
                    throw new \InvalidArgumentException("$name: replacements must be a map of function => function");
                }
                $rep = [];
                foreach ($opt['replacements'] as $from => $to) {
                    if (!RandomApiMigration::knownFunction((string) $from)) {
                        throw new \InvalidArgumentException("$name: Function \"$from\" is not handled by the fixer.");
                    }
                    if (!\is_string($to)) {
                        throw new \InvalidArgumentException("$name: replacements must map to function names");
                    }
                    $rep[(string) $from] = $to;
                }
            }
            foreach ($rep as $from => $to) {
                if ($to !== (string) $from && isset($rep[$to])) {
                    throw new \InvalidArgumentException("$name: chained replacements (\"$to\" is replaced itself) are not supported");
                }
            }
            return new RandomApiMigration($rep);
        }
        if ($name === 'use_arrow_functions') {
            return new UseArrowFunctions();
        }
        if ($name === 'php_unit_assert_new_names') {
            return new PhpUnitAssertNewNames();
        }
        if ($name === 'php_unit_mock') {
            Rules::only($name, $opt, ['target']);
            return new PhpUnitMock(Rules::enum($name, $opt, 'target', ['5.4', '5.5', 'newest'], 'newest'));
        }
        if ($name === 'php_unit_namespaced') {
            Rules::only($name, $opt, ['target']);
            return new PhpUnitNamespaced(Rules::enum($name, $opt, 'target', ['4.8', '5.7', '6.0', 'newest'], 'newest'));
        }
        if ($name === 'phpdoc_readonly_class_comment_to_keyword') {
            return new PhpdocReadonlyClassCommentToKeyword();
        }
        if ($name === 'final_class') {
            return new FinalInternalClass([], ['final', 'Entity', 'ORM\Entity', 'ORM\Mapping\Entity', 'Mapping\Entity', 'Document', 'ODM\Document'], true, 'final_class');
        }
        if ($name === 'final_public_method_for_abstract_class') {
            return new FinalPublicMethodForAbstractClass();
        }
        if ($name === 'mb_str_functions') {
            return new MbStrFunctions();
        }
        if ($name === 'php_unit_dedicate_assert_internal_type') {
            Rules::only($name, $opt, ['target']);
            Rules::enum($name, $opt, 'target', ['7.5', 'newest'], 'newest');
            return new PhpUnitDedicateAssertInternalType();
        }
        if ($name === 'php_unit_dedicate_assert') {
            Rules::only($name, $opt, ['target']);
            return new PhpUnitDedicateAssert(Rules::enum($name, $opt, 'target', ['3.0', '3.5', '5.0', '5.6', 'newest'], 'newest'));
        }
        if ($name === 'php_unit_expectation') {
            Rules::only($name, $opt, ['target']);
            return new PhpUnitExpectation(Rules::enum($name, $opt, 'target', ['5.2', '5.6', '8.4', 'newest'], 'newest'), $lineEnding, $indent);
        }
        if ($name === 'php_unit_no_expectation_annotation') {
            Rules::only($name, $opt, ['target', 'use_class_const']);
            return new PhpUnitNoExpectationAnnotation(Rules::enum($name, $opt, 'target', ['3.2', '4.3', 'newest'], 'newest'), Rules::bool($name, $opt, 'use_class_const', true), $lineEnding, $indent);
        }
        if ($name === 'no_useless_printf') {
            return new NoUselessPrintf();
        }
        if ($name === 'date_time_immutable') {
            return new DateTimeImmutable();
        }
        if ($name === 'date_time_create_from_format_call') {
            return new DateTimeCreateFromFormatCall();
        }
        if ($name === 'phpdoc_array_type') {
            return new PhpdocArrayType();
        }
        if ($name === 'phpdoc_list_type') {
            return new PhpdocListType();
        }
        if ($name === 'static_private_method') {
            return new StaticPrivateMethod();
        }
        if ($name === 'regular_callable_call') {
            return new RegularCallableCall();
        }
        return ExtraRules::make($name, $lineEnding, $opt, $indent);
    }
}
