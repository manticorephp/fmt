<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer (priority 65). The elements of a class body (from the end of the
 * previous one to the element's `;` / body, plus trailing same-line blanks and comments) are stably sorted by their position in `order`
 * (types missing from it inherit a parent's position or go last; sort_algorithm none keeps the source order inside a position, alpha sorts
 * by name). A cheap walk over the Source decides whether a class is out of order; only then is the body copied into a FlatTokens window,
 * its elements cut out with upstream's indexes and written back sorted. A class that had elements is left behind at its last element:
 * classes nested in it are not visited (upstream's loop jumps there).
 */
final class OrderedClassElements extends EditRule
{
    private const HIERARCHY = [
        'use_trait' => null, 'public' => null, 'protected' => null, 'private' => null, 'case' => ['public'], 'constant' => null,
        'constant_public' => ['constant', 'public'], 'constant_protected' => ['constant', 'protected'], 'constant_private' => ['constant', 'private'],
        'property' => null, 'property_static' => ['property'], 'property_public' => ['property', 'public'], 'property_protected' => ['property', 'protected'],
        'property_private' => ['property', 'private'], 'property_public_abstract' => ['property_abstract', 'property_public'],
        'property_public_readonly' => ['property_readonly', 'property_public'], 'property_protected_abstract' => ['property_abstract', 'property_protected'],
        'property_protected_readonly' => ['property_readonly', 'property_protected'], 'property_private_readonly' => ['property_readonly', 'property_private'],
        'property_public_static' => ['property_static', 'property_public'], 'property_protected_static' => ['property_static', 'property_protected'],
        'property_private_static' => ['property_static', 'property_private'], 'method' => null, 'method_abstract' => ['method'], 'method_static' => ['method'],
        'method_public' => ['method', 'public'], 'method_protected' => ['method', 'protected'], 'method_private' => ['method', 'private'],
        'method_public_abstract' => ['method_abstract', 'method_public'], 'method_protected_abstract' => ['method_abstract', 'method_protected'],
        'method_private_abstract' => ['method_abstract', 'method_private'],
        'method_public_abstract_static' => ['method_abstract', 'method_static', 'method_public'],
        'method_protected_abstract_static' => ['method_abstract', 'method_static', 'method_protected'],
        'method_private_abstract_static' => ['method_abstract', 'method_static', 'method_private'],
        'method_public_static' => ['method_static', 'method_public'], 'method_protected_static' => ['method_static', 'method_protected'],
        'method_private_static' => ['method_static', 'method_private'],
    ];

    private const PHPUNIT = ['setupbeforeclass' => 1, 'dosetupbeforeclass' => 2, 'teardownafterclass' => 3, 'doteardownafterclass' => 4, 'setup' => 5,
        'dosetup' => 6, 'assertpreconditions' => 7, 'assertpostconditions' => 8, 'teardown' => 9, 'doteardown' => 10];

    /** @var array<string, int> */
    private array $pos = [];
    private FlatTokens $t;

    /** @param string[] $order */
    public function __construct(array $order, private bool $alpha = false, private bool $caseSensitive = false)
    {
        $this->t = new FlatTokens();
        /** @var array<string, int|null> $p */
        $p = [];
        $i = 0;
        foreach ($order as $ty) {
            $p[$ty] = $i++;
        }
        foreach (self::HIERARCHY as $ty => $parents) {
            if (isset($p[$ty])) {
                continue;
            }
            $p[$ty] = null;
            if ($parents === null) {
                continue;
            }
            foreach ($parents as $par) {
                if (isset($p[$par])) {
                    $p[$ty] = $p[$par];
                    break;
                }
            }
        }
        $last = \count($order);
        foreach ($p as $ty => $v) {
            $this->pos[$ty] = ($v ?? $last) * 10;
        }
    }

    public function name(): string
    {
        return 'ordered_class_elements';
    }

    public function priority(): int
    {
        return 65;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 1; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] !== 0 || !($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                continue;
            }
            while ($i < $n && !($s->kind[$i] === 123 && $s->ct[$i] === 0)) {
                $i++;
            }
            if ($i >= $n) {
                return;
            }
            $els = $this->elements($s, $i);
            if ($els === []) {
                continue;
            }
            $lastEnd = $els[\count($els) - 1][1];
            if ($this->outOfOrder($els)) {
                $this->sortBody($s, $i);
            }
            $i = $lastEnd;
        }
    }

    /**
     * The elements of the body opened at $brace, on the Source: [element token, its end token (without the trailing comments), position, name]
     * @return array<int, array{0:int,1:int,2:int,3:string}>
     */
    private function elements(Source $s, int $brace): array
    {
        $n = $s->n;
        $out = [];
        $i = $brace + 1;
        while ($i < $n) {
            $vis = 'public';
            $abstract = false;
            $static = false;
            $ro = false;
            for (; $i < $n; $i++) {
                $k = $s->kind[$i];
                $c = $s->ct[$i];
                if ($k === \__McTokId::T_ATTRIBUTE) {
                    $i = $s->pair[$i];
                    continue;
                }
                if ($k === 125 && $c === 0) {
                    return $out;
                }
                if ($c === 0 && $k === \__McTokId::T_ABSTRACT) {
                    $abstract = true;
                } elseif ($c === 0 && $k === \__McTokId::T_STATIC) {
                    $static = true;
                } elseif ($c === 0 && $k === \__McTokId::T_READONLY) {
                    $ro = true;
                } elseif ($c === 0 && ($k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE)) {
                    $vis = \strtolower($s->text($i));
                } elseif ($c === CT::USE_TRAIT || ($c === 0 && ($k === \__McTokId::T_CASE || $k === \__McTokId::T_CONST || $k === \__McTokId::T_VARIABLE || $k === \__McTokId::T_FUNCTION))) {
                    break;
                }
            }
            if ($i >= $n) {
                return $out;
            }
            [$pos, $name] = $this->position($s, $i, $vis, $abstract, $static, $ro);
            $end = self::endOf($s, $i);
            if ($end < 0) {
                return $out;
            }
            $out[] = [$i, $end, $pos, $name];
            $i = $end + 1;
        }
        return $out;
    }

    /** findElementEnd without its trailing comments: the `;`, or the closer of the body / hook list */
    private static function endOf(Source $s, int $i): int
    {
        $n = $s->n;
        for ($j = $i + 1; $j < $n; $j++) {
            $k = $s->kind[$j];
            $c = $s->ct[$j];
            if ($c === 0 && ($k === 40 || $k === 123 || $k === 59) || $c === CT::PROPERTY_HOOK_BRACE_OPEN) {
                break;
            }
        }
        if ($j >= $n) {
            return -1;
        }
        if ($s->kind[$j] === 40 && $s->ct[$j] === 0) {
            $j = $s->pair[$j];
            while ($j < $n && !(($s->kind[$j] === 123 || $s->kind[$j] === 59) && $s->ct[$j] === 0)) {
                $j++;
            }
            if ($j >= $n) {
                return -1;
            }
        }
        if (($s->kind[$j] === 123 && $s->ct[$j] === 0) || $s->ct[$j] === CT::PROPERTY_HOOK_BRACE_OPEN) {
            $j = $s->pair[$j];
        }
        return $j;
    }

    /** @return array{0:int,1:string} getTypePosition + the element's name */
    private function position(Source $s, int $i, string $vis, bool $abstract, bool $static, bool $ro): array
    {
        $k = $s->kind[$i];
        $c = $s->ct[$i];
        $name = '';
        if ($c === CT::USE_TRAIT) {
            $type = 'use_trait';
        } elseif ($k === \__McTokId::T_CASE) {
            $type = 'case';
        } elseif ($k === \__McTokId::T_CONST) {
            $type = 'constant';
        } elseif ($k === \__McTokId::T_VARIABLE) {
            $type = 'property';
            $name = $s->text($i);
        } else {
            $nm = $i + 1 < $s->n ? $s->text($i + 1) : '';
            $low = \strtolower($nm);
            $isStr = $i + 1 < $s->n && $s->kind[$i + 1] === \__McTokId::T_STRING && $s->ct[$i + 1] === 0;
            if ($isStr && $low === '__construct') {
                $type = 'construct';
            } elseif ($isStr && $low === '__destruct') {
                $type = 'destruct';
            } elseif ($isStr && isset(self::PHPUNIT[$low])) {
                $type = 'phpunit';
            } else {
                $type = \str_starts_with($nm, '__') ? 'magic' : 'method';
            }
            $name = $type === 'phpunit' ? $low : $nm;
        }
        if ($type === 'constant') {
            for ($q = $i + 1; $q < $s->n; $q++) {
                if ($s->kind[$q] === 61 && $s->ct[$q] === 0) {
                    $name = $s->text($q - 1);
                    break;
                }
            }
        } elseif ($type === 'use_trait' || $type === 'case') {
            $name = $i + 1 < $s->n ? $s->text($i + 1) : '';
        }
        if (($type === 'method' || $type === 'magic' || $type === 'phpunit') && isset($this->pos['method:' . $name])) {
            return [$this->pos['method:' . $name], $name];
        }
        if ($type === 'construct' || $type === 'destruct' || $type === 'magic' || $type === 'phpunit') {
            if (isset($this->pos[$type])) {
                $p = $this->pos[$type];
                return [$type === 'phpunit' ? $p + self::PHPUNIT[$name] : $p, $name];
            }
            $type = 'method';
        }
        if ($type === 'constant' || $type === 'property' || $type === 'method') {
            $type .= '_' . $vis . ($abstract ? '_abstract' : '') . ($static ? '_static' : '') . ($ro ? '_readonly' : '');
        }
        return [$this->pos[$type] ?? 0, $name];
    }

    /** @param array<int, array{0:int,1:int,2:int,3:string}> $els */
    private function cmp(array $a, array $b): int
    {
        if ($a[2] !== $b[2]) {
            return $a[2] <=> $b[2];
        }
        if ($this->alpha) {
            return $this->caseSensitive ? $a[3] <=> $b[3] : \strcasecmp($a[3], $b[3]);
        }
        return $a[0] <=> $b[0];
    }

    /** @param array<int, array{0:int,1:int,2:int,3:string}> $els */
    private function outOfOrder(array $els): bool
    {
        $m = \count($els);
        for ($i = 1; $i < $m; $i++) {
            if ($this->cmp($els[$i - 1], $els[$i]) > 0) {
                return true;
            }
        }
        return false;
    }

    /** getElements + sortElements + sortTokens on a FlatTokens window over the body */
    private function sortBody(Source $s, int $brace): void
    {
        $close = $s->pair[$brace];
        if ($close < 0) {
            return;
        }
        $els = $this->elements($s, $brace);
        $t = $this->t;
        $t->load($s, $brace, $close);
        $m = $t->count();
        $ranges = [];
        $start = 1;
        foreach ($els as $e) {
            // findElementEnd: past the end token, same-line blanks and comments; a whitespace item last is given back
            $q = $t->pos[$e[1]] + 1;
            while ($q < $m && ($t->k[$q] === FlatTokens::COMMENT || ($t->k[$q] === FlatTokens::WS && \strspn($t->c[$q], " \t") === \strlen($t->c[$q])))) {
                $q++;
            }
            $q--;
            $end = $t->k[$q] === FlatTokens::WS ? $q - 1 : $q;
            $ranges[] = [$start, $end, $e];
            $start = $end + 1;
        }
        $order = $ranges;
        $cnt = \count($order);
        for ($i = 1; $i < $cnt; $i++) {
            $x = $order[$i];
            $j = $i - 1;
            while ($j >= 0 && $this->cmp($order[$j][2], $x[2]) > 0) {
                $order[$j + 1] = $order[$j];
                $j--;
            }
            $order[$j + 1] = $x;
        }
        // the window [`{` .. `}`] with items 1..lastEnd in the sorted order; emit records only what moved (none when nothing did)
        $k = [$t->k[0]];
        $c = [$t->c[0]];
        $ix = [$t->i[0]];
        foreach ($order as $r) {
            for ($q = $r[0]; $q <= $r[1]; $q++) {
                $k[] = $t->k[$q];
                $c[] = $t->c[$q];
                $ix[] = $t->i[$q];
            }
        }
        for ($q = $ranges[$cnt - 1][1] + 1; $q < $m; $q++) {
            $k[] = $t->k[$q];
            $c[] = $t->c[$q];
            $ix[] = $t->i[$q];
        }
        $t->k = $k;
        $t->c = $c;
        $t->i = $ix;
        $t->emit($s, $brace, $close, false, false, true);
    }
}
