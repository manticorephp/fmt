<?php

declare(strict_types=1);

namespace Fmt;

/** What happened to one file; crosses the worker pipe as one JSON line (toWire / fromWire). */
final class Outcome
{
    public const OK = 0;
    public const CHANGED = 1;
    /** the file does not lint (a bad character): left alone, upstream's "invalid file" */
    public const INVALID = 2;
    /** a rule refused it or the result would not lint (upstream: exception in app) */
    public const FAILED = 3;
    /** the cache vouches for it */
    public const CACHED = 4;

    public string $msg = '';
    /** @var string[] */
    public array $applied = [];
    public string $diff = '';
    /** md5 of the content as it is on disk after this run ('' when unknown / not cacheable) */
    public string $hash = '';

    public function __construct(public string $path, public int $status)
    {
    }

    public function toWire(): string
    {
        return \json_encode([$this->path, $this->status, $this->msg, $this->applied, $this->diff, $this->hash], \JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    }

    public static function fromWire(string $line): ?self
    {
        $j = \json_decode($line, true);
        if (!\is_array($j) || \count($j) !== 6) {
            return null;
        }
        $o = new self((string) $j[0], (int) $j[1]);
        $o->msg = (string) $j[2];
        /** @var string[] $ap */
        $ap = \is_array($j[3]) ? $j[3] : [];
        $o->applied = $ap;
        $o->diff = (string) $j[4];
        $o->hash = (string) $j[5];
        return $o;
    }
}
