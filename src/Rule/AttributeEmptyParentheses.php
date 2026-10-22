<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\AttributeNotation\AttributeEmptyParenthesesFixer (priority 0). Every attribute of every `#[..]` group: its empty `()`
 * goes (use_parentheses false) or is added right behind the name (true). Whole-file FlatTokens port.
 */
final class AttributeEmptyParentheses extends FlatRule
{
    public function __construct(private bool $use)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'attribute_empty_parentheses';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_ATTRIBUTE) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $stops = [40, 44, CT::ATTRIBUTE_CLOSE];
        $index = 0;
        while (true) {
            $index = self::nextOf($t, $index, \__McTokId::T_ATTRIBUTE);
            if ($index < 0) {
                return;
            }
            $next = $index;
            do {
                $paren = self::nextOfAny($t, $next, $stops);
                if ($paren < 0) {
                    return;
                }
                if ($this->use) {
                    if (!self::is($t, $paren, 40)) {
                        $t->insertAt($t->meaningful($paren, -1) + 1, [FlatTokens::code(40, '('), FlatTokens::code(41, ')')]);
                    }
                } elseif (self::is($t, $paren, 40)) {
                    $closing = $t->meaningful($paren, 1);
                    if (self::is($t, $closing, 41)) {
                        self::clearMerge($t, $closing);
                        self::clearMerge($t, $paren);
                    }
                }
                $next = self::nextOfAny($t, $next, $stops);
                if ($next < 0) {
                    return;
                }
                if (self::is($t, $next, 40)) {
                    $next = $t->blockEnd($next, 41);
                    $next = self::nextOfAny($t, $next, [44, CT::ATTRIBUTE_CLOSE]);
                    if ($next < 0) {
                        return;
                    }
                }
                if (!self::is($t, $next, CT::ATTRIBUTE_CLOSE)) {
                    $next = $t->meaningful($next, 1);
                    if ($next < 0) {
                        return;
                    }
                }
            } while (!self::is($t, $next, CT::ATTRIBUTE_CLOSE));
        }
    }
}
