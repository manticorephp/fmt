<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Runs gap rules in cs-fixer priority order (desc; ties keep the order given). Adjacent rules that cannot see each
 * other's neighbour traffic are fused into one per-token pipeline; the rest run as separate passes, which is
 * exactly what upstream does. An EditRule is a file rule whose recorded token edits rebuild the Source right after it.
 */
final class Engine
{
    /**
     * @param GapRule[] $rules
     * @return GapRule[] priority desc, then rule name (upstream's order for equal priorities)
     */
    public static function sort(array $rules): array
    {
        $out = [];
        foreach ($rules as $r) {
            $pos = \count($out);
            // equal priorities: by rule name, which is the order upstream's FixerFactory ends up with (verified on the @Symfony set)
            while ($pos > 0 && ($out[$pos - 1]->sortPriority() < $r->sortPriority()
                || ($out[$pos - 1]->sortPriority() === $r->sortPriority() && \strcmp($out[$pos - 1]->sortName(), $r->sortName()) > 0))) {
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
        if ($a->isFileRule() || $b->isFileRule()) {
            return true;
        }
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

    /**
     * One pass per fused group. Per token i: the gap before it (rules in order, each skipped by its needsMask unless the
     * gap is the trailing one), then its text. The only cross-token traffic of the P0 rules (F_NB_OPEN) is the gap right
     * after an open tag, so that gap and the open tag's text are run rule by rule instead, exactly as upstream
     * does, and the per-token step skips them.
     * @param GapRule[] $sorted
     */
    public static function run(Source $s, array $sorted): Source
    {
        // prepare() runs right before the group of its rule does: the earlier groups (a file rule moves comments, tokens never move)
        // have rewritten the file by then, exactly what upstream's rule sees
        foreach (self::groups($sorted) as $planned) {
            $group = [];
            foreach ($planned as $r) {
                if ($r->prepare($s)) {
                    $group[] = $r;
                }
            }
            if ($group === []) {
                continue;
            }
            if ($group[0]->isFileRule()) {
                $group[0]->fixFile($s);
                if ($s->edits !== []) {
                    $s = $s->rebuild(); // an EditRule changed tokens: the next rule sees the re-parsed file
                }
                continue;
            }
            $n = $s->n;
            $cnt = \count($group);
            /** @var int[] $masks */
            $masks = [];
            /** @var bool[] $hasText */
            $hasText = [];
            $any = 0;
            $anyText = false;
            $hit = \array_fill(0, 512, 0);
            // kind triggers: bit k of tp[kind] = rule k runs on the gap right AFTER a token of that kind, tn[kind] = right BEFORE
            $tp = \array_fill(0, 512, 0);
            $tn = \array_fill(0, 512, 0);
            $trig = false;
            $bit = 0;
            foreach ($group as $r) {
                foreach ($r->afterKinds() as $tk) {
                    $tp[$tk] |= 1 << $bit;
                    $trig = true;
                }
                foreach ($r->beforeKinds() as $tk) {
                    $tn[$tk] |= 1 << $bit;
                    $trig = true;
                }
                $bit++;
                $m = $r->needsMask();
                $masks[] = $m;
                $any |= $m;
                $t = $r->usesText($s);
                $hasText[] = $t;
                if ($t) {
                    $anyText = true;
                    foreach ($r->textKinds() as $tk) {
                        $hit[$tk] = 1;
                    }
                }
            }
            $skipGap = -1;
            // read-only snapshots: a rewrite copies the s->gapFlags column once per file, and only the rewritten gap's flags
            // change (re-read from $s below), so the stale snapshot is never consulted for it again
            $fcol = $s->gapFlags;
            $kcol = $s->kind;
            for ($i = 0; $i <= $n; $i++) {
                if ($i !== $skipGap) {
                    $fl = $fcol[$i];
                    $last = $i === $n;
                    $sel = 0;
                    if ($trig && !$last) {
                        $sel = $tn[$kcol[$i]];
                        if ($i > 0) {
                            $sel |= $tp[$kcol[$i - 1]];
                        }
                    }
                    if ($last || $sel !== 0 || ($fl & $any) !== 0) {
                        for ($k = 0; $k < $cnt; $k++) {
                            if ($last || ($fl & $masks[$k]) !== 0 || (($sel >> $k) & 1) !== 0) {
                                $gap = $s->gapBefore[$i];
                                $new = $group[$k]->fixGap($gap, $s, $i);
                                if ($new !== $gap) {
                                    $s->setGap($i, $new);
                                    $fl = $s->gapFlags[$i];
                                }
                            }
                        }
                    }
                }
                if ($i < $n) {
                    $kd = $kcol[$i];
                    if ($kd === \__McTokId::T_OPEN_TAG) {
                        for ($k = 0; $k < $cnt; $k++) {
                            $gap = $s->gapBefore[$i + 1];
                            $new = $group[$k]->fixGap($gap, $s, $i + 1);
                            if ($new !== $gap) {
                                $s->setGap($i + 1, $new);
                            }
                            if ($hasText[$k]) {
                                $t = $group[$k]->fixText($s, $i);
                                if ($t !== null) {
                                    $s->setText($i, $t);
                                }
                            }
                        }
                        $skipGap = $i + 1;
                    } elseif ($anyText && $hit[$kd] !== 0) {
                        for ($k = 0; $k < $cnt; $k++) {
                            if ($hasText[$k]) {
                                $t = $group[$k]->fixText($s, $i);
                                if ($t !== null) {
                                    $s->setText($i, $t);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $s;
    }
}
