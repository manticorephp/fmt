<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DoctrineToken;
use Fmt\Doc\DoctrineTokens;
use Fmt\DoctrineAnnotationRule;

/**
 * Parity target: PhpCsFixer\Fixer\DoctrineAnnotation\DoctrineAnnotationBracesFixer (priority 0). Annotations get `()` after their name (with_braces) or lose an empty `()`
 * (without_braces). Port over the token list with upstream's live indexes (an insertion shifts what the walk visits next).
 */
final class DoctrineAnnotationBraces extends DoctrineAnnotationRule
{
    /** @param string[] $ignoredTags */
    public function __construct(array $ignoredTags, private bool $with)
    {
        parent::__construct($ignoredTags);
    }

    public function name(): string
    {
        return 'doctrine_annotation_braces';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @param DoctrineToken[] $tokens */
    protected function fixAnnotations(array $tokens): ?array
    {
        if (!$this->with) {
            for ($index = 0, $max = \count($tokens); $index < $max; ++$index) {
                if (!$tokens[$index]->isType(DoctrineTokens::T_AT)) {
                    continue;
                }
                $open = DoctrineTokens::nextMeaningful($tokens, $index + 1);
                if ($open === null || !$tokens[$open]->isType(DoctrineTokens::T_OPEN_PARENTHESIS)) {
                    continue;
                }
                $close = DoctrineTokens::nextMeaningful($tokens, $open);
                if ($close === null || !$tokens[$close]->isType(DoctrineTokens::T_CLOSE_PARENTHESIS)) {
                    continue;
                }
                for ($current = $index + 2; $current <= $close; ++$current) {
                    $tokens[$current]->clear();
                }
            }
            return $tokens;
        }
        for ($index = 0; $index < \count($tokens); ++$index) {
            if (!$tokens[$index]->isType(DoctrineTokens::T_AT)) {
                continue;
            }
            $brace = DoctrineTokens::nextMeaningful($tokens, $index + 1);
            if ($brace !== null && $tokens[$brace]->isType(DoctrineTokens::T_OPEN_PARENTHESIS)) {
                continue;
            }
            $tokens = self::insertAt($tokens, $index + 2, new DoctrineToken(DoctrineTokens::T_OPEN_PARENTHESIS, '('));
            $tokens = self::insertAt($tokens, $index + 3, new DoctrineToken(DoctrineTokens::T_CLOSE_PARENTHESIS, ')'));
        }
        return $tokens;
    }

    /**
     * Tokens::insertAt on a list (a position past the end pads with empty tokens, as the shifted fixed array does)
     * @param DoctrineToken[] $tokens
     * @return DoctrineToken[]
     */
    public static function insertAt(array $tokens, int $index, DoctrineToken $token): array
    {
        $out = [];
        $n = \count($tokens);
        for ($x = 0; $x < $index && $x < $n; $x++) {
            $out[] = $tokens[$x];
        }
        while (\count($out) < $index) {
            $out[] = new DoctrineToken();
        }
        $out[] = $token;
        for ($x = $index; $x < $n; $x++) {
            $out[] = $tokens[$x];
        }
        return $out;
    }
}
