<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Fork pool: the files are dealt to N children by size (largest first onto the least loaded child), each child formats its
 * share and appends one JSON line per file to its own result file; the parent waits for all of them and returns the outcomes
 * in the order of the input, so the report is the same as a sequential run. A child that dies leaves its unfinished files
 * FAILED ("worker crashed"). Forking happens once, before any file is read.
 */
final class Pool
{
    public static function cores(): int
    {
        $n = (int) \trim((string) @\shell_exec('sysctl -n hw.ncpu 2>/dev/null || nproc 2>/dev/null'));
        return $n > 0 ? $n : 1;
    }

    /**
     * @param string[] $files
     * @return Outcome[] aligned with $files
     */
    public static function run(array $files, int $workers, Processor $proc): array
    {
        $n = \count($files);
        if ($workers > $n) {
            $workers = $n;
        }
        if ($n < 12) {
            $workers = 1; // a fork costs more than a handful of files
        }
        $dir = \sys_get_temp_dir() . '/mfmt-' . \getmypid();
        if ($workers < 2 || !@\mkdir($dir, 0700, true)) {
            return self::sequential($files, $proc);
        }
        // deal by size: order indexes by size descending, give each to the least loaded child
        /** @var int[] $size */
        $size = [];
        foreach ($files as $i => $f) {
            $size[$i] = (int) @\filesize($f);
        }
        $order = \array_keys($size);
        \usort($order, static fn (int $a, int $b): int => $size[$b] <=> $size[$a] ?: $a <=> $b);
        /** @var int[][] $share */
        $share = [];
        /** @var int[] $load */
        $load = [];
        for ($w = 0; $w < $workers; $w++) {
            $share[$w] = [];
            $load[$w] = 0;
        }
        foreach ($order as $i) {
            $best = 0;
            for ($w = 1; $w < $workers; $w++) {
                if ($load[$w] < $load[$best]) {
                    $best = $w;
                }
            }
            $share[$best][] = $i;
            $load[$best] += $size[$i] + 1024;
        }
        \fflush(\STDOUT);
        \fflush(\STDERR);
        /** @var int[] $pids */
        $pids = [];
        for ($w = 0; $w < $workers; $w++) {
            \sort($share[$w]);
            $pid = \pcntl_fork();
            if ($pid === 0) {
                $out = @\fopen($dir . '/' . $w, 'w');
                if ($out !== false) {
                    foreach ($share[$w] as $i) {
                        \fwrite($out, $proc->file($files[$i])->toWire());
                    }
                    \fclose($out);
                }
                \posix_kill(\posix_getpid(), \SIGKILL); // never unwind the parent's stack in a child
            }
            if ($pid > 0) {
                $pids[$w] = $pid;
            }
        }
        foreach ($pids as $pid) {
            $st = 0;
            \pcntl_waitpid($pid, $st);
        }
        /** @var array<string, Outcome> $byPath */
        $byPath = [];
        for ($w = 0; $w < $workers; $w++) {
            $raw = @\file_get_contents($dir . '/' . $w);
            if ($raw !== false) {
                foreach (\explode("\n", $raw) as $line) {
                    if ($line !== '') {
                        $o = Outcome::fromWire($line);
                        if ($o !== null) {
                            $byPath[$o->path] = $o;
                        }
                    }
                }
            }
            @\unlink($dir . '/' . $w);
        }
        @\rmdir($dir);
        $res = [];
        foreach ($files as $f) {
            $o = $byPath[$f] ?? null;
            if ($o === null) {
                $o = new Outcome($f, Outcome::FAILED);
                $o->msg = 'worker crashed';
            }
            $res[] = $o;
        }
        return $res;
    }

    /**
     * @param string[] $files
     * @return Outcome[]
     */
    public static function sequential(array $files, Processor $proc): array
    {
        $res = [];
        foreach ($files as $f) {
            $res[] = $proc->file($f);
        }
        return $res;
    }
}
