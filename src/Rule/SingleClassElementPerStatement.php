<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\SingleClassElementPerStatementFixer (priority 56). `const A = 1, B = 2;` /
 * `public $a, $b;` (option `elements`) become one statement each: from the last comma back (array values skipped), the comma turns into
 * `;`, the whitespace after it goes, the whitespace before the statement (cut to one line break + its indent) is inserted, then a copy
 * of the statement's head (modifiers + type, its whitespace included, comments dropped). Upstream's steps run on a FlatTokens window
 * over the statement, rendered back as one edit; the scan for a comma runs on the Source.
 */
final class SingleClassElementPerStatement extends EditRule
{
    private FlatTokens $t;
    private bool $const;
    private bool $property;

    /** @param string[] $elements */
    public function __construct(array $elements = ['const', 'property'])
    {
        $this->t = new FlatTokens();
        $this->const = \in_array('const', $elements, true);
        $this->property = \in_array('property', $elements, true);
    }

    public function name(): string
    {
        return 'single_class_element_per_statement';
    }

    public function priority(): int
    {
        return 56;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        $any = false;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                $any = true;
                break;
            }
        }
        if (!$any) {
            return;
        }
        $done = []; // statement start => true: a statement expanded for a later element is not expanded again (upstream finds `;` next)
        foreach (\array_reverse(Analyzer::classyElements($s), true) as $idx => $info) {
            $type = $info[0];
            if (!(($type === Analyzer::E_CONST && $this->const) || ($type === Analyzer::E_PROPERTY && $this->property))) {
                continue;
            }
            // fixElement: is a comma next, before `;` / a hook?
            $r = $idx;
            $comma = false;
            while (++$r < $n) {
                $k = $s->kind[$r];
                $c = $s->ct[$r];
                if ($c === 0 && $k === \__McTokId::T_ARRAY) {
                    while ($r < $n && !($s->kind[$r] === 40 && $s->ct[$r] === 0)) {
                        $r++;
                    }
                    $r = $r < $n ? $s->pair[$r] : $n;
                    continue;
                }
                if ($c === CT::ARRAY_BRACKET_OPEN) {
                    $r = $s->pair[$r];
                    continue;
                }
                if (($k === 59 && $c === 0) || $c === CT::PROPERTY_HOOK_BRACE_OPEN) {
                    break;
                }
                if ($k === 44 && $c === 0) {
                    $comma = true;
                    break;
                }
            }
            if (!$comma) {
                continue;
            }
            $st = $idx;
            while ($st >= 0 && !($s->ct[$st] === 0 && ($s->kind[$st] === 59 || $s->kind[$st] === 123 || $s->kind[$st] === 125))) {
                $st--;
            }
            $start = $st + 1;
            $end = $idx;
            while ($end < $n && !($s->kind[$end] === 59 && $s->ct[$end] === 0)) {
                $end++;
            }
            if ($end >= $n || isset($done[$start])) {
                continue;
            }
            $done[$start] = true;
            $this->expand($s, $type === Analyzer::E_CONST, $start, $end);
        }
    }

    private function expand(Source $s, bool $const, int $start, int $end): void
    {
        $div = null;
        $g = $s->gapBefore[$start];
        if ($g !== '') {
            $p = Gap::hasComment($g) ? Gap::pieces($g) : [$g];
            $last = $p[\count($p) - 1];
            if (Gap::isSpace($last)) {
                $div = $last;
                if (\preg_match('#(\n|\r\n)#', $div, $m) === 1) {
                    $div = $m[0] . \trim($div, "\r\n");
                }
            }
        }
        $t = $this->t;
        $t->load($s, $start, $end);
        $e = $t->count() - 1;
        for ($i = $e - 1; $i > 0; $i--) {
            if (!$t->isCode($i)) {
                continue;
            }
            $k = $t->k[$i];
            if ($k === 41) {
                $i = $t->blockStart($i, 40);
                continue;
            }
            if ($k === CT::ARRAY_BRACKET_CLOSE) {
                $i = $t->blockStart($i, CT::ARRAY_BRACKET_OPEN);
                continue;
            }
            if ($k !== 44) {
                continue;
            }
            $t->k[$i] = 59;
            $t->c[$i] = ';';
            $t->i[$i] = FlatTokens::NEW;
            if ($t->isWs($i + 1)) {
                $t->clearAt($i + 1);
            }
            if ($div !== null && $div !== '') {
                $t->insertAt($i + 1, [FlatTokens::ws($div)]);
            }
            $t->insertAt($i + 2, $const ? $this->constHead() : $this->propertyHead($e));
        }
        $s->edit(Source::tokAt($start), Source::tokAt($end) + 1, $t->render(), -1, $t->wsBoundaries());
    }

    /** @return array<int, array{0:int,1:string,2:int}> every item from the start to the name before the first `=`, comments dropped */
    private function constHead(): array
    {
        $t = $this->t;
        $m = $t->count();
        $eq = 1;
        while ($eq < $m && !($t->isCode($eq) && $t->k[$eq] === 61)) {
            $eq++;
        }
        $name = $t->meaningful($eq, -1);
        $out = [];
        for ($q = 0; $q < $name; $q++) {
            if (!$t->isComment($q)) {
                $out[] = $t->item($q);
            }
        }
        return $out;
    }

    /** @return array<int, array{0:int,1:string,2:int}> the modifiers and type from the start (whitespace kept, comments dropped) */
    private function propertyHead(int $end): array
    {
        $t = $this->t;
        $out = [];
        for ($q = 0; $q < $end - 1; $q++) {
            if ($t->isComment($q)) {
                continue;
            }
            if (!$t->isWs($q) && !self::headKind($t->k[$q])) {
                break;
            }
            $out[] = $t->item($q);
        }
        return $out;
    }

    private static function headKind(int $k): bool
    {
        return $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_FINAL
            || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_VAR || $k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR
            || $k === CT::NULLABLE_TYPE || $k === CT::ARRAY_TYPEHINT || $k === CT::TYPE_ALTERNATION || $k === CT::TYPE_INTERSECTION
            || $k === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN || $k === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE
            || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET;
    }
}
