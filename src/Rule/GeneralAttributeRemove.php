<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatAttributes;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\AttributeNotation\GeneralAttributeRemoveFixer (priority 0). Attributes whose fully qualified name (resolved against the use imports
 * of their namespace, with a leading backslash kept as written) is in `attributes` are cleared with their comma; a group left empty goes whole. Whole-file FlatTokens port.
 */
final class GeneralAttributeRemove extends FlatRule
{
    /** @param string[] $attributes */
    public function __construct(private array $attributes)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'general_attribute_remove';
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
        $index = 0;
        while (true) {
            $index = self::nextOf($t, $index, \__McTokId::T_ATTRIBUTE);
            if ($index < 0) {
                return;
            }
            $g = FlatAttributes::collectOne($t, $index);
            $end = $g->end;
            $removed = 0;
            foreach ($g->attributes as $el) {
                $full = FlatAttributes::fullyQualifiedName($s, $t, $el->name, $el->start);
                if (!\in_array($full, $this->attributes, true)) {
                    continue;
                }
                for ($i = $el->start; $i <= $el->end; ++$i) {
                    $t->clearAt($i);
                }
                ++$removed;
                $sibling = $t->nonEmptySibling($el->end, 1);
                if ($sibling >= 0 && $t->content($sibling) === ',') {
                    $t->clearAt($sibling);
                }
            }
            if (\count($g->attributes) === $removed) {
                for ($i = $g->start; $i <= $g->end; ++$i) {
                    $t->clearAt($i);
                }
            }
            $prev = $t->meaningful($g->close, -1);
            if ($prev >= 0 && $t->content($prev) === ',') {
                if ($prev < $g->start) {
                    $s->abort = true; // the whole group went and the walk back from its `]` met the comma BEFORE it (`f($a, #[X] $b)`): upstream's result does not lint
                }
                $t->clearAt($prev);
            }
            $index = $end;
        }
    }
}
