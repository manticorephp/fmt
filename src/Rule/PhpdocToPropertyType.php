<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpdocToTypeRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\FunctionNotation\PhpdocToPropertyTypeFixer (priority 8, risky, experimental). An untyped property (or property list) documented by `@var` of a type PHP can
 * declare gets that type. Class and trait bodies only, as upstream: the docblocks in front of the class are the only ones whose `@phpstan-type` aliases exclude a type.
 */
final class PhpdocToPropertyType extends PhpdocToTypeRule
{
    public function name(): string
    {
        return 'phpdoc_to_property_type';
    }

    public function priority(): int
    {
        return 8;
    }

    protected function template(): string
    {
        return 'property';
    }

    protected function tag(): string
    {
        return '@var';
    }

    protected function shape(Source $s, int $u): bool
    {
        return $s->kind[$u] === \__McTokId::T_VARIABLE;
    }

    protected function isSkippedType(string $type): bool
    {
        return $type === 'resource' || $type === 'null';
    }

    protected function apply(Source $s, FlatTokens $t): void
    {
        /** @var array<int, array<int, array{0:int,1:string,2:int}>> $slices */
        $slices = [];
        /** @var string[] $exclude */
        $exclude = [];
        for ($index = 0, $m = $t->count(); $index < $m; $index++) {
            if (self::isDocItem($t, $index)) {
                $x = self::typesToExclude($t->c[$index]);
                if ($x === null) {
                    $s->abort = true;
                    return;
                }
                \array_push($exclude, ...$x);
                continue;
            }
            if ($t->i[$index] !== -1 && ($t->k[$index] === \__McTokId::T_CLASS || $t->k[$index] === \__McTokId::T_TRAIT)) {
                $found = $this->fixClass($s, $t, $index, $exclude);
                if ($found === null) {
                    $s->abort = true;
                    return;
                }
                $slices += $found;
            }
        }
        $t->insertSlices($slices);
    }

    /**
     * @param string[] $exclude
     * @return array<int, array<int, array{0:int,1:string,2:int}>>|null null: upstream throws
     */
    private function fixClass(Source $s, FlatTokens $t, int $index, array $exclude): ?array
    {
        $slices = [];
        $index = self::nextOf($t, $index, 123);
        if ($index < 0) {
            return null;
        }
        $classEnd = $t->blockEnd($index, 125);
        if ($classEnd < 0) {
            return null;
        }
        for (; $index < $classEnd; $index++) {
            if ($t->i[$index] !== -1 && $t->k[$index] === \__McTokId::T_FUNCTION) {
                $index = self::nextBraceOrSemicolon($t, $index);
                if ($index < 0) {
                    return null;
                }
                if ($t->k[$index] === 123) {
                    $index = $t->blockEnd($index, 125);
                    if ($index < 0) {
                        return null;
                    }
                }
                continue;
            }
            if (!self::isDocItem($t, $index)) {
                continue;
            }
            $doc = $index;
            $props = $this->findNextUntypedPropertiesDeclaration($t, $doc);
            if ($props === null) {
                return null;
            }
            if ($props === []) {
                continue;
            }
            $annotations = $this->annotations('var', $t, $doc);
            if ($annotations === null) {
                return null;
            }
            $info = $this->resolveApplicableType($props, $annotations);
            if ($info === null) {
                continue;
            }
            $type = $info['commonType'];
            $nullable = $info['isNullable'];
            if (\in_array($type, ['callable', 'never', 'void'], true)) {
                continue;
            }
            if (\in_array($type, $exclude, true)) {
                continue;
            }
            $ok = $this->syntax($type);
            if ($ok < 0) {
                return null;
            }
            if ($ok === 0) {
                continue;
            }
            $first = \reset($props);
            $pm = $t->meaningful($first, -1);
            if ($pm < 0 || !\in_array($t->k[$pm], [\__McTokId::T_PRIVATE, \__McTokId::T_PROTECTED, \__McTokId::T_PUBLIC, \__McTokId::T_STATIC, \__McTokId::T_VAR], true)) {
                return null; // the docblock sits inside a property list (`private $a, /** @var int */ $b;`): the type lands behind a comma, upstream's lint rejects the file
            }
            $items = $this->typeItems($type, $nullable);
            $items[] = FlatTokens::ws(' ');
            $slices[$first] = $items;
            $index = \max($props) + 1;
        }
        return $slices;
    }

    /** getNextTokenOfKind($index, ['{', ';']) */
    private static function nextBraceOrSemicolon(FlatTokens $t, int $q): int
    {
        for ($j = $q + 1, $m = $t->count(); $j < $m; $j++) {
            if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === 59)) {
                return $j;
            }
        }
        return -1;
    }

    /**
     * @return array<string, int>|null variable name => item index; null: upstream runs off the end of the file
     */
    private function findNextUntypedPropertiesDeclaration(FlatTokens $t, int $index): ?array
    {
        do {
            $index = $t->meaningful($index, 1);
            if ($index < 0) {
                return null;
            }
        } while (\in_array($t->k[$index], [\__McTokId::T_PRIVATE, \__McTokId::T_PROTECTED, \__McTokId::T_PUBLIC, \__McTokId::T_STATIC, \__McTokId::T_VAR], true));
        if ($t->k[$index] !== \__McTokId::T_VARIABLE) {
            return [];
        }
        /** @var array<string, int> $props */
        $props = [];
        while (!($t->k[$index] === 59 && $t->i[$index] !== -1)) {
            if ($t->k[$index] === \__McTokId::T_VARIABLE) {
                $props[$t->content($index)] = $index;
            }
            $index = $t->meaningful($index, 1);
            if ($index < 0) {
                return null;
            }
        }
        return $props;
    }

    /**
     * @param array<string, int> $props
     * @param \Fmt\Doc\Annotation[] $annotations
     * @return array{commonType: string, isNullable: bool}|null
     */
    private function resolveApplicableType(array $props, array $annotations): ?array
    {
        /** @var array<string, array{commonType: string, isNullable: bool}> $types */
        $types = [];
        foreach ($annotations as $a) {
            $name = $a->getVariableName();
            if ($name === null) {
                if (\count($props) !== 1) {
                    continue;
                }
                $name = (string) \array_key_first($props);
            }
            if (!isset($props[$name])) {
                continue;
            }
            $te = $a->getTypeExpression();
            if ($te === null) {
                continue;
            }
            $info = $this->getCommonTypeInfo($te, false);
            $union = null;
            if ($info === null) {
                $union = $this->getUnionTypes($te, false);
            }
            if ($info === null && $union === null) {
                continue;
            }
            if ($union !== null) {
                $info = ['commonType' => $union, 'isNullable' => false];
            }
            /** @var array{commonType: string, isNullable: bool} $info */
            if (isset($types[$name]) && ($info['commonType'] !== $types[$name]['commonType'] || $info['isNullable'] !== $types[$name]['isNullable'])) {
                return null;
            }
            $types[$name] = $info;
        }
        if (\count($types) !== \count($props)) {
            return null;
        }
        $first = null;
        foreach ($types as $ty) {
            if ($first === null) {
                $first = $ty;
                continue;
            }
            if ($ty['commonType'] !== $first['commonType'] || $ty['isNullable'] !== $first['isNullable']) {
                return null;
            }
        }
        return $first;
    }
}
