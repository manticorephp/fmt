<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Comment\SingleLineCommentStyleFixer (priority -31). hash: `# x` -> `// x` (not `#[`). asterisk: a one-line-of-text
 * block comment alone on its line(s) becomes `// text` (stars and blanks around the text dropped) and the blanks right behind it go; a block comment
 * that ends the file, or is followed by anything but a whitespace token with a line break, is left. Comments are trivia: pieces of a gap.
 */
final class SingleLineCommentStyle extends GapRule
{
    public function __construct(private bool $asterisk, private bool $hash) {}

    public function name(): string
    {
        return 'single_line_comment_style';
    }

    public function priority(): int
    {
        return -31;
    }

    public function needsMask(): int
    {
        return Gap::G_COMMENT;
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
        if (!Gap::hasComment($gap)) {
            return $gap;
        }
        $p = isset($s->wsSplit[$i]) ? Gap::splitPieces(Gap::pieces($gap), $s->wsSplit[$i]) : Gap::pieces($gap); // upstream holds two adjacent whitespace tokens at a split
        $m = \count($p);
        $changed = false;
        for ($k = 0; $k < $m; $k++) {
            $c = $p[$k];
            if (Gap::isSpace($c) || Trivia::isDoc($c)) {
                continue;
            }
            $inner = \strlen($c) > 4 ? \substr($c, 2, -2) : '';
            if ($this->hash && $c[0] === '#') {
                if (isset($c[1]) && $c[1] === '[') {
                    continue;
                }
                $p[$k] = '//' . \substr($c, 1);
                $changed = true;
                continue;
            }
            if (!$this->asterisk || $c[0] !== '/' || $c[1] !== '*' || \str_contains($inner, '?>') || Trivia::pmatch('/[^\s\*].*\R.*[^\s\*]/s', $inner)) {
                continue;
            }
            // the element after the comment: the next piece, else the next code token (or nothing at the end of the file)
            if ($k + 1 < $m) {
                if (!Gap::isSpace($p[$k + 1]) || !Trivia::pmatch('/\R/', $p[$k + 1])) {
                    continue;
                }
                $p[$k + 1] = \ltrim($p[$k + 1], " \t");
            } elseif ($i < $s->n) {
                continue;
            }
            $new = '//';
            if (Trivia::pmatch('/[^\s\*]/', $inner)) {
                $new = '// ' . Trivia::preplace('/[\s\*]*([^\s\*](?:.+[^\s\*])?)[\s\*]*/', '\1', $inner);
            }
            $p[$k] = $new;
            $changed = true;
        }
        return $changed ? \implode('', $p) : $gap;
    }
}
