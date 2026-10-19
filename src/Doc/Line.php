<?php

declare(strict_types=1);

namespace Fmt\Doc;

use Fmt\Preg;

/** Port of PhpCsFixer\DocBlock\Line: one line of a docblock, its line break included. */
final class Line
{
    public function __construct(public string $content)
    {
    }

    public function containsUsefulContent(): bool
    {
        return Preg::test('/\*\s*\S+/', $this->content) && '' !== \trim(\str_replace(['/', '*'], ' ', $this->content));
    }

    public function containsATag(): bool
    {
        return \str_contains($this->content, '@') && Preg::test('/\*\s*@/', $this->content);
    }

    public function isTheStart(): bool
    {
        return \str_contains($this->content, '/**');
    }

    public function isTheEnd(): bool
    {
        return \str_contains($this->content, '*/');
    }

    public function remove(): void
    {
        $this->content = '';
    }

    public function addBlank(): void
    {
        if (!Preg::match('/^(\h*\*)[^\r\n]*(\r?\n)$/', $this->content, $m)) {
            return;
        }
        $this->content .= $m[1] . $m[2];
    }
}
