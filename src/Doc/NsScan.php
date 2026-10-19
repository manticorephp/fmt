<?php

declare(strict_types=1);

namespace Fmt\Doc;

use Fmt\CT;
use Fmt\Source;

/**
 * Upstream's NamespacesAnalyzer / NamespaceUsesAnalyzer over a Source, for the rules that resolve names in docblocks.
 * Namespace scopes are in token terms: a docblock in gap g lies in the scope [start, end] when start < g <= end (upstream's
 * scope runs from the `namespace` keyword to its `}`, or to the trivia right before the next `namespace`).
 */
final class NsScan
{
    /** @var string[] namespace names (trimmed, trivia between the keyword and `;` / `{` included as upstream) */
    public array $name = [];
    /** @var int[] the `namespace` token */
    public array $start = [];
    /** @var int[] the last token of the scope: the `}` of a braced namespace, the token before the next `namespace` / the last token */
    public array $end = [];
    /** @var int[] the `;` / `{` that ends the declaration */
    public array $declEnd = [];
    /** @var int[] the last TOKEN of the scope */
    public array $tokEnd = [];
    /** true: a file without a namespace, one global scope */
    public bool $global = false;

    /** text of tokens a..b with the gaps between them (upstream's generatePartialCode over a token range with its trivia) */
    public static function partial(Source $s, int $a, int $b, bool $leadingGap = false): string
    {
        $out = '';
        for ($t = $a; $t <= $b; $t++) {
            if ($t > $a || $leadingGap) {
                $out .= $s->gapBefore[$t];
            }
            $out .= $s->text($t);
        }
        return $out;
    }

