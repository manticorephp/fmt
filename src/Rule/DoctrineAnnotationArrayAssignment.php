<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DoctrineToken;
use Fmt\Doc\DoctrineTokens;
use Fmt\DoctrineAnnotationRule;

/**
 * Parity target: PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationArrayAssignmentFixer (priority 1). Inside `{ .. }` of a Doctrine annotation every `=` / `:` becomes
 * the configured operator.
 */
final class DoctrineAnnotationArrayAssignment extends DoctrineAnnotationRule
{
    /** @param string[] $ignoredTags */
    public function __construct(array $ignoredTags, private string $operator)
    {
        parent::__construct($ignoredTags);
    }

    public function name(): string
    {
        return 'doctrine_annotation_array_assignment';
    }

    public function priority(): int
    {
        return 1;
    }

    /** @param DoctrineToken[] $tokens */
    protected function fixAnnotations(array $tokens): ?array
    {
        /** @var bool[] $scopes true = array, false = annotation */
        $scopes = [];
        foreach ($tokens as $token) {
            if ($token->isType(DoctrineTokens::T_OPEN_PARENTHESIS)) {
                $scopes[] = false;
                continue;
            }
            if ($token->isType(DoctrineTokens::T_OPEN_CURLY_BRACES)) {
                $scopes[] = true;
                continue;
            }
            if ($token->isTypeOf(DoctrineTokens::T_CLOSE_PARENTHESIS, DoctrineTokens::T_CLOSE_CURLY_BRACES)) {
                \array_pop($scopes);
                continue;
            }
            if ($scopes !== [] && $scopes[\count($scopes) - 1] && $token->isTypeOf(DoctrineTokens::T_EQUALS, DoctrineTokens::T_COLON)) {
                $token->content = $this->operator;
            }
        }
        return $tokens;
    }
}
