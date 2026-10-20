<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer (priority 55). The line breaks between class elements (methods, properties,
 * constants, trait imports, enum cases): `one` = a blank line, `none` = adjacent (a blank line anyway when the element above is of another kind, or carries a
 * docblock / attribute), `only_if_meta`; the first element sits on its own line under `{`, the last one directly above `}`; comments, docblocks and attributes
 * above an element travel with it. Only whitespace changes: ported on a whole-file FlatTokens (upstream's indexes), the result goes back as gaps.
 */
final class ClassAttributesSeparation extends FlatRule
{
    /** @var array<string, string> element type => none|one|only_if_meta */
    private array $spacing;
    /** @var array<int, bool> */
    private array $modifier = [];

    /** @param array<string, string> $spacing */
    public function __construct(array $spacing, private string $le)
    {
        parent::__construct();
        $this->gapsOnly = true;
        $this->spacing = $spacing;
        foreach ([\__McTokId::T_PRIVATE, \__McTokId::T_PROTECTED, \__McTokId::T_PUBLIC, \__McTokId::T_ABSTRACT, \__McTokId::T_FINAL, \__McTokId::T_STATIC, \__McTokId::T_STRING,
            \__McTokId::T_NS_SEPARATOR, \__McTokId::T_VAR, CT::NULLABLE_TYPE, CT::ARRAY_TYPEHINT, CT::TYPE_ALTERNATION, CT::TYPE_INTERSECTION,
            CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN, CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE, \__McTokId::T_READONLY, \__McTokId::T_PRIVATE_SET,
            \__McTokId::T_PROTECTED_SET, \__McTokId::T_PUBLIC_SET] as $k) {
            $this->modifier[$k] = true;
        }
    }

    public function name(): string
    {
        return 'class_attributes_separation';
    }

    public function priority(): int
    {
        return 55;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                return true;
            }
        }
        return false;
    }

    private static function content(FlatTokens $t, int $q): string
    {
        return $t->i[$q] !== -1 ? $t->content($q) : $t->c[$q];
    }

    private static function nl(FlatTokens $t, int $q): int
    {
        return \substr_count(self::content($t, $q), "\n");
    }

    private static function isNonDocComment(FlatTokens $t, int $q): bool
    {
        return $t->isComment($q) && !Trivia::isDoc($t->c[$q]);
    }

    private static function isMeta(FlatTokens $t, int $q): bool
    {
        return $q >= 0 && (($t->isComment($q) && Trivia::isDoc($t->c[$q])) || ($t->i[$q] !== -1 && $t->k[$q] === CT::ATTRIBUTE_CLOSE));
    }

    private Source $src;

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        // upstream: getClassyElements reversed, grouped by consecutive class; start / end computed per class when it is complete
        $els = Analyzer::classyElements($s);
        $names = [Analyzer::E_PROPERTY => 'property', Analyzer::E_METHOD => 'method', Analyzer::E_PROMOTED => 'promoted_property', Analyzer::E_CONST => 'const',
            Analyzer::E_TRAIT_IMPORT => 'trait_import', Analyzer::E_CASE => 'case'];
        $rev = \array_reverse($els, true);
        $group = [];
        $cls = -1;
        $groups = [];
        foreach ($rev as $idx => [$type, $ci]) {
            if ($cls !== $ci) {
                if ($group !== []) {
                    $groups[] = $group;
                }
                $group = [];
                $cls = $ci;
            }
            $group[] = [$idx, $names[$type], $ci];
        }
        if ($group !== []) {
            $groups[] = $group;
        }
        foreach ($groups as $g) {
            $ci = $t->pos[$g[0][2]];
            $open = self::nextOf($t, $ci, 123);
            $close = $t->blockEnd($open, 125);
            $elements = [];
            foreach ($g as [$idx, $type]) {
                $q = $t->pos[$idx];
                $elements[] = ['q' => $q, 'type' => $type, 'start' => $this->first($t, $open, $q), 'end' => $this->last($t, $ci, $q, $type)];
            }
            $class = ['open' => $open, 'close' => $close];
            if (isset($this->spacing[$elements[0]['type']])) {
                $this->below($t, $class, $elements[0]);
            }
            foreach ($elements as $k => $e) {
                if (isset($this->spacing[$e['type']])) {
                    $this->above($t, $class, $elements, $k);
                }
            }
        }
    }

    private function first(FlatTokens $t, int $open, int $q): int
    {
        $f = $q;
        do {
            $p = $t->meaningful($f, -1);
            if ($p >= 0 && $t->i[$p] !== -1 && isset($this->modifier[$t->k[$p]])) {
                $f = $p;
            } else {
                break;
            }
        } while ($f > $open);
        return $f;
    }

    private function last(FlatTokens $t, int $ci, int $q, string $type): int
    {
        if ($type === 'method' && $t->k[$ci] !== \__McTokId::T_INTERFACE) {
            $abstract = false;
            for ($i = $q; $i >= 0; $i--) {
                $i = $t->meaningful($i, -1);
                if ($i < 0) {
                    break;
                }
                $k = $t->k[$i];
                if ($k === \__McTokId::T_ABSTRACT) {
                    $abstract = true;
                    continue;
                }
                if ($k === \__McTokId::T_STATIC || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC) {
                    continue;
                }
                break;
            }
            $end = $abstract ? self::nextOf($t, $q, 59) : $t->blockEnd(self::nextOf($t, $q, 123), 125);
        } elseif ($type === 'trait_import') {
            $end = $q;
            do {
                $end = $t->meaningful($end, 1);
            } while ($end >= 0 && $t->i[$end] !== -1 && ($t->k[$end] === \__McTokId::T_STRING || $t->k[$end] === \__McTokId::T_NS_SEPARATOR || $t->k[$end] === 44));
            if ($end < 0 || $t->k[$end] !== 59) {
                $end = $t->blockEnd(self::nextOf($t, $q, 123), 125);
            }
        } elseif ($type === 'property') {
            $m = $t->count();
            $end = -1;
            for ($j = $q + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 59 || $t->k[$j] === CT::PROPERTY_HOOK_BRACE_OPEN)) {
                    $end = $j;
                    break;
                }
            }
            if ($end >= 0 && $t->k[$end] === CT::PROPERTY_HOOK_BRACE_OPEN) {
                $end = $t->blockEnd($end, CT::PROPERTY_HOOK_BRACE_CLOSE);
            }
        } else {
            $m = $t->count();
            $end = -1;
            for ($j = $q + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 59 || $t->k[$j] === 123)) {
                    $end = $j;
                    break;
                }
            }
        }
        if ($end < 0) {
            return $q;
        }
        $single = true;
        for ($i = $q + 1; $i < $end; $i++) {
            if (\str_contains(self::content($t, $i), "\n")) {
                $single = false;
                break;
            }
        }
        if ($single) {
            $m = $t->count();
            while ($end + 1 < $m) {
                $n = $end + 1;
                if (($t->isComment($n) || $t->isWs($n)) && !\str_contains(self::content($t, $n), "\n")) {
                    $end++;
                } else {
                    break;
                }
            }
            if ($t->isWs($end)) {
                $end = $t->nonWhitespace($end, -1);
            }
        }
        return $end;
    }

    /** @param array{open:int,close:int} $class @param array{q:int,type:string,start:int,end:int} $e */
    private function below(FlatTokens $t, array $class, array $e): void
    {
        if ($class['close'] === $t->nonWhitespace($e['end'], 1)) {
            $this->correct($t, $e['end'], $class['close'], 1);
        }
    }

    /**
     * @param array{open:int,close:int} $class
     * @param array<int, array{q:int,type:string,start:int,end:int}> $els
     */
    private function above(FlatTokens $t, array $class, array $els, int $k): void
    {
        $e = $els[$k];
        $aboveEnd = isset($els[$k + 1]) ? $els[$k + 1]['end'] : 0;
        $na = $t->nonWhitespace($e['start'], -1);
        if ($na === $class['open']) {
            $this->correct($t, $na, $e['start'], 1);
            return;
        }
        if (self::isNonDocComment($t, $na)) {
            if ($aboveEnd === $na) {
                $this->correct($t, $na, $e['start'], $this->required($t, $els, $k));
                return;
            }
            if ($t->isWs($na + 1) && self::nl($t, $na + 1) > 1) {
                $this->correct($t, $na, $e['start'], 2);
                return;
            }
            if ($e['start'] - $na === 1 || ($t->isWs($na - 1) && self::nl($t, $na - 1) > 0) || ($t->isWs($na + 1) && self::nl($t, $na + 1) > 0)) {
                $this->correct($t, $na, $e['start'], 1);
                $na = $this->commentBlockStart($t, $na, $aboveEnd);
                $nac = $t->nonWhitespace($na, -1);
                if ($nac === $class['open']) {
                    if ($t->isWs($nac - 1) && self::nl($t, $nac - 1) > 0) {
                        $this->correct($t, $nac, $na, 1);
                    }
                } else {
                    $this->correct($t, $nac, $na, 2);
                }
            } else {
                $this->correct($t, $na, $e['start'], 2);
            }
            return;
        }
        if (self::isMeta($t, $na)) {
            $this->correct($t, $na, $e['start'], 1);
            $na = $this->commentBlockStart($t, $na, $aboveEnd);
            $nac = $t->nonWhitespace($na, -1);
            $this->correct($t, $nac, $na, $nac === $class['open'] ? 1 : 2);
            return;
        }
        $this->correct($t, $na, $e['start'], $this->required($t, $els, $k));
    }

    /** @param array<int, array{q:int,type:string,start:int,end:int}> $els */
    private function required(FlatTokens $t, array $els, int $k): int
    {
        $type = $els[$k]['type'];
        $sp = $this->spacing[$type];
        if ($sp === 'one') {
            return 2;
        }
        if ($sp === 'none') {
            $above = $els[$k + 1] ?? null;
            if ($above === null) {
                $this->src->abort = true; // upstream's \assert(isset(elements[i + 1])) fails: the file is reported and left as it is
                return 2;
            }
            if ($above['type'] !== $type) {
                return 2;
            }
            return self::isMeta($t, $t->nonWhitespace($above['start'], -1)) ? 2 : 1;
        }
        return self::isMeta($t, $t->nonWhitespace($els[$k]['start'], -1)) ? 2 : 1;
    }

    private function correct(FlatTokens $t, int $start, int $end, int $req): void
    {
        $start++;
        $white = $end - $start;
        if ($white === 0) {
            $t->insertAt($start, [FlatTokens::ws(\str_repeat($this->le, $req))]);
            return;
        }
        $have = 0;
        for ($i = $start; $i < $end; $i++) {
            $have += self::nl($t, $i);
        }
        if ($have === $req) {
            return;
        }
        if ($have < $req) {
            $t->c[$start] = \str_repeat($this->le, $req - $have) . $t->content($start);
            if ($t->i[$start] !== -1) {
                $t->i[$start] = -1; // a code item cannot get a prefix: upstream only calls this on whitespace
            }
            return;
        }
        if ($white === 1) {
            $t->c[$start] = \preg_replace('/\r\n|\n/', '', $t->c[$start], $have - $req) ?? $t->c[$start];
        }
    }

    private function commentBlockStart(FlatTokens $t, int $start, int $aboveEnd): int
    {
        for ($i = $start; $i > $aboveEnd; $i--) {
            if ($t->i[$i] !== -1 && $t->k[$i] === CT::ATTRIBUTE_CLOSE) {
                $start = $i = $t->blockStart($i, \__McTokId::T_ATTRIBUTE);
                continue;
            }
            if ($t->isComment($i)) {
                $start = $i;
                continue;
            }
            if (!$t->isWs($i) || self::nl($t, $i) > 1) {
                break;
            }
        }
        return $start;
    }
}
