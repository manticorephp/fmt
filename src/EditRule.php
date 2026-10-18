<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Interface only (P3): a rule that adds/removes/replaces tokens. It emits span edits against the original
 * source; the Engine applies a batch of them in a rebuild phase (re-lex + Structure::build), then the following
 * gap rules continue on the new Source. Consecutive edit rules with disjoint footprints share one rebuild.
 */
interface EditRule
{
    public function name(): string;

    public function priority(): int;

    /** @return int[] token kinds the rule inspects (T_* ids; byte values for punctuation) */
    public function readsKinds(): array;

    /** @return int[] token kinds the rule may create, delete or change */
    public function writesKinds(): array;

    /** @return Edit[] non-overlapping, sorted by start */
    public function edits(Source $s, Structure $st): array;
}
