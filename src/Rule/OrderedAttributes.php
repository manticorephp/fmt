<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatAttributeElement;
use Fmt\FlatAttributes;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\AttributeNotation\OrderedAttributesFixer (priority 0). The attributes inside every `#[A, B]` group, and the groups of a run of
 * `#[..] #[..]` groups (by the name of their first attribute after the inner sort), are sorted alphabetically or by a custom FQN order; names are compared as written
 * (leading backslash removed) for alpha, resolved for custom. The sort is stable. Whole-file FlatTokens port.
 */
final class OrderedAttributes extends FlatRule
{
    /** @param string[] $order FQNs without a leading backslash, in order */
    public function __construct(private bool $custom, private array $order)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'ordered_attributes';
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
            $groups = FlatAttributes::collect($t, $index);
            /** @var FlatAttributeElement[] $elements name = the name of the group's first attribute once sorted, [start, end] = the group */
            $elements = [];
            foreach ($groups as $g) {
                $elements[] = new FlatAttributeElement($g->start, $g->end, $this->sortAttributes($s, $t, $g->start, $g->attributes));
            }
            $endIndex = $elements[\count($elements) - 1]->end;
            if (\count($elements) > 1) {
                $sorted = $this->sorted($elements);
                if (!self::same($elements, $sorted)) {
                    $this->sortTokens($t, $index, $endIndex, $sorted, false);
                }
            }
            $index = $endIndex;
        }
    }

    /**
     * @param FlatAttributeElement[] $attributes
     */
    private function sortAttributes(Source $s, FlatTokens $t, int $index, array $attributes): string
    {
        if (\count($attributes) === 1) {
            return $this->nameOf($s, $t, $attributes[0]->name, $attributes[0]->start);
        }
        $named = [];
        foreach ($attributes as $a) {
            $named[] = new FlatAttributeElement($a->start, $a->end, $this->nameOf($s, $t, $a->name, $a->start));
        }
        $sorted = $this->sorted($named);
        if (self::same($named, $sorted)) {
            return $named[0]->name;
        }
        $this->sortTokens($t, $index + 1, $named[\count($named) - 1]->end, $sorted, true);
        return $sorted[0]->name;
    }

    private function nameOf(Source $s, FlatTokens $t, string $name, int $at): string
    {
        return $this->custom ? FlatAttributes::qualify($s, $t, $name, $at) : \ltrim($name, '\\');
    }

    /**
     * @param FlatAttributeElement[] $a
     * @param FlatAttributeElement[] $b
     */
    private static function same(array $a, array $b): bool
    {
        foreach ($a as $x => $e) {
            if ($e->start !== $b[$x]->start || $e->end !== $b[$x]->end || $e->name !== $b[$x]->name) {
                return false;
            }
        }
        return true;
    }

    /**
     * usort, which is stable since PHP 8.
     * @param FlatAttributeElement[] $elements
     * @return FlatAttributeElement[]
     */
    private function sorted(array $elements): array
    {
        $out = [];
        foreach ($elements as $e) {
            $pos = \count($out);
            while ($pos > 0 && $this->compare($out[$pos - 1]->name, $e->name) > 0) {
                $pos--;
            }
            $next = [];
            for ($x = 0; $x < $pos; $x++) {
                $next[] = $out[$x];
            }
            $next[] = $e;
            for ($x = $pos; $x < \count($out); $x++) {
                $next[] = $out[$x];
            }
            $out = $next;
        }
        return $out;
    }

    private function compare(string $a, string $b): int
    {
        if (!$this->custom) {
            return $a <=> $b;
        }
        $ia = \array_search($a, $this->order, true);
        $ib = \array_search($b, $this->order, true);
        return ($ia === false ? \PHP_INT_MAX : (int) $ia) <=> ($ib === false ? \PHP_INT_MAX : (int) $ib);
    }

    /**
     * @param FlatAttributeElement[] $elements
     */
    private function sortTokens(FlatTokens $t, int $start, int $end, array $elements, bool $delimiter): void
    {
        $items = [];
        $last = \count($elements) - 1;
        foreach ($elements as $pos => $e) {
            for ($i = $e->start; $i <= $e->end; ++$i) {
                $items[] = $t->item($i);
            }
            if ($delimiter && $pos !== $last) {
                $items[] = FlatTokens::code(44, ',');
            }
        }
        self::overrideRange($t, $start, $end, $items);
    }
}
