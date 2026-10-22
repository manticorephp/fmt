<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CommentsAnalyzer;
use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Comment\CommentToPhpdocFixer (priority 26, risky). A line or block comment (`//`, `#`, slash-star) that carries an annotation (`@var`, `@param`, ..; not an ignored tag nor
 * `@x-ignore` / `@x-suppress`) and stands before a structural element, control structure or variable assignment becomes a docblock; a block of `//` / `#` comments becomes one multi-line
 * docblock. Header comments (right after the open tag / `declare`) stay. Whole-file FlatTokens port: comments are items of the flat list.
 */
final class CommentToPhpdoc extends FlatRule
{
    /** @var string[] */
    private array $ignored = [];

    /** @param string[] $ignoredTags */
    public function __construct(array $ignoredTags, private string $lineEnding)
    {
        parent::__construct();
        foreach ($ignoredTags as $x) {
            $this->ignored[] = \strtolower($x);
        }
    }

    public function name(): string
    {
        return 'comment_to_phpdoc';
    }

    public function priority(): int
    {
        return 26;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->gapFlags[$i] !== 0 && \str_contains($s->gapBefore[$i], '@') && self::plainWithAt($s->gapBefore[$i])) {
                return true;
            }
        }
        return $s->gapFlags[$s->n] !== 0 && \str_contains($s->gapBefore[$s->n], '@') && self::plainWithAt($s->gapBefore[$s->n]);
    }

    /** a `//`, `#` or non-doc block comment with an `@` after its opener: a superset of the comments the rule can turn into docblocks */
    private static function plainWithAt(string $gap): bool
    {
        return \preg_match('~(?://|#)[^\r\n]*@|/\*(?!\*)(?:(?!\*/).)*@~s', $gap) === 1;
    }

    private static function isPlainComment(FlatTokens $t, int $q): bool
    {
        return $t->k[$q] === FlatTokens::COMMENT && !Trivia::isDoc($t->c[$q]);
    }

    private static function commentType(string $c): int
    {
        if ($c[0] === '#') {
            return 1;
        }
        if ($c[1] === '*') {
            return 3;
        }
        return 2;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $limit = $t->count();
        for ($index = 0; $index < $limit; $index++) {
            if (!self::isPlainComment($t, $index)) {
                continue;
            }
            $content = $t->c[$index];
            if ($this->isHeader($t, $index)) {
                continue;
            }
            $next = $this->nextToken($t, $index);
            if ($next < 0 || $t->i[$next] < 0 || !CommentsAnalyzer::beforeStructural($s, $t->i[$next], $content)) {
                continue;
            }
            if (Preg::match('~(?:#|//|/\*+|\R(?:\s*\*)?)\s*\@(?=\w+-(ignore|suppress))([a-zA-Z0-9_\\\\-]+)(?=\s|\(|$)~', $content)) {
                continue;
            }
            $indices = $this->blockIndices($t, $index);
            if ($this->isCandidate($t, $indices)) {
                if (!$this->fixComment($s, $t, $indices)) {
                    return;
                }
            }
            $index = \max($indices);
        }
    }

    private function isHeader(FlatTokens $t, int $index): bool
    {
        if ($t->meaningful($index, 1) < 0) {
            return false;
        }
        $prev = $t->nonWhitespace($index, -1);
        if ($prev >= 0 && self::is($t, $prev, 59)) {
            $close = $t->meaningful($prev, -1);
            if (!self::is($t, $close, 41)) {
                return false;
            }
            $open = $t->blockStart($close, 40);
            $declare = $t->meaningful($open, -1);
            if (!self::is($t, $declare, \__McTokId::T_DECLARE)) {
                return false;
            }
            $prev = $t->nonWhitespace($declare, -1);
        }
        return $prev >= 0 && self::is($t, $prev, \__McTokId::T_OPEN_TAG);
    }

    /** CommentsAnalyzer::getNextTokenIndex over the items */
    private function nextToken(FlatTokens $t, int $index): int
    {
        $next = $index;
        do {
            $next = $t->meaningful($next, 1);
            while ($next >= 0 && $t->k[$next] === \__McTokId::T_ATTRIBUTE) {
                $next = $t->blockEnd($next, CT::ATTRIBUTE_CLOSE);
                $next = $next < 0 ? -1 : $t->meaningful($next, 1);
            }
        } while ($next >= 0 && self::is($t, $next, 40));
        return $next;
    }

    /** @return int[] */
    private function blockIndices(FlatTokens $t, int $index): array
    {
        $type = self::commentType($t->c[$index]);
        $indices = [$index];
        if ($type === 3) {
            return $indices;
        }
        $count = $t->count();
        for ($i = $index + 1; $i < $count; $i++) {
            if ($t->k[$i] === FlatTokens::COMMENT) {
                if ($type === self::commentType($t->c[$i]) && !Trivia::isDoc($t->c[$i])) {
                    $indices[] = $i;
                    continue;
                }
                break;
            }
            if ($t->k[$i] !== FlatTokens::WS || \preg_match_all('/\R/u', $t->c[$i]) > 1) {
                break;
            }
        }
        return $indices;
    }

    /** @param int[] $indices */
    private function isCandidate(FlatTokens $t, array $indices): bool
    {
        foreach ($indices as $i) {
            if (!Preg::match('~(?:#|//|/\*+|\R(?:\s*\*)?)\s*\@([a-zA-Z0-9_\\\\-]+)(?=\s|\(|$)~', $t->c[$i], $m)) {
                continue;
            }
            if (!\in_array(\strtolower($m[1]), $this->ignored, true)) {
                return true;
            }
        }
        return false;
    }

    /** @param int[] $indices false: upstream throws (the file stays as it is) */
    private function fixComment(Source $s, FlatTokens $t, array $indices): bool
    {
        if (\count($indices) === 1) {
            $q = $indices[0];
            $msg = self::message($t->c[$q]);
            if (\str_contains($msg, '*/')) {
                $s->abort = true; // the docblock would end inside its text: upstream's result does not lint, the file stays as it is
                return false;
            }
            if (\trim(\substr($msg, 0, 1)) !== '') {
                $msg = ' ' . $msg;
            }
            if (\trim(\substr($msg, -1)) !== '') {
                $msg .= ' ';
            }
            $t->c[$q] = '/**' . $msg . '*/';
            return true;
        }
        $start = $indices[0];
        $prev = $start - 1;
        if ($prev < 0 || $t->k[$prev] !== FlatTokens::WS) {
            $s->abort = true; // Utils::calculateTrailingWhitespaceIndent throws for a token that is no whitespace
            return false;
        }
        $norm = \str_replace(["\r\n", "\r"], "\n", $t->c[$prev]);
        $pos = \strrpos($norm, "\n");
        $indent = $pos === false ? '' : \ltrim(\substr($norm, $pos), "\n");
        $new = '/**' . $this->lineEnding;
        $count = \max($indices);
        for ($i = $start; $i <= $count; $i++) {
            if ($t->k[$i] !== FlatTokens::COMMENT) {
                continue;
            }
            if (\str_contains($t->c[$i], '*/')) {
                return true;
            }
            $msg = self::message($t->c[$i]);
            if (\trim(\substr($msg, 0, 1)) !== '') {
                $msg = ' ' . $msg;
            }
            $new .= $indent . ' *' . $msg . $this->lineEnding;
        }
        for ($i = $start; $i <= $count; $i++) {
            $t->clearAt($i);
        }
        $new .= $indent . ' */';
        $t->insertAt($start, [[FlatTokens::COMMENT, $new, -1]]);
        return true;
    }

    private static function message(string $c): string
    {
        if (\str_starts_with($c, '#')) {
            return \substr($c, 1);
        }
        if (\str_starts_with($c, '//')) {
            return \substr($c, 2);
        }
        return \rtrim(\ltrim($c, '/*'), '*/');
    }
}
