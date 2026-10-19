<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Structure;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\NoBlankLinesAfterClassOpeningFixer (priority 0). The whitespace token behind a class
 * / interface / trait / enum body `{` that holds more than one "\n" becomes one line break plus the indent after its last "\n".
 */
final class NoBlankLinesAfterClassOpening extends GapRule
{
    public function __construct(private string $le = "\n")
    {
    }

    public function name(): string
    {
        return 'no_blank_lines_after_class_opening';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return Gap::G_NL2;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($i === 0 || $i >= $s->n || $s->kind[$i - 1] !== 123 || $s->ct[$i - 1] !== 0) {
            return $gap;
        }
        $lead = Gap::lead($gap);
        if (\substr_count($lead, "\n") < 2 || Structure::braceOwner($s, $i - 1) !== Structure::OW_CLASS_BODY) {
            return $gap;
        }
        return Gap::withLead($gap, $this->le . \substr($lead, (int) \strrpos($lead, "\n") + 1));
    }
}
