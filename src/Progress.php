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

    public function __construct(private int $total, private bool $color, private float $t0, private int $width = 0) {}

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
        \fwrite(\STDERR, "\r" . self::render($this->done, $this->total, $this->last - $this->t0, $this->changed, $this->ok, $this->cached, $this->errors, $this->color, $this->width) . "\033[K");
    }

    /** Terminal columns: $COLUMNS, else the columns of `stty size` ("rows cols"), else 80. */
    public static function width(?string $columns, ?string $sttySize): int
    {
        if ($columns !== null && $columns !== '' && \strspn($columns, '0123456789') === \strlen($columns) && (int) $columns > 0) {
            return (int) $columns;
        }
        $p = \explode(' ', \trim((string) $sttySize));
        if (\count($p) === 2 && $p[1] !== '' && \strspn($p[1], '0123456789') === \strlen($p[1]) && (int) $p[1] > 0) {
            return (int) $p[1];
        }
        return 80;
    }

    /**
     * The line; with $width > 0 it never reaches the last column (no wrap): the full line, then shorter ones (narrower bar, short labels,
     * no counters, no bar), at last the plain text cut off.
     */
    public static function render(int $done, int $total, float $elapsed, int $changed, int $ok, int $cached, int $errors, bool $color, int $width = 0): string
    {
        $max = $width - 1;
        $last = '';
        for ($tier = 0; $tier <= 4; $tier++) {
            $line = self::tier($tier, $done, $total, $elapsed, $changed, $ok, $cached, $errors, $color);
            if ($width <= 0 || \strlen(self::tier($tier, $done, $total, $elapsed, $changed, $ok, $cached, $errors, false)) <= $max) {
                return $line;
            }
            $last = $tier === 4 ? self::tier(4, $done, $total, $elapsed, $changed, $ok, $cached, $errors, false) : '';
        }
        return \substr($last, 0, \max(0, $max));
    }

    private static function tier(int $tier, int $done, int $total, float $elapsed, int $changed, int $ok, int $cached, int $errors, bool $color): string
    {
        $w = $tier === 0 ? 20 : 10;
        $fill = $total > 0 ? \intdiv($done * $w, $total) : $w;
        $pct = $total > 0 ? \intdiv($done * 100, $total) : 100;
        $rate = $elapsed > 0.0 ? $done / $elapsed : 0.0;
        $eta = $done > 0 && $rate > 0.0 ? self::duration(($total - $done) / $rate) : '--';
        $bar = '[' . Ansi::paint($color, Ansi::GREEN, \str_repeat('#', $fill)) . Ansi::paint($color, Ansi::DIM, \str_repeat('-', $w - $fill)) . '] ';
        $count = $done . '/' . $total;
        $e = Ansi::paint($color, $errors > 0 ? Ansi::RED : Ansi::DIM, ($tier >= 2 ? 'err ' : 'errors ') . $errors);
        if ($tier <= 1) {
            return $bar . $count . \sprintf(' %3d%% %.1f files/s ETA %s  ', $pct, $rate, $eta)
                . Ansi::paint($color, Ansi::GREEN, 'ok ' . $ok) . '  '
                . Ansi::paint($color, Ansi::YELLOW, 'changed ' . $changed) . '  '
                . Ansi::paint($color, Ansi::DIM, 'cached ' . $cached) . '  ' . $e;
        }
        if ($tier === 2) {
            return $bar . $count . \sprintf(' %d%% %.0f/s ', $pct, $rate) . Ansi::paint($color, Ansi::GREEN, 'ok ' . $ok) . ' '
                . Ansi::paint($color, Ansi::YELLOW, 'chg ' . $changed) . ' ' . $e;
        }
        if ($tier === 3) {
            return $bar . $count . \sprintf(' %d%%', $pct);
        }
        return $count . \sprintf(' %d%%', $pct);
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
