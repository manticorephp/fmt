<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\ArrayWalk;
use Fmt\GapRule;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ArrayNotation\NoWhitespaceBeforeCommaInArrayFixer (priority 0). In an array literal the whitespace
 * token right before a comma goes (not behind a comment; behind a heredoc end only with after_heredoc). Calls, closures and `{}` blocks
 * inside are skipped as upstream does.
 */
final class NoWhitespaceBeforeCommaInArray extends GapRule
{
    /** @var int[][] */
    private array $arrays = [];

    public function __construct(private bool $afterHeredoc) {}

    public function name(): string
    {
        return 'no_whitespace_before_comma_in_array';
    }

    public function priority(): int
    {
        return 0;
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

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        $this->arrays = ArrayWalk::arrays($s);
        return $this->arrays !== [];
    }

    public function fixFile(Source $s): void
    {
        foreach ($this->arrays as [$start, $end]) {
            for ($j = $end - 1; $j > $start; $j--) {
                $k = ArrayWalk::skip($s, $j);
                if ($k < 0) {
                    continue; // a comma of an implements list
                }
                $j = $k;
                if ($s->kind[$j] !== 44 || $s->ct[$j] !== 0) {
                    continue;
                }
                $g = $s->gapBefore[$j];
                // only a gap of exactly one whitespace token qualifies: with a comment before it, upstream's "previous non-whitespace" is that comment
                if ($g === '' || Gap::hasComment($g)) {
                    continue;
                }
                if (!$this->afterHeredoc && $s->kind[$j - 1] === \__McTokId::T_END_HEREDOC) {
                    continue;
                }
                $s->setGap($j, '');
            }
        }
    }
}
