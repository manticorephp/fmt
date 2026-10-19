<?php

declare(strict_types=1);

namespace Fmt;

/**
 * A rule that inserts, deletes, replaces or moves TOKENS (docs/design.md, "Edit phase"). A file rule: fixFile reads the live Source
 * and records span edits with Source::edit; when it recorded any, the Engine renders the file and re-parses it before the next rule.
 */
abstract class EditRule extends GapRule
{
    public function isFileRule(): bool
    {
        return true;
    }

    public function isEditRule(): bool
    {
        return true;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_TEXT | self::F_NB;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_TEXT | self::F_NB;
    }
}
