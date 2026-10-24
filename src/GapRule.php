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
    /** neighbour traffic limited to the previous token being an open tag; the Engine runs that gap rule by rule, so it never blocks fusion */
    public const F_NB_OPEN = 8;

    abstract public function name(): string;

    /** an alias / proxy fixer runs in ITS slot: upstream sorts the proxy by its own priority and name, not its successor's */
    public ?int $slotPriority = null;
    public string $slotName = '';

    final public function sortPriority(): int
    {
        return $this->slotPriority ?? $this->priority();
    }

    final public function sortName(): string
    {
        return $this->slotName !== '' ? $this->slotName : $this->name();
    }

    /** cs-fixer priority; higher runs first */
    abstract public function priority(): int;

    abstract public function reads(): int;

    abstract public function writes(): int;

    /**
     * Gap::G_* bits; when a gap's flags share none of them the rule is a no-op on it and the Engine does not call it.
     * Not applied to the trailing gap or to a gap right after an open tag (those always run).
     */
    abstract public function needsMask(): int;

    /**
     * @return int[] token kinds: the gap right AFTER a token of such a kind always runs fixGap, whatever needsMask says
     * (a rule that inserts a blank into an EMPTY gap has flags 0). fixGap must re-check its own condition: it is also
     * called for the trailing gap and the gap after an open tag.
     */
    public function afterKinds(): array
    {
        return [];
    }

    /** @return int[] same, for the gap right BEFORE a token of such a kind */
    public function beforeKinds(): array
    {
        return [];
    }

    /**
     * A whole-file rule: a decision walk over the structure (statement_indentation, braces_position, ...) that rewrites gaps
     * through Source::setGap itself. The Engine calls fixFile once per file instead of fixGap/fixText, in its own pass:
     * a file rule reads every gap and token, so it never fuses with another rule.
     */
    public function isFileRule(): bool
    {
        return false;
    }

    public function fixFile(Source $s): void {}

    /** An EditRule: fixFile may record token edits (Source::edit), the Engine rebuilds the Source after it. */
    public function isEditRule(): bool
    {
        return false;
    }

    /** The path of the file the next prepare() / fixFile() run is about (a rule that depends on it, psr_autoloading, keeps it); '' = no file */
    public function forFile(string $path): void {}

    /** A rule that works on the raw file before it is lexed (encoding: a BOM); the Cli applies it in priority order. */
    public function preParse(string $code): string
    {
        return $code;
    }

    /**
     * Once per file, before the rules run: false when the rule cannot change anything in this file (upstream's isCandidate);
     * the Engine then leaves it out. A rule may also precompute per-file state here.
     */
    public function prepare(Source $s): bool
    {
        return true;
    }

    /** New gap before token $i ($i === $s->n is the trailing trivia). */
    public function fixGap(string $gap, Source $s, int $i): string
    {
        return $gap;
    }

    /** Whether fixText can answer non-null for this file at all; lets the Engine skip the per-token pass. */
    public function usesText(Source $s): bool
    {
        return false;
    }

    /** @return int[] the only token kinds fixText may rewrite */
    public function textKinds(): array
    {
        return [];
    }

    /** New text of token $i ($i < n), or null to leave it. */
    public function fixText(Source $s, int $i): ?string
    {
        return null;
    }
}
