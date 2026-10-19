<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\NamespaceNotation\BlankLineAfterNamespaceFixer (priority -20). Behind `namespace Foo;`: walk past
 * trailing comments on the same line (a blank-only whitespace token followed by a comment, and the comments themselves); the token
 * after that point becomes "\n\n" + its indent after the last line break (one "\n" at the very end of the file), or is inserted.
 */
final class BlankLineAfterNamespace extends GapRule
{
    public function __construct(private string $le = "\n")
    {
    }

    public function name(): string
    {
        return 'blank_line_after_namespace';
    }

    public function priority(): int
    {
        return -20;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function afterKinds(): array
    {
        return [59];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($i === 0 || $s->kind[$i - 1] !== 59 || $s->ct[$i - 1] !== 0) {
            return $gap;
        }
        $k = $i - 2;
        while ($k >= 0 && $s->ct[$k] === 0 && ($s->kind[$k] === \__McTokId::T_STRING || $s->kind[$k] === \__McTokId::T_NS_SEPARATOR)) {
            $k--;
        }
        if ($k < 0 || $k === $i - 2 || $s->kind[$k] !== \__McTokId::T_NAMESPACE || $s->ct[$k] !== 0) {
            return $gap;
        }
        $last = $i === $s->n;
        $p = $gap === '' ? [] : Gap::pieces($gap);
        $m = \count($p);
        $pos = -1;
        $q = 0;
        while ($q < $m) {
            $x = $p[$q];
            if (Gap::isSpace($x)) {
                if (Gap::pieceHasR($x)) {
                    break;
                }
                if ($q + 1 >= $m) {
                    if ($last) {
                        $s->abort = true; // upstream asks for the token behind the end of the file and throws: the file stays as it is
                        return $gap;
                    }
                    break; // the token behind a blank-only whitespace token is code, not a comment
                }
            }
            $pos = $q;
            $q++;
        }
        $atEnd = $pos + 1 >= $m;
        if (!$atEnd) {
            // p[pos + 1] is the whitespace token to rewrite
            $x = $p[$pos + 1];
            $p[$pos + 1] = $this->blank($x, $last && $pos + 2 >= $m);
            return \implode('', $p);
        }
        return $gap . ($last ? $this->le : $this->le . $this->le);
    }

    private function blank(string $content, bool $isLast): string
    {
        $ending = $isLast ? $this->le : $this->le . $this->le;
        $r = \preg_match('/^.*\R( *)$/su', $content, $m);
        if ($r === false) {
            $r = \preg_match('/^.*\R( *)$/s', $content, $m);
        }
        return $ending . ($r === 1 ? $m[1] : '');
    }
}
