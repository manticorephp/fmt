<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocTrimFixer (priority -5): no blank lines at the start / end of a docblock. */
final class PhpdocTrim extends DocRule
{
    public function name(): string
    {
        return 'phpdoc_trim';
    }

    public function priority(): int
    {
        return -5;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        // necessary for either pattern: a line without content right after the start or right before the end
        if (\strpbrk($doc, "\r\n\x0b\x0c\x85\xe2") === false || !Preg::test('~^/\*\*\R\h*(?:\*\h*)?\R|\R\h*(?:\*\h*)?\R\h*\*/$~', $doc)) {
            return $doc;
        }
        $doc = Preg::replace('~
                (^/\*\*)            # DocComment begin
                (?:
                    \R\h*(?:\*\h*)? # lines without useful content
                    (?!\R\h*\*/)    # not followed by a DocComment end
                )+
                (\R\h*(?:\*\h*)?\S) # first line with useful content
            ~x', '$1$2', $doc);
        return Preg::replace('~
                (\R\h*(?:\*\h*)?\S.*?) # last line with useful content
                (?:
                    (?<!/\*\*)         # not preceded by a DocComment start
                    \R\h*(?:\*\h*)?    # lines without useful content
                )+
                (\R\h*\*/$)            # DocComment end
            ~x', '$1$2', $doc);
    }
}
