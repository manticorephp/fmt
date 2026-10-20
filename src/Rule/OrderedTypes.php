<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\OrderedTypesFixer (priority 0). The members of a union / intersection / DNF type (catch,
 * property, parameter, return) are sorted (sort_algorithm alpha | none, null_adjustment always_first | always_last | none, case_sensitive)
 * with a stable sort, as PHP's usort; a changed type is rewritten by overrideRange (its trivia goes). Elements run in upstream's order
 * (class elements, then catch / functions, each ascending) on a whole-file FlatTokens, only for a file where the configuration can change
 * something.
 */
final class OrderedTypes extends EditRule
{
    private FlatTokens $t;
    private TypeWalk $w;
    private bool $changed = false;
    private bool $mangled = false;

    public function __construct(private bool $alpha = true, private string $null = 'always_first', private bool $caseSensitive = false)
    {
        $this->t = new FlatTokens();
        $this->w = new TypeWalk($this->t);
    }

    public function name(): string
    {
        return 'ordered_types';
    }

    public function priority(): int
    {
        return 0;
    }

    private function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 1; $i + 1 < $n; $i++) {
            $c = $s->ct[$i];
            if ($c !== CT::TYPE_ALTERNATION && $c !== CT::TYPE_INTERSECTION) {
                continue;
            }
            if ($this->alpha) {
                return true;
            }
            if ($this->null === 'always_last' && \strtolower($s->text($i - 1)) === 'null') {
                return true;
            }
            if ($this->null === 'always_first' && \strtolower($s->text($i + 1)) === 'null') {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        if (!$this->candidate($s)) {
            return;
        }
        $t = $this->t;
        $t->load($s, 0, $s->n - 1);
        $this->changed = false;
        $this->mangled = false;
        /** @var array<int, int> $el flat index => 0 function, 1 property, 2 catch, in upstream's insertion order */
        $el = [];
        foreach (Analyzer::classyElements($s) as $idx => $info) {
            if ($info[0] === Analyzer::E_METHOD || $info[0] === Analyzer::E_PROPERTY) {
                $el[$t->pos[$idx]] = $info[0] === Analyzer::E_PROPERTY ? 1 : 0;
            }
        }
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] !== 0) {
                continue;
            }
            if ($k === \__McTokId::T_CATCH) {
                $el[$t->pos[$i]] = 2;
            } elseif (($k === \__McTokId::T_FN || $k === \__McTokId::T_FUNCTION) && !isset($el[$t->pos[$i]])) {
                $el[$t->pos[$i]] = 0;
            }
        }
        foreach ($el as $q => $type) {
            if ($type === 2) {
                $open = $this->w->nextOfKind($q, 40);
                $end = $open + 1;
                $m = $t->count();
                while ($end < $m && !($t->isCode($end) && ($t->k[$end] === 41 || $t->k[$end] === \__McTokId::T_VARIABLE))) {
                    $end++;
                }
                $this->sortType($this->collect($open, $end));
                continue;
            }
            if ($type === 1) {
                $this->sortType($this->w->propertyType($q));
                continue;
            }
            foreach ($this->w->arguments($q) as $a) {
                $this->sortType($a);
            }
            $this->sortType($this->w->returnType($q));
        }
        if ($this->mangled) {
            $s->abort = true; // the later name of `public A|null $a, $b;` is typed `A|null$a,`: moving it makes upstream's result not lint
        } elseif ($this->changed) {
            $s->edit(Source::tokAt(0), Source::tokAt($s->n - 1) + 1, $t->render(), -1, $t->wsBoundaries());
        }
    }

    /** @return array{0:string,1:int,2:int}|null collectTypeAnalysis */
    private function collect(int $start, int $end): ?array
    {
        $t = $this->t;
        $ts = $t->meaningful($start, 1);
        $te = $ts;
        $type = '';
        for ($i = $ts; $i >= 0 && $i < $end; $i++) {
            if ($t->k[$i] === FlatTokens::WS || $t->k[$i] === FlatTokens::COMMENT) {
                continue;
            }
            $type .= $t->content($i);
            $te = $i;
        }
        return $type === '' ? null : [$type, $ts, $te];
    }

    /** @param array{0:string,1:int,2:int}|null $ta */
    private function sortType(?array $ta): void
    {
        if ($ta === null) {
            return;
        }
        $name = \str_starts_with($ta[0], '?') ? \substr($ta[0], 1) : $ta[0];
        if (!\str_contains($name, '|') && !\str_contains($name, '&')) {
            return;
        }
        /** @var array<int, string|string[]> $types */
        $types = [];
        if (\str_contains($name, '|') && \str_contains($name, '&')) {
            foreach (\explode('|', $name) as $sub) {
                $types[] = \str_starts_with($sub, '(') ? \explode('&', \trim($sub, '()')) : $sub;
            }
            $glue = '|';
        } else {
            $types = \explode('|', $name);
            $glue = '|';
            if (\count($types) === 1) {
                $types = \explode('&', $name);
                $glue = '&';
            }
        }
        $sorted = [];
        foreach ($types as $x) {
            $sorted[] = \is_array($x) ? $this->sorted($x) : $x;
        }
        $sorted = $this->sorted($sorted);
        if ($sorted === $types) {
            return;
        }
        $last = $types[\count($types) - 1];
        if (\is_string($last) && \str_contains($last, '$') && $sorted[\count($sorted) - 1] !== $last) {
            $this->mangled = true;
        }
        $this->w->overrideRange($ta[1], $ta[2], self::tokens($sorted, $glue, false));
        $this->changed = true;
    }

    /**
     * usort with upstream's comparator; insertion sort, stable as PHP's
     * @param array<int, string|string[]> $types
     * @return array<int, string|string[]>
     */
    private function sorted(array $types): array
    {
        $m = \count($types);
        for ($i = 1; $i < $m; $i++) {
            $x = $types[$i];
            $j = $i - 1;
            while ($j >= 0 && $this->cmp($types[$j], $x) > 0) {
                $types[$j + 1] = $types[$j];
                $j--;
            }
            $types[$j + 1] = $x;
        }
        return $types;
    }

    /**
     * @param string|string[] $a
     * @param string|string[] $b
     */
    private function cmp(string|array $a, string|array $b): int
    {
        $a = \is_array($a) ? \implode('&', $a) : $a;
        $b = \is_array($b) ? \implode('&', $b) : $b;
        $a = $a !== '' && $a[0] === '\\' ? \substr($a, 1) : $a;
        $b = $b !== '' && $b[0] === '\\' ? \substr($b, 1) : $b;
        $la = \strtolower($a);
        $lb = \strtolower($b);
        if ($this->null !== 'none') {
            if ($la === 'null' && $lb !== 'null') {
                return $this->null === 'always_last' ? 1 : -1;
            }
            if ($la !== 'null' && $lb === 'null') {
                return $this->null === 'always_last' ? -1 : 1;
            }
        }
        if ($this->alpha) {
            return $this->caseSensitive ? $a <=> $b : \strcasecmp($a, $b);
        }
        return 0;
    }

    /**
     * createTypeDeclarationTokens
     * @param array<int, string|string[]> $types
     * @return array<int, array{0:int,1:string,2:int}>
     */
    private static function tokens(array $types, string $glue, bool $dnf): array
    {
        $out = [];
        $count = \count($types);
        foreach ($types as $i => $type) {
            if (\is_array($type)) {
                foreach (self::tokens($type, '&', true) as $x) {
                    $out[] = $x;
                }
            } elseif ($type === 'array') {
                $out[] = FlatTokens::code(CT::ARRAY_TYPEHINT, 'array');
            } elseif ($type === 'callable') {
                $out[] = FlatTokens::code(\__McTokId::T_CALLABLE, 'callable');
            } elseif ($type === 'static') {
                $out[] = FlatTokens::code(\__McTokId::T_STATIC, 'static');
            } else {
                foreach (\explode('\\', $type) as $ni => $v) {
                    if ($ni === 0 && $v === '') {
                        continue;
                    }
                    if ($ni > 0) {
                        $out[] = FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\');
                    }
                    $out[] = FlatTokens::code(\__McTokId::T_STRING, $v);
                }
            }
            if ($i <= $count - 2) {
                $out[] = $glue === '|' ? FlatTokens::code(CT::TYPE_ALTERNATION, '|') : FlatTokens::code(CT::TYPE_INTERSECTION, '&');
            }
        }
        if ($dnf) {
            \array_unshift($out, FlatTokens::code(CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN, '('));
            $out[] = FlatTokens::code(CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE, ')');
        }
        return $out;
    }
}
