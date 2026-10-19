<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\GapRule;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\NamespaceNotation\CleanNamespaceFixer (priority 10). Upstream's isCandidate is `PHP_VERSION_ID < 8.0`: a no-op on PHP 8 (the oracle). */
final class CleanNamespace extends GapRule
{
    public function name(): string
    {
        return 'clean_namespace';
    }

    public function priority(): int
    {
        return 10;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function prepare(Source $s): bool
    {
        return false;
    }
}
