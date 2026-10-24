<?php

declare(strict_types=1);

namespace Fmt\Doc;

/** PhpCsFixer\Doctrine\Annotation\Token: a piece of a docblock as the Doctrine lexer sees it (T_NONE = plain text). */
final class DoctrineToken
{
    public function __construct(public int $type = DoctrineTokens::T_NONE, public string $content = '', public int $position = 0) {}

    public function isType(int $type): bool
    {
        return $this->type === $type;
    }

    public function isTypeOf(int $a, int $b): bool
    {
        return $this->type === $a || $this->type === $b;
    }

    public function clear(): void
    {
        $this->content = '';
    }
}
