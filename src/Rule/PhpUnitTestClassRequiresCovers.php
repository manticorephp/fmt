<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitTestClassRequiresCoversFixer (priority 9). A non-abstract, non-anonymous PHPUnit test class with no `@covers` /
 * `@coversDefaultClass` / `@coversNothing` annotation and no Covers* attribute gets `@coversNothing` (appended to its docblock, or in a new docblock).
 */
final class PhpUnitTestClassRequiresCovers extends PhpUnitClassRule
{
    public function __construct(private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_test_class_requires_covers';
    }

    public function priority(): int
    {
        return 9;
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
        if ($classIndex < 0 || self::isAnonymousClass($t, $classIndex)) {
            return;
        }
        [, $abstract] = self::classyModifiers($t, $classIndex);
        if ($abstract) {
            return;
        }
        $this->ensureDocAnnotation($t, $classIndex, 'coversNothing', ['covers', 'coversDefaultClass', 'coversNothing'], [
            'phpunit\framework\attributes\coversclass',
            'phpunit\framework\attributes\coversnothing',
            'phpunit\framework\attributes\coversmethod',
            'phpunit\framework\attributes\coversfunction',
            'phpunit\framework\attributes\coverstrait',
        ], $this->lineEnding);
    }
}
