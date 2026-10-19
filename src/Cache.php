<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Content-hash cache: path => md5 of the content the file had when it was last known to be clean under this signature
 * (the rules with their options, indent, line ending and the binary). A file whose current content hashes the same is skipped.
 * A different signature discards the whole file.
 */
final class Cache
{
    /** @var array<string, string> */
    private array $files = [];
    private bool $dirty = false;

    public function __construct(private string $path, private string $sig)
    {
        $raw = @\file_get_contents($path);
        if ($raw === false) {
            return;
        }
        $j = \json_decode($raw, true);
        if (\is_array($j) && ($j['sig'] ?? '') === $sig && \is_array($j['files'] ?? null)) {
            foreach ($j['files'] as $k => $v) {
                $this->files[(string) $k] = (string) $v;
            }
        }
    }

    public function isClean(string $file, string $hash): bool
    {
        return ($this->files[$file] ?? '') === $hash;
    }

    public function count(): int
    {
        return \count($this->files);
    }

    /** Record the outcome of a file: clean files are remembered, anything else is forgotten. */
    public function note(Outcome $o): void
    {
        if ($o->status === Outcome::CACHED) {
            return;
        }
        if ($o->hash !== '' && ($o->status === Outcome::OK || $o->status === Outcome::CHANGED)) {
            if (($this->files[$o->path] ?? '') !== $o->hash) {
                $this->files[$o->path] = $o->hash;
                $this->dirty = true;
            }
        } elseif (isset($this->files[$o->path])) {
            unset($this->files[$o->path]);
            $this->dirty = true;
        }
    }

    public function save(): void
    {
        if (!$this->dirty) {
            return;
        }
        \ksort($this->files);
        $tmp = $this->path . '.' . \getmypid() . '.tmp';
        if (@\file_put_contents($tmp, \json_encode(['sig' => $this->sig, 'files' => $this->files], \JSON_UNESCAPED_SLASHES)) !== false) {
            @\rename($tmp, $this->path);
        }
    }

    /** The binary's identity: a new build of the tool must not trust an old cache. */
    public static function stamp(string $argv0): string
    {
        $p = $argv0;
        if (\strpos($p, '/') === false) {
            foreach (\explode(':', (string) \getenv('PATH')) as $d) {
                if ($d !== '' && \is_file($d . '/' . $p)) {
                    $p = $d . '/' . $p;
                    break;
                }
            }
        }
        $rp = \realpath($p);
        if ($rp === false) {
            return Cli::ABOUT;
        }
        return Cli::ABOUT . '|' . \filesize($rp) . '|' . \filemtime($rp);
    }
}