    public static function scan(Source $s): NsScan
    {
        $r = new NsScan();
        $n = $s->n;
        for ($i = 1; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_NAMESPACE || $s->ct[$i] !== 0) {
                continue;
            }
            $e = $i + 1;
            while ($e < $n && !($s->kind[$e] === 59 || ($s->kind[$e] === 123 && $s->ct[$e] === 0))) {
                $e++;
            }
            if ($e >= $n) {
                break;
            }
            // generatePartialCode(index + 1, declEnd - 1): the trivia right after the keyword up to the trivia before `;` / `{`
            $txt = $e - 1 > $i ? self::partial($s, $i + 1, $e - 1, true) . $s->gapBefore[$e] : $s->gapBefore[$e];
            if ($s->kind[$e] === 123) {
                $end = $s->pair[$e] >= 0 ? $s->pair[$e] : $n - 1;
            } else {
                $nx = -1;
                for ($x = $e + 1; $x < $n; $x++) {
                    if ($s->kind[$x] === \__McTokId::T_NAMESPACE) {
                        $nx = $x;
                        break;
                    }
                }
                $end = $nx < 0 ? $n : $nx; // scope ends in the gap before the next namespace (or the trailing trivia)
            }
            $r->name[] = \trim($txt);
            $r->start[] = $i;
            $r->end[] = $end;
            $r->declEnd[] = $e;
            $r->tokEnd[] = $s->kind[$e] === 123 ? $end : $end - 1;
            $i = $end;
        }
        if ($r->name === [] && $n > 0) {
            $r->global = true;
        }
        return $r;
    }

    /**
     * The scope FunctionsAnalyzer uses for token $t: [start, end, global]; outside every declaration it keeps the last one (as upstream).
     * @return array{0: int, 1: int, 2: bool}
     */
    public function tokenScope(int $t, int $n = 0): array
    {
        if ($this->global) {
            return [0, \PHP_INT_MAX, true];
        }
        $r = [0, \PHP_INT_MAX, false];
        foreach ($this->name as $k => $nm) {
            $r = [$this->start[$k], $this->tokEnd[$k], false];
            if ($t >= $this->start[$k] && $t <= $this->tokEnd[$k]) {
                return [$this->start[$k], $this->tokEnd[$k], $nm === ''];
            }
        }
        return $r;
    }

    /** the namespace name for a docblock in gap $g; null when no scope holds it (upstream throws) */
    public function at(int $g): ?string
    {
        if ($this->global) {
            return '';
        }
        foreach ($this->name as $k => $nm) {
            if ($this->start[$k] < $g && $g <= $this->end[$k]) {
                return $nm;
            }
        }
        return null;
    }

    public const USE_CLASS = 1;
    public const USE_FUNCTION = 2;
    public const USE_CONST = 3;

    /**
     * NamespaceUsesAnalyzer::getDeclarationsFromTokens: every import of the file (T_USE outside classes and closures), in order, as
     * [type (USE_*), full name, short name, aliased (0/1), in a multi / group use (0/1), the `use` token, the `;` / close tag token].
     * @return array<int, array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int}>
     */
    public static function uses(Source $s, bool $allowMulti): array
    {
        $out = [];
        $n = $s->n;
        for ($u = 0; $u < $n; $u++) {
            if ($s->kind[$u] !== \__McTokId::T_USE || $s->ct[$u] !== 0) {
                continue;
            }
            $end = $u + 1;
            while ($end < $n && $s->kind[$end] !== 59 && $s->kind[$end] !== \__McTokId::T_CLOSE_TAG) {
                $end++;
            }
            $tk = $u + 1 < $n ? $s->ct[$u + 1] : 0;
            $type = $tk === CT::FUNCTION_IMPORT ? self::USE_FUNCTION : ($tk === CT::CONST_IMPORT ? self::USE_CONST : self::USE_CLASS);
            $multi = 0;
            for ($x = $u + 1; $x < $n && $x < $end; $x++) {
                if (($s->kind[$x] === 44 && $s->ct[$x] === 0) || $s->ct[$x] === CT::GROUP_IMPORT_BRACE_OPEN) {
                    $multi = 1;
                    break;
                }
            }
            $index = $u + 1;
            while ($index < $n && !(($s->kind[$index] === \__McTokId::T_STRING || $s->kind[$index] === \__McTokId::T_NS_SEPARATOR) && $s->ct[$index] === 0)) {
                $index++;
            }
            while ($index < $n && $index <= $end) {
                [$full, $short, $aliased, $after] = self::qualifiedName($s, $index);
                if ($after < $n && $s->ct[$after] === CT::GROUP_IMPORT_BRACE_OPEN) {
                    $gi = $after;
                    $ge = $s->pair[$after];
                    while ($gi < $ge) {
                        $chunk = $gi + 1;
                        if ($chunk >= $n || $s->ct[$chunk] === CT::GROUP_IMPORT_BRACE_CLOSE) {
                            break;
                        }
                        [$gf, $gs, $ga, $gafter] = self::qualifiedName($s, $chunk);
                        $out[] = [$type, $full . $gf, $gs, $ga ? 1 : 0, 1, $u, $end];
                        $gi = $gafter;
                    }
                    $index = $gi;
                } elseif ($after < $n && ($s->kind[$after] === 44 || $s->kind[$after] === 59 || $s->kind[$after] === \__McTokId::T_CLOSE_TAG)) {
                    if ($s->ct[$after - 1] !== CT::GROUP_IMPORT_BRACE_CLOSE) {
                        $out[] = [$type, $full, $short, $aliased ? 1 : 0, $multi, $u, $end];
                    }
                    $index = $after;
                }
                $index++;
            }
        }
        if (!$allowMulti) {
            $keep = [];
            foreach ($out as $d) {
                if ($d[4] === 0) {
                    $keep[] = $d;
                }
            }
            return $keep;
        }
        return $out;
    }

    /** @return array{0: string, 1: string, 2: bool, 3: int} full name, short name, aliased, the token that ends it */
    private static function qualifiedName(Source $s, int $index): array
    {
        $full = '';
        $short = '';
        $aliased = false;
        $n = $s->n;
        for (; $index < $n; $index++) {
            $k = $s->kind[$index];
            $c = $s->ct[$index];
            if ($k === \__McTokId::T_STRING && $c === 0) {
                $short = $s->text($index);
                if (!$aliased) {
                    $full .= $short;
                }
            } elseif ($k === \__McTokId::T_NS_SEPARATOR) {
                $full .= $s->text($index);
            } elseif ($k === \__McTokId::T_AS) {
                $aliased = true;
            } elseif ($k === 44 || $k === 59 || $c === CT::GROUP_IMPORT_BRACE_OPEN || $c === CT::GROUP_IMPORT_BRACE_CLOSE || $k === \__McTokId::T_CLOSE_TAG) {
                break;
            }
        }
        return [$full, $short, $aliased, $index];
    }

    /**
     * NamespaceUsesAnalyzer::getDeclarationsFromTokens (multi-uses filtered out): lowercase short name => lowercase full name.
     * @return array<string, string>
     */
    public static function shortNames(Source $s): array
    {
        $out = [];
        foreach (self::uses($s, false) as $d) {
            $out[\strtolower($d[2])] = \strtolower($d[1]);
        }
        return $out;
    }
}
