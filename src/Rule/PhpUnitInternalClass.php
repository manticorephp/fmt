<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitInternalClassFixer (priority 68). A PHPUnit test class of an allowed kind (types: normal, final, abstract) gets an
 * `@internal` annotation: appended to its docblock (a one-line docblock is made multi-line), or a new docblock is created in front of the class. An anonymous class is skipped.
 */
final class PhpUnitInternalClass extends PhpUnitClassRule
{
    /** @param string[] $types */
    public function __construct(private array $types, private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_internal_class';
    }

    public function priority(): int
    {
        return 68;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        $classIndex = -1;
        for ($j = $start - 1; $j >= 0; $j--) {
            if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_CLASS) {
                $classIndex = $j;
                break;
            }
        }
        if ($classIndex < 0) {
            return;
        }
        $prev = $t->meaningful($classIndex, -1);
        if ($prev >= 0 && self::is($t, $prev, \__McTokId::T_NEW)) {
            return;
        }
        [$final, $abstract] = self::classyModifiers($t, $classIndex);
        if ($final) {
            $allowed = \in_array('final', $this->types, true);
        } elseif ($abstract) {
            $allowed = \in_array('abstract', $this->types, true);
        } else {
            $allowed = \in_array('normal', $this->types, true);
        }
        if (!$allowed) {
            return;
        }
        $this->ensureDocAnnotation($t, $classIndex, 'internal', ['internal'], [], $this->lineEnding);
    }
}
