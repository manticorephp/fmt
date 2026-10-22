<?php

declare(strict_types=1);

namespace Fmt;

/** AttributeAnalyzer::collect / collectOne (and collectAttributes) over a FlatTokens, with upstream's index arithmetic. */
final class FlatAttributes
{
    /**
     * collect: every `#[..]` group of the run of groups that contains the T_ATTRIBUTE item $index (walking back to the first).
     * @return FlatAttributeGroup[]
     */
    public static function collect(FlatTokens $t, int $index): array
    {
        while (true) {
            $prev = $t->meaningful($index, -1);
            if ($prev < 0 || $t->i[$prev] === -1 || $t->k[$prev] !== CT::ATTRIBUTE_CLOSE) {
                break;
            }
            $index = $t->blockStart($prev, \__McTokId::T_ATTRIBUTE);
        }
        $out = [];
        $opening = $index;
        do {
            $e = self::collectOne($t, $opening);
            $out[] = $e;
            $opening = $t->meaningful($e->end, 1);
        } while ($opening >= 0 && $t->i[$opening] !== -1 && $t->k[$opening] === \__McTokId::T_ATTRIBUTE);
        return $out;
    }

    public static function collectOne(FlatTokens $t, int $index): FlatAttributeGroup
    {
        $start = $index;
        $prev = $t->meaningful($index, -1);
        if ($prev >= 0 && $t->i[$prev] !== -1 && $t->k[$prev] === CT::ATTRIBUTE_CLOSE) {
            $start = $t->nonWhitespace($prev, 1);
        }
        $closing = $t->blockEnd($index, CT::ATTRIBUTE_CLOSE);
        $end = $t->nonWhitespace($closing, 1);
        if ($end < 0) {
            $end = $t->count();
        }
        return new FlatAttributeGroup($start, $end - 1, $index, $closing, self::attributes($t, $index, $closing));
    }

    /** @return FlatAttributeElement[] */
    private static function attributes(FlatTokens $t, int $index, int $closing): array
    {
        $out = [];
        do {
            $attrStart = $index + 1;
            $nameStart = self::nextOfAny($t, $index, [\__McTokId::T_STRING, \__McTokId::T_NS_SEPARATOR]);
            $index = self::nextOfAny($t, $attrStart, [40, 44, CT::ATTRIBUTE_CLOSE]);
            $name = '';
            $last = $t->meaningful($index, -1);
            for ($q = $nameStart; $q <= $last; $q++) {
                $name .= $t->content($q);
            }
            if ($t->k[$index] === 40) {
                $index = $t->blockEnd($index, 41);
                $index = self::nextOfAny($t, $index, [44, CT::ATTRIBUTE_CLOSE]);
            }
            $out[] = new FlatAttributeElement($attrStart, $index - 1, $name);
            $next = $index;
            if ($next < $closing) {
                $next = $t->meaningful($index, 1);
            }
        } while ($next < $closing);
        --$index;
        while ($index >= 0 && $t->k[$index] === FlatTokens::WS) {
            if (\preg_match('/\R/', $t->c[$index]) === 1) {
                $out[\count($out) - 1]->end = $index - 1;
                break;
            }
            --$index;
        }
        return $out;
    }

    /** @param int[] $ids */
    private static function nextOfAny(FlatTokens $t, int $q, array $ids): int
    {
        $m = $t->count();
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && \in_array($t->k[$j], $ids, true)) {
                return $j;
            }
        }
        return $m;
    }

    /**
     * AttributeAnalyzer::determineAttributeFullyQualifiedName for the attribute whose name starts at flat index $start (a T_STRING / T_NS_SEPARATOR is searched from
     * there when that item is neither), resolved against the class imports of its namespace.
     */
    public static function fullyQualifiedName(Source $s, FlatTokens $t, string $name, int $start): string
    {
        if ($name[0] === '\\') {
            return $name;
        }
        $index = $start;
        if (!($t->i[$index] !== -1 && ($t->k[$index] === \__McTokId::T_STRING || $t->k[$index] === \__McTokId::T_NS_SEPARATOR))) {
            $index = self::nextOfAny($t, $index, [\__McTokId::T_STRING, \__McTokId::T_NS_SEPARATOR]);
        }
        [$namespace, $uStart, $uEnd] = self::namespaceAt($t, $index);
        $first = $t->content($index);
        $import = null;
        foreach (FlatNamespaces::uses($s, $t, false) as $d) {
            if ($d[0] === Doc\NsScan::USE_CLASS && $d[5] >= $uStart && $d[5] <= $uEnd && $d[2] === $first) {
                $import = $d[1];
            }
        }
        if ($import !== null) {
            $namespace = $import;
            if ($name === $first) {
                return $namespace;
            }
            $cut = \strstr($name, '\\');
            $name = \substr($cut === false ? '' : $cut, 1);
        }
        return $namespace . '\\' . $name;
    }

    /**
     * FullyQualifiedNameAnalyzer::getFullyQualifiedName for a class name written at flat index $at: the class imports of the namespace (short names without case) first,
     * then the namespace itself; no leading backslash.
     */
    public static function qualify(Source $s, FlatTokens $t, string $name, int $at): string
    {
        if ($name[0] === '\\') {
            return \ltrim($name, '\\');
        }
        [$namespace, $uStart, $uEnd] = self::namespaceAt($t, $at);
        /** @var string[] $shorts */
        $shorts = [];
        /** @var string[] $fulls */
        $fulls = [];
        foreach (FlatNamespaces::uses($s, $t, false) as $d) {
            if ($d[0] !== Doc\NsScan::USE_CLASS || $d[5] < $uStart || $d[5] > $uEnd) {
                continue;
            }
            $short = \strtolower($d[2]);
            $at2 = \array_search($short, $shorts, true);
            if ($at2 === false) {
                $shorts[] = $short;
                $fulls[] = $d[1];
            } else {
                $fulls[(int) $at2] = $d[1];
            }
        }
        $lower = \strtolower($name);
        foreach ($shorts as $x => $short) {
            if ($lower === $short) {
                return \ltrim($fulls[$x], '\\');
            }
            if (\str_starts_with($lower, $short . '\\')) {
                return \ltrim($fulls[$x] . \substr($name, \strlen($short)), '\\');
            }
        }
        return \ltrim($namespace . '\\' . $name, '\\');
    }

    /** @return array{0: string, 1: int, 2: int} the namespace (name, scope start, scope end) holding flat item $at; the whole file when it has none */
    private static function namespaceAt(FlatTokens $t, int $at): array
    {
        foreach (FlatNamespaces::declarations($t) as [$nm, $a, $b]) {
            if ($a <= $at && $at <= $b) {
                return [$nm, $a, $b];
            }
        }
        return ['', 0, $t->count()];
    }
}