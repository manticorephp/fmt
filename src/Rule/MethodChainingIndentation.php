<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer (priority 0). The whitespace before each `->` / `?->` that starts a line is re-indented to the
 * chain's expected indent (one level deeper than the line the chain starts on; a chain operator behind a comment moves to its own line), and the line breaks
 * inside that call's parentheses are shifted by the same difference. Whole-file FlatTokens port (loop bound = the token count at the start, as upstream's).
 */
final class MethodChainingIndentation extends FlatRule
{
    public function __construct(private string $indent, private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'method_chaining_indentation';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
                return true;
            }
        }
        return false;
    }

    private static function objectOp(FlatTokens $t, int $q): bool
    {
        return $t->i[$q] !== -1 && ($t->k[$q] === \__McTokId::T_OBJECT_OPERATOR || $t->k[$q] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $count = $t->count();
        for ($index = 1; $index < $count; ++$index) {
            if (!self::objectOp($t, $index)) {
                continue;
            }
            $end = -1;
            for ($j = $index + 1; $j < $t->count(); $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 40 || $t->k[$j] === 59 || $t->k[$j] === 44 || $t->k[$j] === \__McTokId::T_CLOSE_TAG)) {
                    $end = $j;
                    break;
                }
            }
            $prevEnd = -1;
            for ($j = $index - 1; $j >= 0; $j--) {
                if ($t->i[$j] !== -1 && $t->k[$j] === 41) {
                    $prevEnd = $j;
                    break;
                }
            }
            if ($end < 0 || ($t->k[$end] !== 40 && $prevEnd < 0)) {
                continue;
            }
            if ($this->canBeMovedToNextLine($t, $index)) {
                if ($t->isWs($index - 1)) {
                    $t->c[$index - 1] = $this->lineEnding;
                } else {
                    $t->insertAt($index, [FlatTokens::ws($this->lineEnding)]);
                    ++$index;
                    ++$end;
                }
            }
            $currentIndent = $this->indentAt($t, $index - 1);
            if ($currentIndent === null) {
                continue;
            }
            $expected = $this->expectedIndentAt($t, $index);
            if ($currentIndent !== $expected) {
                $q = $index - 1;
                $t->k[$q] = FlatTokens::WS;
                $t->c[$q] = $this->lineEnding . $expected;
                $t->i[$q] = -1;
            }
            if ($t->k[$end] !== 40) {
                continue;
            }
            $end = $t->blockEnd($end, 41);
            if ($end < 0) {
                return;
            }
            for ($search = $index + 1; $search < $end; ++$search) {
                if (!$t->isWs($search)) {
                    continue;
                }
                $content = $t->c[$search];
                if (!Preg::test('/\R/', $content)) {
                    continue;
                }
                $t->c[$search] = Preg::replace('/(\R)' . $currentIndent . '(\h*)$/D', '$1' . $expected . '$2', $content);
            }
        }
    }

    private function expectedIndentAt(FlatTokens $t, int $index): string
    {
        $index = $t->meaningful($index, -1);
        for ($i = $index; $i >= 0; --$i) {
            if ($t->i[$i] !== -1 && $t->k[$i] === 41) {
                $i = $t->blockStart($i, 40);
                if ($i < 0) {
                    break;
                }
            }
            $current = $this->indentAt($t, $i);
            if ($current === null) {
                continue;
            }
            if ($this->requiresExtraIndent($t, $i, $index)) {
                return $current . $this->indent;
            }
            return $current;
        }
        return $this->indent;
    }

    private function canBeMovedToNextLine(FlatTokens $t, int $index): bool
    {
        $prevMeaningful = $t->meaningful($index, -1);
        $hasComment = false;
        for ($i = $index - 1; $i > $prevMeaningful; --$i) {
            if ($t->isComment($i)) {
                $hasComment = true;
                continue;
            }
            if ($t->isWs($i) && Preg::test('/\R/', $t->c[$i])) {
                return $hasComment;
            }
        }
        return false;
    }

    private function indentAt(FlatTokens $t, int $index): ?string
    {
        if ($index < 0) {
            return null;
        }
        if (Preg::match('/\R{1}(\h*)$/', $this->indentContentAt($t, $index), $m)) {
            return (string) $m[1];
        }
        return null;
    }

    private function indentContentAt(FlatTokens $t, int $index): string
    {
        $ws = $t->k[$index] === FlatTokens::WS && $t->c[$index] !== '';
        $html = $t->i[$index] !== -1 && $t->k[$index] === \__McTokId::T_INLINE_HTML;
        if (!$ws && !$html) {
            return '';
        }
        $content = $t->content($index);
        if ($ws && $index > 0 && $t->i[$index - 1] !== -1 && $t->k[$index - 1] === \__McTokId::T_OPEN_TAG) {
            $content = $t->content($index - 1) . $content;
        }
        return Preg::test('/\R/', $content) ? $content : '';
    }

    private function requiresExtraIndent(FlatTokens $t, int $start, int $end): bool
    {
        $first = $t->meaningful($start, 1);
        if ($first >= 0 && self::objectOp($t, $first)) {
            $second = $t->meaningful($first, 1);
            $third = $second < 0 ? -1 : $t->meaningful($second, 1);
            return $third >= 0 && self::is($t, $third, 40) && $t->blockEnd($third, 41) > $end;
        }
        return !self::is($t, $end, 41) || $t->blockStart($end, 40) >= $start;
    }
}
