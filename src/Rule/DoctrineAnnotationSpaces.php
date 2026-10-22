<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DoctrineToken;
use Fmt\Doc\DoctrineTokens;
use Fmt\DoctrineAnnotationRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationSpacesFixer (priority 0). Spaces inside Doctrine annotations: none around the parentheses
 * (around_parentheses), a space after commas (around_commas), and around `=` of arguments and `=` / `:` of arrays as configured (true: one space, false: none, null: untouched).
 * The walks are upstream's live iterations over a shifting token list, including the second visit of a token that got an empty token inserted before it.
 */
final class DoctrineAnnotationSpaces extends DoctrineAnnotationRule
{
    /** @param array<string, int> $assign the six assignment options by name: -1 null (untouched), 0 false, 1 true */
    public function __construct(array $ignoredTags, private bool $parentheses, private bool $commas, private array $assign)
    {
        parent::__construct($ignoredTags);
    }

    public function name(): string
    {
        return 'doctrine_annotation_spaces';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @param DoctrineToken[] $tokens */
    protected function fixAnnotations(array $tokens): ?array
    {
        if ($this->parentheses) {
            $tokens = $this->aroundParentheses($tokens);
            if ($tokens === null) {
                return null;
            }
        }
        if ($this->commas) {
            $tokens = $this->aroundCommas($tokens);
            if ($tokens === null) {
                return null;
            }
        }
        foreach ($this->assign as $v) {
            if ($v !== -1) {
                return $this->aroundAssignments($tokens);
            }
        }
        return $tokens;
    }

    /**
     * @param DoctrineToken[] $tokens
     * @return DoctrineToken[]|null
     */
    private function aroundParentheses(array $tokens): ?array
    {
        $until = null;
        $n = \count($tokens);
        for ($index = 0; $index < $n; ++$index) {
            $token = $tokens[$index];
            if ($until !== null) {
                if ($index === $until) {
                    $until = null;
                    continue;
                }
            } elseif ($token->isType(DoctrineTokens::T_AT)) {
                $end = DoctrineTokens::annotationEnd($tokens, $index);
                if ($end !== null) {
                    $until = $end + 1;
                }
                continue;
            }
            if ($until === null) {
                continue;
            }
            if (!$token->isTypeOf(DoctrineTokens::T_OPEN_PARENTHESIS, DoctrineTokens::T_CLOSE_PARENTHESIS)) {
                continue;
            }
            if ($token->isType(DoctrineTokens::T_OPEN_PARENTHESIS)) {
                if ($index - 1 < 0 || $index + 1 >= $n) {
                    return null;
                }
                $prev = $tokens[$index - 1];
                if ($prev->isType(DoctrineTokens::T_NONE)) {
                    $prev->clear();
                }
                $token = $tokens[$index + 1];
            } else {
                if ($index - 1 < 0) {
                    return null;
                }
                $token = $tokens[$index - 1];
            }
            if ($token->isType(DoctrineTokens::T_NONE)) {
                if (\str_contains($token->content, "\n")) {
                    continue;
                }
                $token->clear();
            }
        }
        return $tokens;
    }

    /**
     * @param DoctrineToken[] $tokens
     * @return DoctrineToken[]|null
     */
    private function aroundCommas(array $tokens): ?array
    {
        $until = null;
        for ($index = 0; $index < \count($tokens); ++$index) {
            $token = $tokens[$index];
            if ($until !== null) {
                if ($index === $until) {
                    $until = null;
                    continue;
                }
            } elseif ($token->isType(DoctrineTokens::T_AT)) {
                $end = DoctrineTokens::annotationEnd($tokens, $index);
                if ($end !== null) {
                    $until = $end;
                }
                continue;
            }
            if ($until === null) {
                continue;
            }
            if (!$token->isType(DoctrineTokens::T_COMMA)) {
                continue;
            }
            if ($index - 1 < 0) {
                return null;
            }
            $prev = $tokens[$index - 1];
            if ($prev->isType(DoctrineTokens::T_NONE)) {
                $prev->clear();
            }
            if ($index < \count($tokens) - 1 && !Preg::match('/^\s/', $tokens[$index + 1]->content)) {
                $tokens = DoctrineAnnotationBraces::insertAt($tokens, $index + 1, new DoctrineToken(DoctrineTokens::T_NONE, ' '));
            }
        }
        return $tokens;
    }

    /**
     * @param DoctrineToken[] $tokens
     * @return DoctrineToken[]|null
     */
    private function aroundAssignments(array $tokens): ?array
    {
        /** @var int[] $scopes */
        $scopes = [];
        for ($index = 0; $index < \count($tokens); ++$index) {
            $token = $tokens[$index];
            $end = $scopes === [] ? -1 : $scopes[\count($scopes) - 1];
            if ($end !== -1 && $token->isType($end)) {
                \array_pop($scopes);
                continue;
            }
            if ($token->isType(DoctrineTokens::T_AT)) {
                $scopes[] = DoctrineTokens::T_CLOSE_PARENTHESIS;
                continue;
            }
            if ($token->isType(DoctrineTokens::T_OPEN_CURLY_BRACES)) {
                $scopes[] = DoctrineTokens::T_CLOSE_CURLY_BRACES;
                continue;
            }
            if ($end === DoctrineTokens::T_CLOSE_PARENTHESIS && $token->isType(DoctrineTokens::T_EQUALS)) {
                $tokens = $this->updateAt($tokens, $index + 1, $index + 1, $this->assign['after_argument_assignments']);
                if ($tokens === null) {
                    return null;
                }
                $tokens = $this->updateAt($tokens, $index - 1, $index, $this->assign['before_argument_assignments']);
                if ($tokens === null) {
                    return null;
                }
                continue;
            }
            if ($end === DoctrineTokens::T_CLOSE_CURLY_BRACES) {
                if ($token->isType(DoctrineTokens::T_EQUALS)) {
                    $tokens = $this->updateAt($tokens, $index + 1, $index + 1, $this->assign['after_array_assignments_equals']);
                    if ($tokens === null) {
                        return null;
                    }
                    $tokens = $this->updateAt($tokens, $index - 1, $index, $this->assign['before_array_assignments_equals']);
                    if ($tokens === null) {
                        return null;
                    }
                    continue;
                }
                if ($token->isType(DoctrineTokens::T_COLON)) {
                    $tokens = $this->updateAt($tokens, $index + 1, $index + 1, $this->assign['after_array_assignments_colon']);
                    if ($tokens === null) {
                        return null;
                    }
                    $tokens = $this->updateAt($tokens, $index - 1, $index, $this->assign['before_array_assignments_colon']);
                    if ($tokens === null) {
                        return null;
                    }
                }
            }
        }
        return $tokens;
    }

    /**
     * @param DoctrineToken[] $tokens
     * @return DoctrineToken[]|null
     */
    private function updateAt(array $tokens, int $index, int $insertIndex, int $insert): ?array
    {
        if ($insert === -1) {
            return $tokens;
        }
        if ($index < 0 || $index >= \count($tokens)) {
            return null; // SplFixedArray: Index invalid or out of range
        }
        $token = $tokens[$index];
        if ($insert === 1) {
            if (!$token->isType(DoctrineTokens::T_NONE)) {
                $token = new DoctrineToken();
                $tokens = DoctrineAnnotationBraces::insertAt($tokens, $insertIndex, $token);
            }
            $token->content = ' ';
        } elseif ($token->isType(DoctrineTokens::T_NONE)) {
            $token->clear();
        }
        return $tokens;
    }
}
