<?php

declare(strict_types=1);

namespace Fmt;

/** The live one-line progress on a TTY stderr: counters fed per outcome as results arrive, one in-place line redrawn (throttled). */
final class Progress
{
    private int $done = 0;
    private int $changed = 0;
    private int $ok = 0;
    private int $cached = 0;
    private int $errors = 0;
    private float $last = 0.0;

    public function __construct(private int $total, private bool $color, private float $t0)
    {
    }

    public function start(): void
    {
        \fwrite(\STDERR, "\033[?25l");
        $this->draw();
    }

    public function add(Outcome $o): void
    {
        $this->done++;
        if ($o->status === Outcome::CHANGED) {
            $this->changed++;
        } elseif ($o->status === Outcome::CACHED) {
            $this->cached++;
        } elseif ($o->status === Outcome::OK) {
            $this->ok++;
        } else {
            $this->errors++;
        }
        $now = \microtime(true);
        if ($this->done >= $this->total || $now - $this->last >= 0.05) {
            $this->draw();
        }
    }

    /** erase the line and bring the cursor back; safe to call twice */
    public function finish(): void
    {
        \fwrite(\STDERR, "\r\033[K\033[?25h");
    }

    private function draw(): void
    {
        $this->last = \microtime(true);
        \fwrite(\STDERR, "\r" . self::render($this->done, $this->total, $this->last - $this->t0, $this->changed, $this->ok, $this->cached, $this->errors, $this->color) . "\033[K");
    }

    public static function render(int $done, int $total, float $elapsed, int $changed, int $ok, int $cached, int $errors, bool $color): string
    {
        $w = 20;
        $fill = $total > 0 ? \intdiv($done * $w, $total) : $w;
        $pct = $total > 0 ? \intdiv($done * 100, $total) : 100;
        $rate = $elapsed > 0.0 ? $done / $elapsed : 0.0;
        $eta = $done > 0 && $rate > 0.0 ? self::duration(($total - $done) / $rate) : '--';
        return '[' . Ansi::paint($color, Ansi::GREEN, \str_repeat('#', $fill)) . Ansi::paint($color, Ansi::DIM, \str_repeat('-', $w - $fill)) . '] '
            . $done . '/' . $total . \sprintf(' %3d%% %.1f files/s ETA %s  ', $pct, $rate, $eta)
            . Ansi::paint($color, Ansi::GREEN, 'ok ' . $ok) . '  '
            . Ansi::paint($color, Ansi::YELLOW, 'changed ' . $changed) . '  '
            . Ansi::paint($color, Ansi::DIM, 'cached ' . $cached) . '  '
            . Ansi::paint($color, $errors > 0 ? Ansi::RED : Ansi::DIM, 'errors ' . $errors);
    }

    /** 7s, 1m05s, 2h03m */
    public static function duration(float $seconds): string
    {
        $s = (int) \ceil($seconds);
        if ($s < 60) {
            return $s . 's';
        }
        if ($s < 3600) {
            return \intdiv($s, 60) . 'm' . \sprintf('%02d', $s % 60) . 's';
        }
        return \intdiv($s, 3600) . 'h' . \sprintf('%02d', \intdiv($s % 3600, 60)) . 'm';
    }
}
