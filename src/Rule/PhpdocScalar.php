<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocTypesRule;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocScalarFixer (priority 15): `integer` -> `int`, `boolean` -> `bool`, ... in phpdoc types. */
final class PhpdocScalar extends DocTypesRule
{
    public const TYPES_MAP = ['boolean' => 'bool', 'callback' => 'callable', 'double' => 'float', 'integer' => 'int', 'never-return' => 'never',
        'never-returns' => 'never', 'no-return' => 'never', 'real' => 'float', 'str' => 'string'];

    private string $probe;

    /** @param string[] $types */
    public function __construct(private array $types)
    {
        $q = [];
        foreach ($types as $t) {
            $q[] = \preg_quote($t, '/');
        }
        // a type is normalized only when it is a whole name: no name character (TypeExpression::SIMPLE_ATOM) on either side
        $this->probe = '/(?<![A-Za-z0-9_\x80-\xff\\\\-])(?:' . ($q === [] ? '(?!)' : \implode('|', $q)) . ')(?![A-Za-z0-9_\x80-\xff\\\\-])/';
    }

    public function name(): string
    {
        return 'phpdoc_scalar';
    }

    public function priority(): int
    {
        return 15;
    }

    protected function candidate(string $doc): bool
    {
        return \preg_match($this->probe, $doc) === 1;
    }

    protected function normalize(string $type): string
    {
        $suffix = '';
        while (\str_ends_with($type, '[]')) {
            $type = \substr($type, 0, -2);
            $suffix .= '[]';
        }
        if (\in_array($type, $this->types, true)) {
            $type = self::TYPES_MAP[$type];
        }
        return $type . $suffix;
    }
}
