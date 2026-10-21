<?php

declare(strict_types=1);

namespace Fmt;

/**
 * AbstractPhpUnitFixer + DocBlockAnnotationTrait on a whole-file FlatTokens: `applyClass` is called for every PHPUnit test class (a class that extends something and
 * names a `...Test` / `...TestCase` type in its signature) from the last to the first, with the flat indexes of its `{` and `}`.
 */
abstract class PhpUnitClassRule extends FlatRule
{
    protected Source $src;

    abstract protected function applyClass(FlatTokens $t, int $start, int $end): void;

    protected function candidate(Source $s): bool
    {
        // PhpUnitTestCaseAnalyzer::isPhpUnitClass on the Source: a class that extends something and names a `...Test` / `...TestCase` type in its signature
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_CLASS || $s->ct[$i] !== 0) {
                continue;
            }
            $ext = false;
            for ($j = $i + 1; $j < $s->n && !($s->kind[$j] === 123 && $s->ct[$j] === 0); $j++) {
                if ($s->kind[$j] === \__McTokId::T_EXTENDS) {
                    $ext = true;
                } elseif ($ext && $s->kind[$j] === \__McTokId::T_STRING && \preg_match('/(?:Test|TestCase)(?:Interface)?$/', $s->text($j)) === 1) {
                    return true;
                }
            }
            if ($ext && $i + 1 < $s->n && \preg_match('/(?:Test|TestCase)(?:Interface)?$/', $s->text($i + 1)) === 1) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        for ($q = $t->count() - 1; $q > 0; $q--) {
            if (!$this->isPhpUnitClass($t, $q)) {
                continue;
            }
            $open = self::nextOf($t, $q, 123);
            $close = $t->blockEnd($open, 125);
            if ($close < 0) {
                return;
            }
            $this->applyClass($t, $open, $close);
            if ($s->abort) {
                return;
            }
        }
    }

    /** PhpUnitTestCaseAnalyzer::isPhpUnitClass */
    protected function isPhpUnitClass(FlatTokens $t, int $q): bool
    {
        if (!self::is($t, $q, \__McTokId::T_CLASS)) {
            return false;
        }
        $m = $t->count();
        $e = -1;
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && ($t->k[$j] === 123 || $t->k[$j] === \__McTokId::T_EXTENDS)) {
                $e = $j;
                break;
            }
        }
        if ($e < 0 || $t->k[$e] !== \__McTokId::T_EXTENDS) {
            return false;
        }
        if (\preg_match('/(?:Test|TestCase)$/', $t->content($q)) === 1) {
            return true;
        }
        for ($j = $t->meaningful($q, 1); $j >= 0; $j = $t->meaningful($j, 1)) {
            if ($t->k[$j] === 123) {
                break;
            }
            if ($t->k[$j] !== \__McTokId::T_STRING) {
                continue;
            }
            if (\preg_match('/(?:Test|TestCase)(?:Interface)?$/', $t->content($j)) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * TokensAnalyzer::getClassyModifiers: [final, abstract]
     * @return bool[]
     */
    protected static function classyModifiers(FlatTokens $t, int $index): array
    {
        $final = false;
        $abstract = false;
        while (true) {
            $index = $t->meaningful($index, -1);
            if ($index < 0) {
                break;
            }
            if (self::is($t, $index, \__McTokId::T_FINAL)) {
                $final = true;
            } elseif (self::is($t, $index, \__McTokId::T_ABSTRACT)) {
                $abstract = true;
            } elseif (self::is($t, $index, \__McTokId::T_READONLY)) {
                continue;
            } else {
                break;
            }
        }
        return [$final, $abstract];
    }

    /** TokensAnalyzer::isAnonymousClass for a T_CLASS at $index */
    protected static function isAnonymousClass(FlatTokens $t, int $index): bool
    {
        $index = $t->meaningful($index, -1);
        if ($index >= 0 && self::is($t, $index, \__McTokId::T_READONLY)) {
            $index = $t->meaningful($index, -1);
        }
        while ($index >= 0 && self::is($t, $index, CT::ATTRIBUTE_CLOSE)) {
            $index = $t->blockStart($index, \__McTokId::T_ATTRIBUTE);
            $index = $index < 0 ? -1 : $t->meaningful($index, -1);
        }
        return $index >= 0 && self::is($t, $index, \__McTokId::T_NEW);
    }

    /**
     * AttributeAnalyzer::collect + collectAttributes, names only: the names of every attribute of the group (adjacent `#[...]`) that holds the `#[` at $a, as [name, source index
     * of the `#[`] (the name is resolved against the file's imports at that source index).
     * @return array<int, array{0:string,1:int}>
     */
    protected static function attributeNames(FlatTokens $t, int $a): array
    {
        $first = $a;
        while (true) {
            $p = $t->meaningful($first, -1);
            if ($p >= 0 && $t->i[$p] !== -1 && $t->k[$p] === CT::ATTRIBUTE_CLOSE) {
                $first = $t->blockStart($p, \__McTokId::T_ATTRIBUTE);
                if ($first < 0) {
                    return [];
                }
                continue;
            }
            break;
        }
        $out = [];
        $o = $first;
        do {
            $close = $t->blockEnd($o, CT::ATTRIBUTE_CLOSE);
            if ($close < 0) {
                break;
            }
            $depth = 0;
            $name = '';
            $atStart = true;
            for ($j = $o + 1; $j < $close; $j++) {
                if ($t->i[$j] === -1) {
                    continue;
                }
                $k = $t->k[$j];
                if ($k === 40 || $k === 91) {
                    $depth++;
                    $atStart = false;
                } elseif ($k === 41 || $k === 93) {
                    $depth--;
                } elseif ($depth === 0 && $k === 44) {
                    if ($name !== '') {
                        $out[] = [$name, $t->i[$o]];
                    }
                    $name = '';
                    $atStart = true;
                } elseif ($depth === 0 && $atStart && ($k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR)) {
                    $name .= $t->content($j);
                } elseif ($depth === 0) {
                    $atStart = false;
                }
            }
            if ($name !== '') {
                $out[] = [$name, $t->i[$o]];
            }
            $o = $t->meaningful($close, 1);
        } while ($o >= 0 && $t->i[$o] !== -1 && $t->k[$o] === \__McTokId::T_ATTRIBUTE);
        return $out;
    }

    /** FullyQualifiedNameAnalyzer::getFullyQualifiedName for a class name written at source index $idx */
    protected function fqn(string $name, int $idx): string
    {
        if ($name[0] === '\\') {
            return \ltrim($name, '\\');
        }
        $s = $this->src;
        $ns = '';
        $a = 0;
        $b = $s->n - 1;
        foreach (NsAnalysis::scopes($s) as [$nm, $x, $y]) {
            if ($idx >= $x && $idx <= $y) {
                $ns = $nm;
                $a = $x;
                $b = $y;
                break;
            }
        }
        $decl = [];
        foreach (NsAnalysis::singleImports($s) as $im) {
            if ($im[0] === 0 && $im[3] >= $a && $im[3] <= $b) {
                $decl[\strtolower($im[2])] = $im[1];
            }
        }
        $ln = \strtolower($name);
        foreach ($decl as $short => $full) {
            if ($ln === $short) {
                return \ltrim($full, '\\');
            }
            if (\str_starts_with($ln, $short . '\\')) {
                return \ltrim($full . \substr($name, \strlen($short)), '\\');
            }
        }
        return \ltrim($ns . '\\' . $name, '\\');
    }

    /** DocBlockAnnotationTrait::getDocBlockIndex */
    protected function docBlockIndex(FlatTokens $t, int $index): int
    {
        do {
            $index = $t->nonWhitespace($index, -1);
            if ($index < 0) {
                return -1;
            }
            if ($t->i[$index] !== -1 && $t->k[$index] === CT::ATTRIBUTE_CLOSE) {
                $index = $t->blockStart($index, \__McTokId::T_ATTRIBUTE);
                if ($index < 0) {
                    return -1;
                }
            }
            $k = $t->k[$index];
            $code = $t->i[$index] !== -1;
        } while (($code && ($k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_FINAL
            || $k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_ATTRIBUTE || $k === \__McTokId::T_READONLY))
            || ($t->isComment($index) && !Trivia::isDoc($t->c[$index])));
        return $index;
    }

    /**
     * DocBlockAnnotationTrait::ensureIsDocBlockWithAnnotation
     * @param string[] $preventingAnnotations
     * @param string[] $preventingAttributes lower-case fully qualified names
     */
    protected function ensureDocAnnotation(FlatTokens $t, int $index, string $annotation, array $preventingAnnotations, array $preventingAttributes, string $le): void
    {
        $di = $this->docBlockIndex($t, $index);
        if ($di < 0) {
            $this->src->abort = true;
            return;
        }
        if ($this->preventedByAttribute($t, $index, $preventingAttributes)) {
            return;
        }
        if ($t->isComment($di) && Trivia::isDoc($t->c[$di])) {
            $this->updateDocBlock($t, $di, $annotation, $preventingAnnotations, $le);
        } else {
            $this->createDocBlock($t, $di, $annotation, $le);
        }
    }

    /** @param string[] $preventingAttributes */
    private function preventedByAttribute(FlatTokens $t, int $index, array $preventingAttributes): bool
    {
        if ($preventingAttributes === []) {
            return false;
        }
        do {
            $index = $t->meaningful($index, -1);
            if ($index < 0) {
                return false;
            }
        } while (self::is($t, $index, \__McTokId::T_FINAL) || self::is($t, $index, \__McTokId::T_READONLY));
        if (!self::is($t, $index, CT::ATTRIBUTE_CLOSE)) {
            return false;
        }
        $index = $t->blockStart($index, \__McTokId::T_ATTRIBUTE);
        if ($index < 0) {
            return false;
        }
        foreach (self::attributeNames($t, $index) as [$name, $at]) {
            if (\in_array(\strtolower($this->fqn($name, $at)), $preventingAttributes, true)) {
                return true;
            }
        }
        return false;
    }

    /** @param string[] $preventing */
    private function updateDocBlock(FlatTokens $t, int $di, string $annotation, array $preventing, string $le): void
    {
        $doc = new Doc\DocBlock($t->c[$di]);
        foreach ($preventing as $p) {
            if ($doc->getAnnotationsOfType([$p]) !== []) {
                return;
            }
        }
        if (\count($doc->lines) === 1 && $doc->getAnnotationsOfType([$annotation]) === []) {
            $nx = $t->nonWhitespace($di, 1);
            $doc->makeMultiLine($nx < 0 ? '' : self::detectIndent($t, $nx), $le);
        }
        $lines = $doc->lines;
        $indent = self::detectIndent($t, $di);
        \array_splice($lines, -1, 0, [new Doc\Line($indent . ' * @' . $annotation . $le, false)]);
        $out = '';
        foreach ($lines as $l) {
            $out .= $l->get();
        }
        $t->c[$di] = $out;
    }

    protected function createDocBlock(FlatTokens $t, int $di, string $annotation, string $le): void
    {
        $nx = $t->nonWhitespace($di, 1);
        $indent = $nx < 0 ? '' : self::detectIndent($t, $nx);
        $index = $t->meaningful($di, 1);
        if ($index < 0) {
            $this->src->abort = true;
            return;
        }
        $t->insertAt($index, [[FlatTokens::COMMENT, "/**{$le}{$indent} * @{$annotation}{$le}{$indent} */", -1], FlatTokens::ws($le . $indent)]);
        $before = $index - 1;
        $isWs = $t->isWs($before) && $t->c[$before] !== '';
        if (!$isWs) {
            $extra = $le;
            if (!($t->i[$before] !== -1 && $t->k[$before] === \__McTokId::T_OPEN_TAG)) {
                $extra .= $le;
            }
            $t->insertAt($index, [FlatTokens::ws($extra . self::detectIndent($t, $index))]);
        }
    }
}
