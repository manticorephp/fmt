<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Runs gap rules in cs-fixer priority order (desc; ties keep the order given). Adjacent rules that cannot see each
 * other's neighbour traffic are fused into one per-token pipeline; the rest run as separate passes, which is
 * exactly what upstream does. Edit rules + the rebuild phase are not implemented (see EditRule).
 */
final class Engine
{
    /**
     * @param GapRule[] $rules
     * @return GapRule[] stable sort by priority desc
     */
    public static function sort(array $rules): array
    {
        $out = [];
        foreach ($rules as $r) {
            $pos = \count($out);
            while ($pos > 0 && $out[$pos - 1]->priority() < $r->priority()) {
                $pos--;
            }
            \array_splice($out, $pos, 0, [$r]);
        }
        return $out;
    }

    /**
     * Order sensitivity between a (earlier) and b (later): a rule that reads neighbour data must not be fused with
     * one that writes anything, and a rule that writes a neighbour must not be fused with one that touches anything.
     */
    public static function conflicts(GapRule $a, GapRule $b): bool
    {
        $nb = GapRule::F_NB;
        if ((($a->reads() & $nb) !== 0 && $b->writes() !== 0) || (($b->reads() & $nb) !== 0 && $a->writes() !== 0)) {
            return true;
        }
        if ((($a->writes() & $nb) !== 0 && ($b->reads() | $b->writes()) !== 0) || (($b->writes() & $nb) !== 0 && ($a->reads() | $a->writes()) !== 0)) {
            return true;
        }
        return false;
    }

    /**
     * @param GapRule[] $sorted
     * @return GapRule[][]
     */
    public static function groups(array $sorted): array
    {
        $groups = [];
        $cur = [];
        foreach ($sorted as $r) {
            $ok = true;
            foreach ($cur as $m) {
                if (self::conflicts($m, $r)) {
                    $ok = false;
                    break;
                }
            }
            if (!$ok) {
                $groups[] = $cur;
                $cur = [];
            }
            $cur[] = $r;
        }
        if ($cur !== []) {
            $groups[] = $cur;
        }
        return $groups;
    }

    /** @param GapRule[] $sorted */
    public static function run(Source $s, array $sorted): void
    {
        $n = $s->n;
        foreach (self::groups($sorted) as $group) {
            /** @var GapRule[] $textRules */
            $textRules = [];
            foreach ($group as $r) {
                if ($r->usesText()) {
                    $textRules[] = $r;
                }
            }
            $masks = [];
            foreach ($group as $r) {
                $masks[] = $r->needsMask();
            }
            $cnt = \count($group);
            for ($i = 0; $i <= $n; $i++) {
                $force = $i === $n || ($i > 0 && $s->kind[$i - 1] === \__McTokId::T_OPEN_TAG);
                for ($k = 0; $k < $cnt; $k++) {
                    if ($force || ($s->gapFlags[$i] & $masks[$k]) !== 0) {
                        $gap = $s->gapBefore[$i];
                        $new = $group[$k]->fixGap($gap, $s, $i);
                        if ($new !== $gap) {
                            $s->setGap($i, $new);
                        }
                    }
                }
                if ($i < $n && $textRules !== []) {
                    foreach ($textRules as $r) {
                        $t = $r->fixText($s, $i);
                        if ($t !== null) {
                            $s->setText($i, $t);
                        }
                    }
                }
            }
        }
    }
}
