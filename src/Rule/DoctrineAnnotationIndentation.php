<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DoctrineToken;
use Fmt\Doc\DoctrineTokens;
use Fmt\DoctrineAnnotationRule;
use Fmt\Preg;

/**
 * Parity target: PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationIndentationFixer (priority 0). Inside a Doctrine annotation (or in front of one) every line start
 * is indented by 4 spaces per open parenthesis / brace level (after the docblock's ` * `); indent_mixed_lines indents a closing line that has content before its bracket.
 */
final class DoctrineAnnotationIndentation extends DoctrineAnnotationRule
{
    /** @param string[] $ignoredTags */
    public function __construct(array $ignoredTags, private bool $indentMixed)
    {
        parent::__construct($ignoredTags);
    }

    public function name(): string
    {
        return 'doctrine_annotation_indentation';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @param DoctrineToken[] $tokens */
    protected function fixAnnotations(array $tokens): ?array
    {
        $starts = [];
        $ends = [];
        for ($index = 0, $max = \count($tokens); $index < $max; ++$index) {
            if (!$tokens[$index]->isType(DoctrineTokens::T_AT)) {
                continue;
            }
            $end = DoctrineTokens::annotationEnd($tokens, $index);
            if ($end === null) {
                return $tokens;
            }
            $starts[] = $index;
            $ends[] = $end;
            $index = $end;
        }
        $level = 0;
        foreach ($tokens as $index => $token) {
            if (!$token->isType(DoctrineTokens::T_NONE) || !\str_contains($token->content, "\n")) {
                continue;
            }
            if (!$this->canBeFixed($tokens, $index, $starts, $ends)) {
                continue;
            }
            [$opening, $closing] = $this->braces($tokens, $index);
            $delta = $opening - $closing;
            $mixed = $delta === 0 && $opening > 0;
            $extra = 0;
            if ($level > 0 && ($delta < 0 || $mixed)) {
                --$level;
                if ($this->indentMixed && $this->closingLineWithContent($tokens, $index)) {
                    $extra = 1;
                }
            }
            $token->content = Preg::replace('/(\n( +\*)?) *$/', '$1' . \str_repeat(' ', 4 * ($level + $extra) + 1), $token->content);
            if ($delta > 0 || $mixed) {
                ++$level;
            }
        }
        return $tokens;
    }

    /**
     * @param DoctrineToken[] $tokens
     * @return int[]
     */
    private function braces(array $tokens, int $index): array
    {
        $opening = 0;
        $closing = 0;
        $n = \count($tokens);
        while (++$index < $n) {
            $token = $tokens[$index];
            if ($token->isType(DoctrineTokens::T_NONE) && \str_contains($token->content, "\n")) {
                break;
            }
            if ($token->isTypeOf(DoctrineTokens::T_OPEN_PARENTHESIS, DoctrineTokens::T_OPEN_CURLY_BRACES)) {
                ++$opening;
                continue;
            }
            if (!$token->isTypeOf(DoctrineTokens::T_CLOSE_PARENTHESIS, DoctrineTokens::T_CLOSE_CURLY_BRACES)) {
                continue;
            }
            if ($opening > 0) {
                --$opening;
            } else {
                ++$closing;
            }
        }
        return [$opening, $closing];
    }

    /** @param DoctrineToken[] $tokens */
    private function closingLineWithContent(array $tokens, int $index): bool
    {
        $n = \count($tokens);
        while (++$index < $n) {
            $token = $tokens[$index];
            if ($token->isType(DoctrineTokens::T_NONE)) {
                if (\str_contains($token->content, "\n")) {
                    return false;
                }
                continue;
            }
            return !$token->isTypeOf(DoctrineTokens::T_CLOSE_PARENTHESIS, DoctrineTokens::T_CLOSE_CURLY_BRACES);
        }
        return false;
    }

    /**
     * @param DoctrineToken[] $tokens
     * @param int[] $starts
     * @param int[] $ends
     */
    private function canBeFixed(array $tokens, int $newLine, array $starts, array $ends): bool
    {
        foreach ($starts as $x => $start) {
            if ($newLine >= $start && $newLine <= $ends[$x]) {
                return true;
            }
        }
        $n = \count($tokens);
        for ($index = $newLine + 1; $index < $n; ++$index) {
            if (\str_contains($tokens[$index]->content, "\n")) {
                return false;
            }
            return $tokens[$index]->isType(DoctrineTokens::T_AT);
        }
        return false;
    }
}
