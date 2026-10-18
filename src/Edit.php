<?php

declare(strict_types=1);

namespace Fmt;

/** Replace source bytes [start, end) with `text`. Insertion: start === end. Deletion: text === ''. */
final class Edit
{
    public function __construct(
        public int $start,
        public int $end,
        public string $text,
    ) {
    }
}
