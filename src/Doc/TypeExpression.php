<?php

declare(strict_types=1);

namespace Fmt\Doc;

use Fmt\Preg;

/**
 * Port of PhpCsFixer\DocBlock\TypeExpression (3.95.27): a PHPDoc type parsed by upstream's PCRE grammar into nested inner
 * expressions (start offset + expression), the regexes copied byte for byte.
 */
final class TypeExpression
{
    public const REGEX_IDENTIFIER = '(?:(?!(?<!\*)\d)[^\x00-\x2f\x3a-\x40\x5b-\x5e\x60\x7b-\x7f]++)';

    public const REGEX_TYPES = '(?<types>(?x) # one or several types separated by `|` or `&`
' . self::REGEX_TYPE . '
        (?:
            \h*(?<glue>[|&])\h*
            (?&type)
        )*+
    )';

    /**
     * Based on:
     * - https://github.com/phpstan/phpdoc-parser/blob/1.26.0/doc/grammars/type.abnf fuzzing grammar
     * - and https://github.com/phpstan/phpdoc-parser/blob/1.26.0/src/Parser/PhpDocParser.php parser impl.
     */
    private const REGEX_TYPE = '(?<type>(?x) # single type
            (?:co(?:ntra)?variant\h+)?
            (?<nullable>\??\h*)
            (?:
                (?<array_shape>
                    (?<array_shape_name>(?i)(?:array|list|object)(?-i))
                    (?<array_shape_start>\h*\{[\h\v*]*)
                    (?<array_shape_inners>
                        (?<array_shape_inner>
                            (?<array_shape_inner_key>(?:(?&constant)|(?&identifier)|(?&name))\h*\??\h*:\h*|)
                            (?<array_shape_inner_value>(?&types_inner))
                        )
                        (?:
                            \h*,[\h\v*]*
                            (?&array_shape_inner)
                        )*+
                        (?:\h*,|(?!(?&array_shape_unsealed_variadic)))
                    |)
                    (?<array_shape_unsealed> # unsealed array shape, e.g. `...`. `...<string>`
                        (?<array_shape_unsealed_variadic>\h*\.\.\.)
                        (?<array_shape_unsealed_type>
                            (?<array_shape_unsealed_type_start>\h*<\h*)
                            (?<array_shape_unsealed_type_a>(?&types_inner))
                            (?:
                                (?<array_shape_unsealed_type_comma>\h*,\h*)
                                (?<array_shape_unsealed_type_b>(?&array_shape_unsealed_type_a))
                            |)
                            \h*>
                        |)
                    |)
                    [\h\v*]*\}
                )
                |
                (?<callable> # callable syntax, e.g. `callable(string, int...): bool`, `\Closure<T>(T, int): T`
                    (?<callable_name>(?&name))
                    (?<callable_template>
                        (?<callable_template_start>\h*<\h*)
                        (?<callable_template_inners>
                            (?<callable_template_inner>
                                (?<callable_template_inner_name>
                                    (?&identifier)
                                )
                                (?<callable_template_inner_b> # template bound
                                    \h+(?i)(?<callable_template_inner_b_kw>of|as)(?-i)\h+
                                    (?<callable_template_inner_b_types>(?&types_inner))
                                |)
                                (?<callable_template_inner_d> # template default
                                    \h*=\h*
                                    (?<callable_template_inner_d_types>(?&types_inner))
                                |)
                            )
                            (?:
                                \h*,\h*
                                (?&callable_template_inner)
                            )*+
                        )
                        \h*>
                        (?=\h*\()
                    |)
                    (?<callable_start>\h*\(\h*)
                    (?<callable_arguments>
                        (?<callable_argument>
                            (?<callable_argument_type>(?&types_inner))
                            (?<callable_argument_is_reference>\h*&|)
                            (?<callable_argument_is_variadic>\h*\.\.\.|)
                            (?<callable_argument_name>\h*\$(?&identifier)|)
                            (?<callable_argument_is_optional>\h*=|)
                        )
                        (?:
                            \h*,\h*
                            (?&callable_argument)
                        )*+
                        (?:\h*,)?
                    |)
                    \h*\)
                    (?:
                        \h*\:\h*
                        (?<callable_return>(?&type))
                    )?
                )
                |
                (?<generic> # generic syntax, e.g.: `array<int, \Foo\Bar>`
                    (?<generic_name>(?&name))
                    (?<generic_start>\h*<[\h\v*]*)
                    (?<generic_types>
                        (?&types_inner)
                        (?:
                            \h*,[\h\v*]*
                            (?&types_inner)
                        )*+
                        (?:\h*,)?
                    )
                    [\h\v*]*>
                )
                |
                (?<class_constant> # class constants with optional wildcard, e.g.: `Foo::*`, `Foo::CONST_A`, `FOO::CONST_*`
                    (?<class_constant_name>(?&name))
                    ::\*?(?:(?&identifier)\*?)*
                )
                |
                (?<constant> # single constant value (case insensitive), e.g.: 1, -1.8E+6, `\'a\'`
                    (?i)
                    # all sorts of numbers: with or without sign, supports literal separator and several numeric systems,
                    # e.g.: 1, +1.1, 1., .1, -1, 123E+8, 123_456_789, 0x7Fb4, 0b0110, 0o777
                    [+-]?(?:
                        (?:0b[01]++(?:_[01]++)*+)
                        | (?:0o[0-7]++(?:_[0-7]++)*+)
                        | (?:0x[\da-f]++(?:_[\da-f]++)*+)
                        | (?:(?<constant_digits>\d++(?:_\d++)*+)|(?=\.\d))
                          (?:\.(?&constant_digits)|(?<=\d)\.)?+
                          (?:e[+-]?(?&constant_digits))?+
                    )
                    | \'(?:[^\'\\\]|\\\.)*+\'
                    | "(?:[^"\\\]|\\\.)*+"
                    (?-i)
                )
                |
                (?<this> # self reference, e.g.: $this, $self, @static
                    (?i)
                    [@$](?:this | self | static)
                    (?-i)
                )
                |
                (?<name> # full name, e.g.: `int`, `\DateTime`, `\Foo\Bar`, `positive-int`
                    \\\?+
                    (?<identifier>' . self::REGEX_IDENTIFIER . ')
                    (?:[\\\\\-](?&identifier))*+
                )
                |
                (?<parenthesized> # parenthesized type, e.g.: `(int)`, `(int|\stdClass)`
                    (?<parenthesized_start>
                        \(\h*
                    )
                    (?:
                        (?<parenthesized_types>
                            (?&types_inner)
                        )
                        |
                        (?<conditional> # conditional type, e.g.: `$foo is \Throwable ? false : $foo`
                            (?<conditional_cond_left>
                                (?:\$(?&identifier))
                                |
                                (?<conditional_cond_left_types>(?&types_inner))
                            )
                            (?<conditional_cond_middle>
                                \h+(?i)is(?:\h+not)?(?-i)\h+
                            )
                            (?<conditional_cond_right_types>(?&types_inner))
                            (?<conditional_true_start>\h*\?\h*)
                            (?<conditional_true_types>(?&types_inner))
                            (?<conditional_false_start>\h*:\h*)
                            (?<conditional_false_types>(?&types_inner))
                        )
                    )
                    \h*\)
                )
            )
            (?<array> # array, e.g.: `string[]`, `array<int, string>[][]`
                (\h*\[\h*\])*
            )
            (?:(?=1)0
                (?<types_inner>(?>
                    (?&type)
                    (?:
                        \h*[|&]\h*
                        (?&type)
                    )*+
                ))
            |)
        )';

    public const ALIASES = ['boolean' => 'bool', 'callback' => 'callable', 'double' => 'float', 'false' => 'bool', 'integer' => 'int',
        'list' => 'array', 'real' => 'float', 'true' => 'bool'];

    public string $value;
    private bool $composite = false;
    private ?string $glue = null;
    /** @var int[] */
    private array $innerStart = [];
    /** @var TypeExpression[] */
    private array $innerExpr = [];

    public function __construct(string $value, public ?NsContext $ns = null)
    {
        $this->value = $value;
        $this->parse();
    }

    public function toString(): string
    {
        return $this->value;
    }

    /** @return string[] */
    public function getTypes(): array
    {
        if ($this->composite) {
            $out = [];
            foreach ($this->innerExpr as $e) {
                $out[] = $e->value;
            }
            return $out;
        }
        return [$this->value];
    }

    public function isCompositeType(): bool
    {
        return $this->composite;
    }

    public function isUnionType(): bool
    {
        return $this->composite && $this->glue === '|';
    }

    public function isIntersectionType(): bool
    {
        return $this->composite && $this->glue === '&';
    }

    public function getTypesGlue(): ?string
    {
        return $this->glue;
    }

    /** @param \Closure(TypeExpression): TypeExpression $cb */
    public function mapTypes(\Closure $cb): TypeExpression
    {
        $value = $this->value;
        $off = 0;
        $n = \count($this->innerExpr);
        for ($k = 0; $k < $n; $k++) {
            $inner = $this->innerExpr[$k];
            $orig = $inner->value;
            $inner = $inner->mapTypes($cb);
            if ($inner->value !== $orig) {
                $value = \substr_replace($value, $inner->value, $this->innerStart[$k] + $off, \strlen($orig));
                $off += \strlen($inner->value) - \strlen($orig);
            }
        }
        $type = $value === $this->value ? $this : $this->inner($value);
        return $cb($type);
    }

    /** @param \Closure(TypeExpression): void $cb */
    public function walkTypes(\Closure $cb): void
    {
        $this->mapTypes(static function (TypeExpression $t) use ($cb): TypeExpression {
            $cb($t);
            return $t;
        });
    }

    /** @param \Closure(TypeExpression, TypeExpression): int $cmp */
    public function sortTypes(\Closure $cmp): TypeExpression
    {
        return $this->mapTypes(function (TypeExpression $type) use ($cmp): TypeExpression {
            if (!$type->composite) {
                return $type;
            }
            $idx = \array_keys($type->innerExpr);
            $ex = $type->innerExpr;
            \usort($idx, static function (int $a, int $b) use ($ex, $cmp): int {
                $c = $cmp($ex[$a], $ex[$b]);
                return $c !== 0 ? $c : $a <=> $b;
            });
            if ($idx !== \array_keys($type->innerExpr)) {
                $parts = [];
                foreach ($idx as $k) {
                    $parts[] = $ex[$k]->value;
                }
                return $this->inner(\implode((string) $type->glue, $parts));
            }
            return $type;
        });
    }

    public function removeDuplicateTypes(): TypeExpression
    {
        return $this->mapTypes(function (TypeExpression $type): TypeExpression {
            if (!$type->composite) {
                return $type;
            }
            $seen = [];
            $parts = [];
            foreach ($type->innerExpr as $e) {
                $norm = $e->sortTypes(static fn(TypeExpression $a, TypeExpression $b): int => $a->value <=> $b->value)->value;
                if (!\in_array($norm, $seen, true)) {
                    $seen[] = $norm;
                    $parts[] = $e->value;
                }
            }
            return $this->inner(\implode((string) $type->glue, $parts));
        });
    }

    public function getCommonType(): ?string
    {
        $main = null;
        foreach ($this->getTypes() as $type) {
            if ($type === 'null') {
                continue;
            }
            if (\str_starts_with($type, '?')) {
                $type = \substr($type, 1);
            }
            if (Preg::test('/\[\h*\]$/', $type)) {
                $type = 'array';
            } elseif (Preg::match('/^(.+?)\h*[<{(]/', $type, $m)) {
                $type = (string) $m[1];
            }
            if (isset(self::ALIASES[$type])) {
                $type = self::ALIASES[$type];
            }
            if ($main === null || $type === $main) {
                $main = $type;
                continue;
            }
            $main = $this->getParentType($type, $main);
            if ($main === null) {
                return null;
            }
        }
        return $main;
    }

    public function allowsNull(): bool
    {
        foreach ($this->getTypes() as $type) {
            if ($type === 'null' || $type === 'mixed' || \str_starts_with($type, '?')) {
                return true;
            }
        }
        return false;
    }

    private function add(int $start, string $value): void
    {
        $this->innerStart[] = $start;
        $this->innerExpr[] = $this->inner($value);
    }

    /** @param array<int|string, mixed> $m */
    private static function cap(array $m, string $k): string
    {
        return isset($m[$k]) ? (string) $m[$k][0] : '';
    }

    /** @param array<int|string, mixed> $m */
    private static function capAt(array $m, string $k): int
    {
        return isset($m[$k]) ? (int) $m[$k][1] : -1;
    }

    /** one simple atom: an optional `?`, a name (upstream's `name` alternative, ASCII + any byte >= 0x80), `[]` suffixes */
    public const SIMPLE_ATOM = '\??\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:[\\\\-][A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*(?:\[\])*';

    /**
     * The grammar's answer for the common shapes without the full regex (it costs ~10 us natively): a simple atom (`int`, `?Foo\Bar`,
     * `string[]`), or simple atoms glued by one kind of glue without blanks (`int|null`). Anything else takes the full parse.
     */
    private function parseSimple(): bool
    {
        $v = $this->value;
        if (\preg_match('/^' . self::SIMPLE_ATOM . '$/', $v) === 1) {
            if ($v[0] === '?') {
                $this->add(1, \substr($v, 1));
            } elseif (\str_ends_with($v, '[]')) {
                $this->add(0, \rtrim($v, '[]'));
            }
            return true;
        }
        $glue = \str_contains($v, '|') ? '|' : '&';
        if (\preg_match('/^' . self::SIMPLE_ATOM . '(?:\\' . $glue . self::SIMPLE_ATOM . ')+$/', $v) !== 1) {
            return false;
        }
        $this->composite = true;
        $this->glue = $glue;
        $at = 0;
        foreach (\explode($glue, $v) as $part) {
            $this->add($at, $part);
            $at += \strlen($part) + 1;
        }
        return true;
    }

    private function parse(): void
    {
        if ($this->parseSimple()) {
            return;
        }
        $seen = null;
        /** @var int[] $iStart */
        $iStart = [];
        /** @var string[] $iValue */
        $iValue = [];
        /** @var string[] $iGlue */
        $iGlue = [];
        /** @var string[] $iGlueRaw */
        $iGlueRaw = [];
        $index = 0;
        $m = [];
        while (true) {
            Preg::match('{\G' . self::REGEX_TYPE . '(?<glue_raw>\h*(?<glue>[|&])\h*(?!$)|$)}', $this->value, $m, \PREG_OFFSET_CAPTURE, $index);
            if ($m === []) {
                throw new \RuntimeException('Unable to parse phpdoc type ' . \var_export($this->value, true));
            }
            $glue = self::cap($m, 'glue');
            if ($seen === null) {
                if ($glue === '') {
                    break;
                }
                $seen = ['|' => false, '&' => false];
            }
            if ($glue !== '') {
                $seen[$glue] = true;
            }
            $iStart[] = $index;
            $iValue[] = self::cap($m, 'type');
            $iGlue[] = $glue;
            $iGlueRaw[] = self::cap($m, 'glue_raw');
            $index += \strlen((string) $m[0][0]);
            if (\strlen($this->value) <= $index) {
                $this->composite = true;
                $this->glue = $seen['|'] ? '|' : '&';
                $both = $seen['|'] && $seen['&'];
                if (!$both) {
                    $c = \count($iValue);
                    for ($k = 0; $k < $c; $k++) {
                        $this->add($iStart[$k], $iValue[$k]);
                    }
                } else {
                    $c = \count($iValue);
                    for ($k = 0; $k < $c; $k++) {
                        $st = $iStart[$k];
                        $v = '';
                        while (true) {
                            $v .= $iValue[$k];
                            $g = $iGlue[$k] !== '' ? $iGlue[$k] : $this->glue;
                            if ($g === $this->glue) {
                                break;
                            }
                            $v .= $iGlueRaw[$k];
                            $k++;
                        }
                        $this->add($st, $v);
                    }
                }
                return;
            }
        }
        $this->composite = false;
        $nullable = self::cap($m, 'nullable');
        $array = self::cap($m, 'array');
        $type = self::cap($m, 'type');
        if ($nullable !== '') {
            $this->add(\strlen($nullable), \substr($type, \strlen($nullable)));
        } elseif ($array !== '') {
            $this->add(0, \substr($type, 0, -\strlen($array)));
        } elseif (self::cap($m, 'generic') !== '' && self::capAt($m, 'generic') === 0) {
            $this->add(0, self::cap($m, 'generic_name'));
            $this->parseCommaSeparatedInnerTypes(\strlen(self::cap($m, 'generic_name')) + \strlen(self::cap($m, 'generic_start')), self::cap($m, 'generic_types'));
        } elseif (self::cap($m, 'callable') !== '' && self::capAt($m, 'callable') === 0) {
            $this->add(0, self::cap($m, 'callable_name'));
            $this->parseCallableTemplateInnerTypes(
                \strlen(self::cap($m, 'callable_name')) + \strlen(self::cap($m, 'callable_template_start')),
                self::cap($m, 'callable_template_inners'),
            );
            $this->parseCallableArgumentTypes(\strlen(self::cap($m, 'callable_name')) + \strlen(self::cap($m, 'callable_template'))
                + \strlen(self::cap($m, 'callable_start')), self::cap($m, 'callable_arguments'));
            $ret = self::cap($m, 'callable_return');
            if ($ret !== '') {
                $this->add(\strlen($this->value) - \strlen($ret), $ret);
            }
        } elseif (self::cap($m, 'array_shape') !== '' && self::capAt($m, 'array_shape') === 0) {
            $this->add(0, self::cap($m, 'array_shape_name'));
            $next = \strlen(self::cap($m, 'array_shape_name')) + \strlen(self::cap($m, 'array_shape_start'));
            $this->parseArrayShapeInnerTypes($next, self::cap($m, 'array_shape_inners'));
            if (self::cap($m, 'array_shape_unsealed_type') !== '') {
                $next += \strlen(self::cap($m, 'array_shape_inners')) + \strlen(self::cap($m, 'array_shape_unsealed_variadic'))
                    + \strlen(self::cap($m, 'array_shape_unsealed_type_start'));
                $this->add($next, self::cap($m, 'array_shape_unsealed_type_a'));
                if (self::cap($m, 'array_shape_unsealed_type_b') !== '') {
                    $next += \strlen(self::cap($m, 'array_shape_unsealed_type_a')) + \strlen(self::cap($m, 'array_shape_unsealed_type_comma'));
                    $this->add($next, self::cap($m, 'array_shape_unsealed_type_b'));
                }
            }
        } elseif (self::cap($m, 'parenthesized') !== '' && self::capAt($m, 'parenthesized') === 0) {
            $index = \strlen(self::cap($m, 'parenthesized_start'));
            if (self::cap($m, 'conditional') !== '') {
                if (self::cap($m, 'conditional_cond_left_types') !== '') {
                    $this->add($index, self::cap($m, 'conditional_cond_left_types'));
                }
                $index += \strlen(self::cap($m, 'conditional_cond_left')) + \strlen(self::cap($m, 'conditional_cond_middle'));
                $this->add($index, self::cap($m, 'conditional_cond_right_types'));
                $index += \strlen(self::cap($m, 'conditional_cond_right_types')) + \strlen(self::cap($m, 'conditional_true_start'));
                $this->add($index, self::cap($m, 'conditional_true_types'));
                $index += \strlen(self::cap($m, 'conditional_true_types')) + \strlen(self::cap($m, 'conditional_false_start'));
                $this->add($index, self::cap($m, 'conditional_false_types'));
            } else {
                $this->add($index, self::cap($m, 'parenthesized_types'));
            }
        } elseif (self::cap($m, 'class_constant') !== '') {
            $this->add(0, self::cap($m, 'class_constant_name'));
        }
    }

    private function parseCommaSeparatedInnerTypes(int $startIndex, string $value): void
    {
        $index = 0;
        while (\strlen($value) !== $index) {
            Preg::match('{\G' . self::REGEX_TYPES . '(?:\h*,[\h\v*]*|$)}', $value, $m, 0, $index);
            $this->add($startIndex + $index, (string) ($m['types'] ?? ''));
            $index += \strlen((string) ($m[0] ?? ''));
        }
    }

    private function parseCallableTemplateInnerTypes(int $startIndex, string $value): void
    {
        $index = 0;
        while (\strlen($value) !== $index) {
            Preg::match('{\G(?:(?=1)0' . self::REGEX_TYPES . '|(?<_callable_template_inner>(?&callable_template_inner))(?:\h*,\h*|$))}', $value, $pm, 0, $index);
            $consumed = (string) ($pm['_callable_template_inner'] ?? '');
            $cl = \strlen($consumed);
            $comma = \strlen((string) ($pm[0] ?? '')) - $cl;
            $prefix = 'Closure<';
            Preg::match('{^' . self::REGEX_TYPES . '$}', $prefix . $consumed . '>(): void', $m, \PREG_OFFSET_CAPTURE);
            if (self::cap($m, 'callable_template_inner_b') !== '') {
                $this->add($startIndex + $index + self::capAt($m, 'callable_template_inner_b_types') - \strlen($prefix), self::cap($m, 'callable_template_inner_b_types'));
            }
            if (self::cap($m, 'callable_template_inner_d') !== '') {
                $this->add($startIndex + $index + self::capAt($m, 'callable_template_inner_d_types') - \strlen($prefix), self::cap($m, 'callable_template_inner_d_types'));
            }
            $index += $cl + $comma;
        }
    }

    private function parseCallableArgumentTypes(int $startIndex, string $value): void
    {
        $index = 0;
        while (\strlen($value) !== $index) {
            Preg::match('{\G(?:(?=1)0' . self::REGEX_TYPES . '|(?<_callable_argument>(?&callable_argument))(?:\h*,\h*|$))}', $value, $pm, 0, $index);
            $consumed = (string) ($pm['_callable_argument'] ?? '');
            $cl = \strlen($consumed);
            $comma = \strlen((string) ($pm[0] ?? '')) - $cl;
            Preg::match('{^' . self::REGEX_TYPES . '$}', 'Closure(' . $consumed . '): void', $m, \PREG_OFFSET_CAPTURE);
            $this->add($startIndex + $index, self::cap($m, 'callable_argument_type'));
            $index += $cl + $comma;
        }
    }

    private function parseArrayShapeInnerTypes(int $startIndex, string $value): void
    {
        $index = 0;
        while (\strlen($value) !== $index) {
            Preg::match('{\G(?:(?=1)0' . self::REGEX_TYPES . '|(?<_array_shape_inner>(?&array_shape_inner))(?:\h*,[\h\v*]*|$))}', $value, $pm, 0, $index);
            $consumed = (string) ($pm['_array_shape_inner'] ?? '');
            $cl = \strlen($consumed);
            $comma = \strlen((string) ($pm[0] ?? '')) - $cl;
            $prefix = 'array{';
            Preg::match('{^' . self::REGEX_TYPES . '$}', $prefix . $consumed . '}', $m, \PREG_OFFSET_CAPTURE);
            $this->add($startIndex + $index + self::capAt($m, 'array_shape_inner_value') - \strlen($prefix), self::cap($m, 'array_shape_inner_value'));
            $index += $cl + $comma;
        }
    }

    private function inner(string $value): TypeExpression
    {
        return new TypeExpression($value, $this->ns);
    }

    private function getParentType(string $type1, string $type2): ?string
    {
        $types = [$this->normalize($type1), $this->normalize($type2)];
        \natcasesort($types);
        $key = \implode('|', $types);
        if ($key === 'array|Traversable' || $key === 'array|iterable' || $key === 'iterable|Traversable') {
            return 'iterable';
        }
        return $key === 'self|static' ? 'self' : null;
    }

    private function normalize(string $type): string
    {
        if (isset(self::ALIASES[$type])) {
            return self::ALIASES[$type];
        }
        if (\in_array($type, ['array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'null', 'object', 'resource',
            'string', 'true', 'void'], true)) {
            return $type;
        }
        if (Preg::test('/\[\]$/', $type)) {
            return 'array';
        }
        if (Preg::match('/^(.+?)</', $type, $m)) {
            return (string) $m[1];
        }
        if (\str_starts_with($type, '\\')) {
            return \substr($type, 1);
        }
        $ns = $this->ns;
        if ($ns !== null) {
            foreach ($ns->short as $k => $short) {
                if ($short === $type) {
                    return $ns->full[$k];
                }
            }
        }
        if ($ns === null || $ns->ns === '') {
            return $type;
        }
        return $ns->ns . '\\' . $type;
    }
}
