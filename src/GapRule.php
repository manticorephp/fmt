<?php

declare(strict_types=1);

namespace Fmt;

/**
 * A gap rule: a function over (gap, context). It returns the new gap / token text; it never re-lexes.
 * Context = the Source (immutable kinds/offsets, current gaps and texts) and the index.
 *
 * Footprint (reads()/writes()) is a bitmask of the F_* flags. "Own" = the gap before token i and token i's text.
 * The *_NB flags mean "touches a neighbouring token's gap or text" (e.g. reads the previous open tag's text, or
 * rewrites it via Source::setText). Two rules fuse into one per-token pipeline only when neither is order
 * sensitive against the other's neighbour traffic; see Engine::conflicts. Kinds and offsets are immutable
 * and free to read at any index.
 */
abstract class GapRule
{
    public const F_GAP = 1;
    public const F_TEXT = 2;
    public const F_NB = 4;

    abstract public function name(): string;

    /** cs-fixer priority; higher runs first */
    abstract public function priority(): int;

    abstract public function reads(): int;

    abstract public function writes(): int;

    /**
     * Gap::G_* bits; when a gap's flags share none of them the rule is a no-op on it and the Engine does not call it.
     * Not applied to the trailing gap or to a gap right after an open tag (those always run).
     */
    abstract public function needsMask(): int;

    /** New gap before token $i ($i === $s->n is the trailing trivia). */
    public function fixGap(string $gap, Source $s, int $i): string
    {
        return $gap;
    }

    /** Whether fixText can ever answer non-null; lets the Engine skip the per-token pass. */
    public function usesText(): bool
    {
        return false;
    }

    /** New text of token $i ($i < n), or null to leave it. */
    public function fixText(Source $s, int $i): ?string
    {
        return null;
    }
}
