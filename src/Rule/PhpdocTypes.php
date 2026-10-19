<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\TypeExpression;
use Fmt\DocTypesRule;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocTypesFixer (priority 16): the case of PHP's own types in phpdoc (`Int` -> `int`, `$This`). */
final class PhpdocTypes extends DocTypesRule
{
    public const GROUPS = [
        'alias' => ['boolean', 'double', 'integer'],
        'meta' => ['$this', 'false', 'mixed', 'parent', 'resource', 'scalar', 'self', 'static', 'true', 'void'],
        'simple' => ['array', 'bool', 'callable', 'float', 'int', 'iterable', 'null', 'object', 'string'],
    ];

    private string $probe;

    /** @param array<string, bool> $fix lowercase type => true */
    public function __construct(private array $fix)
    {
        $q = [];
        foreach ($fix as $t => $x) {
            $q[] = \preg_quote((string) $t, '/');
        }
        $this->probe = '/' . ($q === [] ? '(?!)' : \implode('|', $q)) . '/i';
    }

    public function name(): string
    {
        return 'phpdoc_types';
    }

    public function priority(): int
    {
        return 16;
    }

    protected function candidate(string $doc): bool
    {
        // a type changes only when one of the words occurs in another case than its lowercase one
        if (\preg_match_all($this->probe, $doc, $m) > 0) {
            foreach ($m[0] as $w) {
                if ($w !== \strtolower($w)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function normalize(string $type): string
    {
        $fix = $this->fix;
        return (new TypeExpression($type))->mapTypes(static function (TypeExpression $t) use ($fix): TypeExpression {
            if ($t->isUnionType()) {
                return $t;
            }
            $lower = \strtolower($t->toString());
            if (isset($fix[$lower])) {
                return new TypeExpression($lower);
            }
            return $t;
        })->toString();
    }
}
