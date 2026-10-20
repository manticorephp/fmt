<?php

declare(strict_types=1);

namespace Fmt;

/** Replace stream units [from, to) with `text` (unit 2i = gap i, unit 2i + 1 = token i, see Source::edit). */
final class Edit
{
    public function __construct(
        public int $from,
        public int $to,
        public string $text,
        /** byte offset in text where upstream has two ADJACENT whitespace tokens (see Source::$wsSplit), -1 = none */
        public int $split = -1,
        /** @var int[] more such offsets (a flat rule that left several) */
        public array $splits = [],
    ) {
    }
}
