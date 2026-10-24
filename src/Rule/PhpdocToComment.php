<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\DocRule;
use Fmt\Gap;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocToCommentFixer (priority 25) with CommentsAnalyzer: a docblock that documents nothing
 * (not the file header, not before a structural element, a control / assignment / echo that uses one of its variables, a trait use)
 * becomes a `/*` comment.
 */
final class PhpdocToComment extends DocRule
{
    /** @param string[] $ignoredTags lowercase */
    public function __construct(private array $ignoredTags, private bool $allowBeforeReturn) {}

    public function name(): string
    {
        return 'phpdoc_to_comment';
    }

    public function priority(): int
    {
        return 25;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if ($this->isHeader($s, $i, $p, $k)) {
            return $doc;
        }
        $next = \Fmt\CommentsAnalyzer::nextTokenIndex($s, $i);
        if ($next === -2) {
            $s->abort = true; // an attribute without its `]`: upstream's findBlockEnd throws
            return $doc;
        }
        if ($this->allowBeforeReturn && $next >= 0 && !($s->kind[$next] === 125 && $s->ct[$next] === 0) && $s->kind[$next] === \__McTokId::T_RETURN) {
            return $doc;
        }
        if (\Fmt\CommentsAnalyzer::beforeStructural($s, $next, $doc)) {
            return $doc;
        }
        if ($this->ignoredTags !== [] && Preg::matchAll('~\@([a-zA-Z0-9_\\\-]+)\b~', $doc, $m) > 0) {
            foreach ($m[1] as $tag) {
                if (\in_array(\strtolower((string) $tag), $this->ignoredTags, true)) {
                    return $doc;
                }
            }
        }
        return '/*' . \ltrim($doc, '/*');
    }

    /** the last non-whitespace piece before piece $k of gap $i: its index, or -1 (then it is token i - 1) */
    private static function prevPiece(array $p, int $k): int
    {
        for ($q = $k - 1; $q >= 0; $q--) {
            if (!Gap::isSpace($p[$q])) {
                return $q;
            }
        }
        return -1;
    }

    /** @param string[] $p */
    private function isHeader(Source $s, int $i, array $p, int $k): bool
    {
        if ($i >= $s->n) {
            return false; // no next meaningful token
        }
        if (self::prevPiece($p, $k) >= 0) {
            return false; // a comment before it
        }
        $t = $i - 1;
        if ($t < 0) {
            return false;
        }
        if ($s->kind[$t] === 59) {
            $close = $t - 1;
            if ($close < 0 || $s->kind[$close] !== 41 || $s->ct[$close] !== 0 || $s->pair[$close] < 0) {
                return false;
            }
            $decl = $s->pair[$close] - 1;
            if ($decl < 0 || $s->kind[$decl] !== \__McTokId::T_DECLARE) {
                return false;
            }
            $gp = Gap::pieces($s->gapBefore[$decl]);
            if (self::prevPiece($gp, \count($gp)) >= 0) {
                return false;
            }
            $t = $decl - 1;
            if ($t < 0) {
                return false;
            }
        }
        return $s->kind[$t] === \__McTokId::T_OPEN_TAG;
    }
}
