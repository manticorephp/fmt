<?php

declare(strict_types=1);

namespace Fmt;

/** AttributeAnalysis over a FlatTokens: the item range of a `#[..]` group (with the whitespace behind it), its `#[` and `]` items and its attributes. */
final class FlatAttributeGroup
{
    /** @param FlatAttributeElement[] $attributes */
    public function __construct(public int $start, public int $end, public int $open, public int $close, public array $attributes) {}
}
