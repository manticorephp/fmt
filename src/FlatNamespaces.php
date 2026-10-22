<?php

declare(strict_types=1);

namespace Fmt;

/** NamespacesAnalyzer::getDeclarations and NamespaceUsesAnalyzer over a FlatTokens (upstream's indexes, trivia included). */
final class FlatNamespaces
{
    /**
     * getDeclarations: [full name, start index (the `namespace` item), scope end index].
     * @return array<int, array{0: string, 1: int, 2: int}>
     */
    public static function declarations(FlatTokens $t): array
    {
        $out = [];
        $count = $t->count();
        for ($index = 1; $index < $count; ++$index) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_NAMESPACE) {
                continue;
            }
            $declEnd = -1;
            for ($j = $index + 1; $j < $count; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 59 || $t->k[$j] === 123)) {
                    $declEnd = $j;
                    break;
                }
            }
            if ($declEnd < 0) {
                break;
            }
            $name = \trim(self::partial($t, $index + 1, $declEnd - 1));
            if ($t->k[$declEnd] === 123) {
                $scopeEnd = $t->blockEnd($declEnd, 125);
            } else {
                $scopeEnd = -1;
                for ($j = $declEnd + 1; $j < $count; $j++) {
                    if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_NAMESPACE) {
                        $scopeEnd = $j;
                        break;
                    }
                }
                if ($scopeEnd < 0) {
                    $scopeEnd = $count;
                }
                --$scopeEnd;
            }
            $out[] = [$name, $index, $scopeEnd];
            $index = $scopeEnd;
        }
        if ($out === []) {
            for ($i = 0; $i < $count; $i++) {
                if ($t->i[$i] !== -1 && $t->k[$i] === \__McTokId::T_OPEN_TAG) {
                    $start = $t->k[0] === \__McTokId::T_INLINE_HTML ? 1 : 0;
                    $out[] = ['', $start, $count - 1];
                    break;
                }
            }
        }
        return $out;
    }

    /** Tokens::generatePartialCode */
    public static function partial(FlatTokens $t, int $a, int $b): string
    {
        $r = '';
        for ($q = $a; $q <= $b && $q < $t->count(); $q++) {
            $r .= $t->content($q);
        }
        return $r;
    }

    /**
     * NamespaceUsesAnalyzer::getDeclarationsFromTokens (multi-imports out) as of the load of the flat list: [type (NsScan::USE_*), full name, short name, aliased, in multi,
     * the `use` item, the `;` item] with flat indexes.
     * @return array<int, array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int}>
     */
    public static function uses(Source $s, FlatTokens $t, bool $allowMulti): array
    {
        $out = [];
        foreach (Doc\NsScan::uses($s, $allowMulti) as $d) {
            $out[] = [$d[0], $d[1], $d[2], $d[3], $d[4], $t->pos[$d[5]], $t->pos[$d[6]]];
        }
        return $out;
    }
}
