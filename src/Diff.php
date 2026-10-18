<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Unified diff (3 lines of context). Trims the common prefix/suffix, then an LCS table over the middle; a middle larger
 * than 4M cells is emitted as one replace hunk. Format is cs-fixer-like, not sebastian/diff byte-identical.
 */
final class Diff
{
    public static function unified(string $old, string $new, string $path): string
    {
        $a = \explode("\n", $old);
        $b = \explode("\n", $new);
        $na = \count($a);
        $nb = \count($b);
        $pre = 0;
        while ($pre < $na && $pre < $nb && $a[$pre] === $b[$pre]) {
            $pre++;
        }
        $suf = 0;
        while ($suf < $na - $pre && $suf < $nb - $pre && $a[$na - 1 - $suf] === $b[$nb - 1 - $suf]) {
            $suf++;
        }
        $ma = $na - $pre - $suf;
        $mb = $nb - $pre - $suf;
        // ops: ' ' keep, '-' delete, '+' add, over the whole file as parallel lists
        /** @var string[] $op */
        $op = [];
        /** @var string[] $ln */
        $ln = [];
        for ($i = 0; $i < $pre; $i++) {
            $op[] = ' ';
            $ln[] = $a[$i];
        }
        if ($ma * $mb > 4000000) {
            for ($i = 0; $i < $ma; $i++) {
                $op[] = '-';
                $ln[] = $a[$pre + $i];
            }
            for ($j = 0; $j < $mb; $j++) {
                $op[] = '+';
                $ln[] = $b[$pre + $j];
            }
        } else {
            $w = $mb + 1;
            /** @var int[] $t */
            $t = [];
            for ($k = 0; $k < ($ma + 1) * $w; $k++) {
                $t[] = 0;
            }
            for ($i = $ma - 1; $i >= 0; $i--) {
                for ($j = $mb - 1; $j >= 0; $j--) {
                    if ($a[$pre + $i] === $b[$pre + $j]) {
                        $t[$i * $w + $j] = $t[($i + 1) * $w + $j + 1] + 1;
                    } else {
                        $x = $t[($i + 1) * $w + $j];
                        $y = $t[$i * $w + $j + 1];
                        $t[$i * $w + $j] = $x >= $y ? $x : $y;
                    }
                }
            }
            $i = 0;
            $j = 0;
            while ($i < $ma && $j < $mb) {
                if ($a[$pre + $i] === $b[$pre + $j]) {
                    $op[] = ' ';
                    $ln[] = $a[$pre + $i];
                    $i++;
                    $j++;
                } elseif ($t[($i + 1) * $w + $j] >= $t[$i * $w + $j + 1]) {
                    $op[] = '-';
                    $ln[] = $a[$pre + $i];
                    $i++;
                } else {
                    $op[] = '+';
                    $ln[] = $b[$pre + $j];
                    $j++;
                }
            }
            while ($i < $ma) {
                $op[] = '-';
                $ln[] = $a[$pre + $i];
                $i++;
            }
            while ($j < $mb) {
                $op[] = '+';
                $ln[] = $b[$pre + $j];
                $j++;
            }
        }
        for ($i = 0; $i < $suf; $i++) {
            $op[] = ' ';
            $ln[] = $a[$na - $suf + $i];
        }
        $total = \count($op);
        $out = "--- Original\n+++ New\n";
        $i = 0;
        while ($i < $total) {
            if ($op[$i] === ' ') {
                $i++;
                continue;
            }
            // hunk: from 3 lines before the first change, extended while changes are within 6 lines
            $from = $i - 3 < 0 ? 0 : $i - 3;
            $last = $i;
            $k = $i;
            while ($k < $total && $k <= $last + 6) {
                if ($op[$k] !== ' ') {
                    $last = $k;
                }
                $k++;
            }
            $to = $last + 3 >= $total ? $total - 1 : $last + 3;
            $ca = 0;
            $cb = 0;
            $sa = 1;
            $sb = 1;
            for ($q = 0; $q < $from; $q++) {
                if ($op[$q] !== '+') {
                    $sa++;
                }
                if ($op[$q] !== '-') {
                    $sb++;
                }
            }
            $body = '';
            for ($q = $from; $q <= $to; $q++) {
                if ($op[$q] !== '+') {
                    $ca++;
                }
                if ($op[$q] !== '-') {
                    $cb++;
                }
                $body .= $op[$q] . $ln[$q] . "\n";
            }
            $out .= "@@ -$sa,$ca +$sb,$cb @@\n" . $body;
            $i = $to + 1;
        }
        return $out;
    }
}
