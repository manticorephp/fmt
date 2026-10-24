<?php

declare(strict_types=1);

namespace Fmt;

/** `--stats`: the aggregate of a run, as text (colored when on) or the `stats` object of the json report. */
final class Stats
{
    public int $total = 0;
    public int $changed = 0;
    public int $ok = 0;
    public int $cached = 0;
    /** discovered but not processed (--stop-on-violation) */
    public int $skipped = 0;
    /** does not lint, or could not be fixed */
    public int $errors = 0;
    public float $wall = 0.0;
    /** -1 when unknown */
    public float $cpu = -1.0;
    public float $peakMb = 0.0;
    /** largest peak resident size any pool worker reported (0 = no pool) */
    public float $workerPeakMb = 0.0;
    public int $bytes = 0;
    /** @var array<string, int> rule => files it changed, most first */
    public array $rules = [];
    /** @var array<string, float> file => seconds, slowest first */
    public array $slowest = [];

    /** @param Outcome[] $results */
    public static function collect(array $results, int $total, float $wall, float $cpu, float $peakMb): self
    {
        $s = new self();
        $s->total = $total;
        $s->wall = $wall;
        $s->cpu = $cpu;
        $s->peakMb = $peakMb;
        /** @var array<string, int> $rules */
        $rules = [];
        /** @var array<string, float> $times */
        $times = [];
        foreach ($results as $r) {
            $s->bytes += $r->bytes;
            $s->workerPeakMb = \max($s->workerPeakMb, $r->peak / 1048576);
            $times[$r->path] = $r->secs;
            if ($r->status === Outcome::CHANGED) {
                $s->changed++;
                foreach ($r->applied as $name) {
                    $rules[$name] = ($rules[$name] ?? 0) + 1;
                }
            } elseif ($r->status === Outcome::CACHED) {
                $s->cached++;
            } elseif ($r->status === Outcome::OK) {
                $s->ok++;
            } else {
                $s->errors++;
            }
        }
        $s->skipped = \max(0, $total - \count($results));
        $names = \array_keys($rules);
        \usort($names, static fn(string $a, string $b): int => $rules[$b] <=> $rules[$a] ?: \strcmp($a, $b));
        foreach (\array_slice($names, 0, 10) as $n) {
            $s->rules[$n] = $rules[$n];
        }
        $paths = \array_keys($times);
        \usort($paths, static fn(string $a, string $b): int => $times[$b] <=> $times[$a] ?: \strcmp($a, $b));
        foreach (\array_slice($paths, 0, 5) as $p) {
            $s->slowest[$p] = $times[$p];
        }
        return $s;
    }

    public function filesPerSec(): float
    {
        $n = $this->total - $this->skipped;
        return $this->wall > 0.0 ? $n / $this->wall : 0.0;
    }

    public function mbPerSec(): float
    {
        return $this->wall > 0.0 ? $this->bytes / 1048576 / $this->wall : 0.0;
    }

    /** @return array<string, mixed> the `stats` object of --format=json */
    public function toArray(): array
    {
        $rules = [];
        foreach ($this->rules as $n => $c) {
            $rules[] = ['rule' => $n, 'files' => $c];
        }
        $slow = [];
        foreach ($this->slowest as $p => $t) {
            $slow[] = ['file' => $p, 'seconds' => \round($t, 4)];
        }
        return [
            'files' => ['total' => $this->total, 'changed' => $this->changed, 'ok' => $this->ok, 'cached' => $this->cached, 'skipped' => $this->skipped, 'errors' => $this->errors],
            'time' => ['wall' => \round($this->wall, 3), 'cpu' => $this->cpu < 0.0 ? null : \round($this->cpu, 3)],
            'peakMemoryMb' => \round($this->peakMb, 2),
            'workerPeakMemoryMb' => $this->workerPeakMb > 0.0 ? \round($this->workerPeakMb, 2) : null,
            'bytes' => $this->bytes,
            'throughput' => ['filesPerSec' => \round($this->filesPerSec(), 1), 'mbPerSec' => \round($this->mbPerSec(), 2)],
            'rules' => $rules,
            'slowest' => $slow,
        ];
    }

    public function text(bool $color): string
    {
        $h = static fn(string $t): string => Ansi::paint($color, Ansi::BOLD, $t);
        $out = "\n" . $h('Statistics') . "\n";
        $out .= \sprintf(
            "  files       %d total, %s, %s, %s, %d skipped, %s\n",
            $this->total,
            Ansi::paint($color, Ansi::YELLOW, $this->changed . ' changed'),
            Ansi::paint($color, Ansi::GREEN, $this->ok . ' ok'),
            Ansi::paint($color, Ansi::DIM, $this->cached . ' cached'),
            $this->skipped,
            Ansi::paint($color && $this->errors > 0, Ansi::RED, $this->errors . ' errors'),
        );
        $out .= \sprintf("  time        %.3f s wall%s\n", $this->wall, $this->cpu < 0.0 ? '' : \sprintf(', %.3f s cpu', $this->cpu));
        $out .= \sprintf("  memory      %.2f MB peak (main process)%s\n", $this->peakMb, $this->workerPeakMb > 0.0 ? \sprintf(', %.2f MB peak (largest worker)', $this->workerPeakMb) : '');
        $out .= \sprintf("  throughput  %.1f files/s, %.2f MB/s (%.2f MB read)\n", $this->filesPerSec(), $this->mbPerSec(), $this->bytes / 1048576);
        if ($this->rules !== []) {
            $out .= $h('Top rules by files changed') . "\n";
            foreach ($this->rules as $n => $c) {
                $out .= \sprintf("  %5d  %s\n", $c, $n);
            }
        }
        if ($this->slowest !== []) {
            $out .= $h('Slowest files') . "\n";
            foreach ($this->slowest as $p => $t) {
                $out .= \sprintf("  %8.4f s  %s\n", $t, $p);
            }
        }
        return $out;
    }
}
