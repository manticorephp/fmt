<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\ControlStructureContinuationPositionFixer, position=next_line (priority 0). `else` / `elseif` / `catch` / `finally` / `do {} while`
 * behind a `}` (no comment between) get a line break and the indentation of their line (WhitespacesAnalyzer::detectIndent, read BEFORE the later keywords were rewritten:
 * the walk is last to first). Whole-file FlatTokens port, trivia only.
 */
final class ControlStructureContinuationNextLine extends FlatRule
{
    public function __construct(private string $lineEnding)
    {
        parent::__construct();
        $this->gapsOnly = true;
    }

    public function name(): string
    {
        return 'control_structure_continuation_position';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if (($k === \__McTokId::T_ELSE || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_FINALLY || $k === \__McTokId::T_WHILE) && $s->kind[$i - 1] === 125) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = $t->count() - 1; $index > 0; --$index) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k !== \__McTokId::T_ELSE && $k !== \__McTokId::T_ELSEIF && $k !== \__McTokId::T_CATCH && $k !== \__McTokId::T_FINALLY && $k !== \__McTokId::T_WHILE) {
                continue;
            }
            $prev = $t->nonWhitespace($index, -1);
            if ($prev < 0 || !self::is($t, $prev, 125)) {
                continue;
            }
            if ($k === \__McTokId::T_WHILE) {
                $start = $t->blockStart($prev, 123);
                $before = $start < 0 ? -1 : $t->meaningful($start, -1);
                if ($before < 0 || !self::is($t, $before, \__McTokId::T_DO)) {
                    continue;
                }
            }
            self::ensureWs($t, $index - 1, 1, $this->lineEnding . self::detectIndent($t, $index));
        }
    }
}
