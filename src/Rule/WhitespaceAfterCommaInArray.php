<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\ArrayWalk;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ArrayNotation\WhitespaceAfterCommaInArrayFixer (priority 0). In an array literal a comma not followed by a
 * whitespace token gets a space (even right before `]`, or before a comment); with ensure_single_space, blanks behind a comma become one
 * space unless a comment follows that is not itself followed by blanks.
 */
final class WhitespaceAfterCommaInArray extends GapRule
{
    /** @var int[][] */
    private array $arrays = [];

    public function __construct(private bool $single)
    {
    }

    public function name(): string
    {
        return 'whitespace_after_comma_in_array';
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
                    continue;
                }
                $j = $k;
                if ($s->kind[$j] !== 44 || $s->ct[$j] !== 0) {
                    continue;
                }
                $g = $s->gapBefore[$j + 1];
                if ($g === '') {
                    $s->setGap($j + 1, ' ');
                    continue;
                }
                if ($g === ' ') {
                    continue;
                }
                $p = Trivia::pieces($g);
                if (Trivia::isComment($p[0])) {
                    $s->setGap($j + 1, ' ' . $g);
                    continue;
                }
                if (!$this->single || !Trivia::hspace($p[0])) {
                    continue;
                }
                $m = \count($p);
                if ($m >= 2 && Trivia::isComment($p[1]) && !($m >= 3 && Trivia::hspace($p[2]))) {
                    continue;
                }
                $p[0] = ' ';
                $s->setGap($j + 1, \implode('', $p));
            }
        }
    }
}
