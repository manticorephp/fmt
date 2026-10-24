<?php

declare(strict_types=1);

namespace Fmt;

/** One attribute of a `#[A, B(1)]` group as AttributeAnalyzer::collectAttributes reports it: item range [start, end] (flat indexes) and the name as written. */
final class FlatAttributeElement
{
    public function __construct(public int $start, public int $end, public string $name) {}
}
