<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatBlocks;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Semicolon\MultilineWhitespaceBeforeSemicolonsFixer (priority 0). strategy=no_multi_line: the line break before a `;` goes (a comment
 * before it keeps the `;` behind the last significant token). strategy=new_line_for_chained_calls: the `;` of a multi-line call chain moves to a line of its own at the
 * indent of the chain's first line. Whole-file FlatTokens port (the loop bound is the token count at the start, as upstream's).
 */
final class MultilineWhitespaceBeforeSemicolons extends FlatRule
{
    private FlatBlocks $blocks;

    public function __construct(private bool $chained, private string $lineEnding)
    {
        parent::__construct();
        $this->blocks = new FlatBlocks();
    }

    public function name(): string
    {
        return 'multiline_whitespace_before_semicolons';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === 59 && $s->ct[$i] === 0) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $le = $this->lineEnding;
        $count = $t->count();
        for ($index = 0; $index < $count; ++$index) {
            if (self::is($t, $index, \__McTokId::T_CONST)) {
                $index = self::nextOf($t, $index, 59);
                if ($index < 0) {
                    return;
                }
                continue;
            }
            if (!self::is($t, $index, 59)) {
                continue;
            }
            $previousIndex = $index - 1;
            $indent = $this->firstCallIndent($t, $index);
            if ($this->chained && $indent !== null) {
                if ($t->isWs($previousIndex) && $t->c[$previousIndex] === $le . $indent) {
                    continue;
                }
                if ($t->isWs($previousIndex)) {
                    $t->clearAt($previousIndex);
                }
                $t->clearAt($index);
                $index = $this->newLineIndex($t, $index);
                $t->insertAt($index++, [FlatTokens::ws($le . $indent), FlatTokens::code(59, ';')]);
            } else {
                if (!$t->isWs($previousIndex) || !\str_contains($t->c[$previousIndex], "\n")) {
                    continue;
                }
                $content = $t->c[$previousIndex];
                if (\str_starts_with($content, $le) && $t->isComment($index - 2)) {
                    $t->clearAt($previousIndex);
                    $t->clearAt($index);
                    $sig = $this->previousSignificant($t, $index);
                    $t->insertAt($sig + 1, [FlatTokens::code(59, ';')]);
                } else {
                    $t->clearAt($previousIndex);
                }
            }
        }
    }

    private function newLineIndex(FlatTokens $t, int $index): int
    {
        $m = $t->count();
        for (; $index < $m; ++$index) {
            if (!$t->isWs($index) && !$t->isComment($index)) {
                break;
            }
            if (\str_contains($t->c[$index], $this->lineEnding)) {
                return $index;
            }
        }
        return $index;
    }

    private function previousSignificant(FlatTokens $t, int $index): int
    {
        for (; $index > 0; --$index) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_LNUMBER || $k === \__McTokId::T_DNUMBER || $k === \__McTokId::T_STRING || $k === \__McTokId::T_VARIABLE
                || $k === \__McTokId::T_CONSTANT_ENCAPSED_STRING || $k === 41) {
                return $index;
            }
        }
        return $index;
    }

    private function firstCallIndent(FlatTokens $t, int $index): ?string
    {
        $multiline = false;
        $prev = $t->meaningful($index, -1);
        while ($prev >= 0 && !$this->stops($t, $prev)) {
            $index = $prev;
            $prev = $t->meaningful($index, -1);
            if ($this->blocks->isEnd($t, $index)) {
                $prev = $this->blocks->start($t, $index);
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_OBJECT_OPERATOR || $k === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR || $k === \__McTokId::T_DOUBLE_COLON) {
                $prev = $t->meaningful($index, -1);
                $multiline = $multiline || ($prev >= 0 && $this->partialMultiline($t, $prev, $index));
            }
        }
        return $multiline ? $this->detectIndent($t, $index) : null;
    }

    private function stops(FlatTokens $t, int $q): bool
    {
        $k = $t->k[$q];
        return $k === 59 || $k === 58 || $k === 123 || $k === 125 || $k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO || $k === \__McTokId::T_ELSE;
    }

    private function partialMultiline(FlatTokens $t, int $start, int $end): bool
    {
        for ($i = $start; $i <= $end; ++$i) {
            if (\str_contains($t->content($i), "\n")) {
                return true;
            }
        }
        return false;
    }

    /** WhitespacesAnalyzer::detectIndent */
    private function detectIndent(FlatTokens $t, int $index): string
    {
        while (true) {
            $w = -1;
            for ($j = $index - 1; $j >= 0; --$j) {
                if ($t->k[$j] === FlatTokens::WS && $t->c[$j] !== '') {
                    $w = $j;
                    break;
                }
            }
            if ($w < 0) {
                return '';
            }
            if (\str_contains($t->c[$w], "\n")) {
                break;
            }
            if ($w >= 1) {
                $p = $w - 1;
                $c = $t->content($p);
                $open = $t->i[$p] !== -1 && $t->k[$p] === \__McTokId::T_OPEN_TAG;
                $comment = $t->k[$p] === FlatTokens::COMMENT && !Trivia::isDoc($c);
                if (($open || $comment) && \substr($c, -1) === "\n") {
                    break;
                }
            }
            $index = $w;
        }
        $parts = \explode("\n", $t->c[$w]);
        return $parts[\count($parts) - 1];
    }
}
