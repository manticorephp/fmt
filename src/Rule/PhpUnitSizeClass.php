<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitSizeClassFixer (priority 9). A non-abstract PHPUnit test class without a size annotation (`@small` `@medium` `@large`) or
 * attribute (`PHPUnit\Framework\Attributes\Small` ...) gets `@<group>`, appended to its docblock or in a new docblock. Same machinery as php_unit_internal_class.
 */
final class PhpUnitSizeClass extends PhpUnitClassRule
{
    public function __construct(private string $group, private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_size_class';
    }

    public function priority(): int
    {
        return 9;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        $classIndex = self::prevOf($t, $start, \__McTokId::T_CLASS);
        if ($classIndex < 0) {
            return;
        }
        $prev = $t->meaningful($classIndex, -1);
        if ($prev >= 0 && self::is($t, $prev, \__McTokId::T_ABSTRACT)) {
            return;
        }
        $this->ensureDocAnnotation($t, $classIndex, $this->group, ['small', 'medium', 'large'],
            ['phpunit\framework\attributes\small', 'phpunit\framework\attributes\medium', 'phpunit\framework\attributes\large'], $this->lineEnding);
    }
}
