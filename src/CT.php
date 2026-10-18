<?php

declare(strict_types=1);

namespace Fmt;

/** php-cs-fixer's custom token ids (PhpCsFixer\Tokenizer\CT), same numbers. Stored in Source::$ct, 0 = none. */
final class CT
{
    public const ARRAY_INDEX_BRACE_CLOSE = 10001;
    public const ARRAY_INDEX_BRACE_OPEN = 10002;
    public const ARRAY_BRACKET_CLOSE = 10003;
    public const ARRAY_BRACKET_OPEN = 10004;
    public const ARRAY_TYPEHINT = 10005;
    public const CLASS_INSTANTIATION_PARENTHESIS_CLOSE = 10006;
    public const CLASS_INSTANTIATION_PARENTHESIS_OPEN = 10007;
    public const CLASS_CONSTANT = 10008;
    public const CONST_IMPORT = 10009;
    public const CURLY_CLOSE = 10010;
    public const DESTRUCTURING_BRACKET_CLOSE = 10011;
    public const DESTRUCTURING_BRACKET_OPEN = 10012;
    public const DOLLAR_CLOSE_CURLY_BRACES = 10013;
    public const DYNAMIC_PROP_BRACE_CLOSE = 10014;
    public const DYNAMIC_PROP_BRACE_OPEN = 10015;
    public const DYNAMIC_VAR_BRACE_CLOSE = 10016;
    public const DYNAMIC_VAR_BRACE_OPEN = 10017;
    public const FUNCTION_IMPORT = 10018;
    public const GROUP_IMPORT_BRACE_CLOSE = 10019;
    public const GROUP_IMPORT_BRACE_OPEN = 10020;
    public const NAMESPACE_OPERATOR = 10021;
    public const NULLABLE_TYPE = 10022;
    public const RETURN_REF = 10023;
    public const TYPE_ALTERNATION = 10024;
    public const TYPE_COLON = 10025;
    public const USE_LAMBDA = 10026;
    public const USE_TRAIT = 10027;
    public const CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC = 10028;
    public const CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED = 10029;
    public const CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE = 10030;
    public const ATTRIBUTE_CLOSE = 10031;
    public const NAMED_ARGUMENT_NAME = 10032;
    public const NAMED_ARGUMENT_COLON = 10033;
    public const FIRST_CLASS_CALLABLE = 10034;
    public const TYPE_INTERSECTION = 10035;
    public const DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN = 10036;
    public const DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE = 10037;
    public const DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_OPEN = 10038;
    public const DYNAMIC_CLASS_CONSTANT_FETCH_BRACE_CLOSE = 10039;
    public const PROPERTY_HOOK_BRACE_OPEN = 10040;
    public const PROPERTY_HOOK_BRACE_CLOSE = 10041;
}
