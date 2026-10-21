<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\PhpdocReadonlyClassCommentToKeywordFixer (priority 4, risky; PHP >= 8.2 like the oracle). A docblock with a `@readonly` annotation in front of
 * a class (modifiers allowed) loses the annotation (the docblock goes when it is empty) and the class gets the `readonly` keyword. Whole-file FlatTokens port.
 */
final class PhpdocReadonlyClassCommentToKeyword extends FlatRule
{
    public function name(): string
    {
        return 'phpdoc_readonly_class_comment_to_keyword';
    }

    public function priority(): int
    {
        return 4;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i <= $s->n; $i++) {
            if ($s->gapFlags[$i] !== 0 && \str_contains($s->gapBefore[$i], '@readonly')) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($index = 0; $index < $t->count(); $index++) {
            if ($t->k[$index] !== FlatTokens::COMMENT || !Trivia::isDoc($t->c[$index]) || !\str_contains($t->c[$index], '@readonly')) {
                continue;
            }
            $doc = new DocBlock($t->c[$index]);
            $annotations = $doc->getAnnotationsOfType(['readonly']);
            if (\count($annotations) === 0) {
                continue;
            }
            foreach ($annotations as $a) {
                $a->remove();
            }
            $main = $index;
            $i = $t->meaningful($index, 1);
            $add = true;
            while ($i >= 0 && $t->i[$i] !== -1 && ($t->k[$i] === \__McTokId::T_ABSTRACT || $t->k[$i] === \__McTokId::T_FINAL || $t->k[$i] === \__McTokId::T_PRIVATE
                || $t->k[$i] === \__McTokId::T_PUBLIC || $t->k[$i] === \__McTokId::T_PROTECTED || $t->k[$i] === \__McTokId::T_READONLY)) {
                if ($t->k[$i] === \__McTokId::T_READONLY) {
                    $add = false;
                }
                $i = $t->meaningful($i, 1);
            }
            if ($i < 0 || !self::is($t, $i, \__McTokId::T_CLASS)) {
                continue;
            }
            if ($add) {
                $t->insertAt($i, [FlatTokens::code(\__McTokId::T_READONLY, 'readonly'), FlatTokens::ws(' ')]);
            }
            $new = $doc->getContent();
            if ($new === '') {
                self::clearMerge($t, $main);
                continue;
            }
            $t->c[$main] = $new;
        }
    }
}
