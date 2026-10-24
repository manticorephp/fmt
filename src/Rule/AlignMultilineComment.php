<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Gap;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\AlignMultilineCommentFixer (priority 27): the lines of a multi-line docblock (and, with
 * comment_type phpdocs_like / all_multiline, of a `/* *\/` comment) are aligned on the indentation of its first line, with a star.
 */
final class AlignMultilineComment extends DocRule
{
    /** @param string $type phpdocs_only | phpdocs_like | all_multiline */
    public function __construct(private string $type, private string $lineEnding) {}

    public function name(): string
    {
        return 'align_multiline_comment';
    }

    public function priority(): int
    {
        return 27;
    }

    public function needsMask(): int
    {
        return $this->type === 'phpdocs_only' ? Gap::G_DOC : Gap::G_COMMENT;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB_OPEN;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        return $doc;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if (($s->gapFlags[$i] & ($this->type === 'phpdocs_only' ? Gap::G_DOC : Gap::G_COMMENT)) === 0) {
            return $gap;
        }
        $p = Gap::pieces($gap);
        if (isset($s->wsSplit[$i])) {
            $p = Gap::splitPieces($p, $s->wsSplit[$i]); // upstream holds two adjacent whitespace tokens there
        }
        $changed = false;
        foreach ($p as $k => $x) {
            $doc = DocRule::isDocPiece($s, $i, $x);
            if (!$doc && ($this->type === 'phpdocs_only' || Gap::isSpace($x))) {
                continue;
            }
            if ($k > 0 && Gap::isSpace($p[$k - 1])) {
                $ws = $p[$k - 1];
                $prev = $k - 2;
            } else {
                $ws = '';
                $prev = $k - 1;
            }
            if ($prev < 0 && $i > 0 && $s->kind[$i - 1] === \__McTokId::T_OPEN_TAG) {
                $ws = Preg::replace('/\S/', '', $s->text($i - 1)) . $ws;
            }
            if (!Preg::match('/\R(\h*)$/', $ws, $mm)) {
                continue;
            }
            if (!$doc && $this->type !== 'all_multiline' && Preg::test('/\R(?:\R|\s*[^\s\*])/', $x)) {
                continue;
            }
            $indent = (string) $mm[1];
            $lines = Preg::split('/\R/', $x);
            foreach ($lines as $n => $line) {
                if ($n === 0) {
                    continue;
                }
                $line = \ltrim($line);
                if (!$doc && ($line === '' || $line[0] !== '*')) {
                    continue;
                }
                if ($line === '') {
                    $line = '*';
                } elseif ($line[0] !== '*') {
                    $line = '* ' . $line;
                }
                $lines[$n] = $indent . ' ' . $line;
            }
            $new = \implode($this->lineEnding, $lines);
            if ($new !== $x) {
                $p[$k] = $new;
                $changed = true;
            }
        }
        return $changed ? \implode('', $p) : $gap;
    }
}
