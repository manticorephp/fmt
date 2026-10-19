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
    public function __construct(private array $ignoredTags, private bool $allowBeforeReturn)
    {
    }

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
        $next = $this->nextTokenIndex($s, $i);
        if ($next === -2) {
            $s->abort = true; // an attribute without its `]`: upstream's findBlockEnd throws
            return $doc;
        }
        if ($this->allowBeforeReturn && $next >= 0 && !($s->kind[$next] === 125 && $s->ct[$next] === 0) && $s->kind[$next] === \__McTokId::T_RETURN) {
            return $doc;
        }
        if ($this->beforeStructural($s, $next, $doc)) {
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

    /** CommentsAnalyzer::getNextTokenIndex: -1 = none, -2 = an unbalanced attribute */
    private function nextTokenIndex(Source $s, int $i): int
    {
        $next = $i;
        $first = true;
        do {
            $next = $first ? $next : $next + 1;
            $first = false;
            if ($next >= $s->n) {
                return -1;
            }
            while ($next < $s->n && $s->kind[$next] === \__McTokId::T_ATTRIBUTE) {
                $end = $s->pair[$next];
                if ($end < 0) {
                    return -2;
                }
                $next = $end + 1;
            }
            if ($next >= $s->n) {
                return -1;
            }
        } while ($s->kind[$next] === 40 && $s->ct[$next] === 0);
        return $next;
    }

    private function beforeStructural(Source $s, int $next, string $doc): bool
    {
        if ($next < 0 || ($s->kind[$next] === 125 && $s->ct[$next] === 0)) {
            return false;
        }
        $k = $s->kind[$next];
        $c = $s->ct[$next];
        // isStructuralElement
        if ($c === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM
            || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_VAR
            || $k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_FN || $k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_CONST
            || $k === \__McTokId::T_NAMESPACE || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE || $k === \__McTokId::T_INCLUDE
            || $k === \__McTokId::T_INCLUDE_ONCE || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PUBLIC_SET
            || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PRIVATE_SET)) {
            return true;
        }
        if ($c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE) {
            return true;
        }
        if ($c === 0 && $k === \__McTokId::T_STRING) {
            $t = \strtolower($s->text($next));
            return $t === 'get' || $t === 'set';
        }
        if ($c === 0 && $k === \__McTokId::T_CASE) {
            for ($x = $next - 1; $x >= 0; $x--) {
                if ($s->kind[$x] === \__McTokId::T_ENUM && $s->ct[$x] === 0) {
                    return true;
                }
                if ($s->kind[$x] === \__McTokId::T_SWITCH) {
                    return false;
                }
            }
            return false;
        }
        if ($c === 0 && $k === \__McTokId::T_STATIC) {
            return !($next + 1 < $s->n && $s->kind[$next + 1] === \__McTokId::T_DOUBLE_COLON);
        }
        // isValidControl
        if ($k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_IF || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_WHILE) {
            $open = $next + 1;
            $close = $open < $s->n ? $s->pair[$open] : -1;
            for ($x = $open + 1; $x < $close; $x++) {
                if ($s->kind[$x] === \__McTokId::T_VARIABLE && \str_contains($doc, $s->text($x))) {
                    return true;
                }
            }
            return false;
        }
        // isValidVariable
        if ($k === \__McTokId::T_VARIABLE) {
            if ($next + 1 >= $s->n) {
                return false;
            }
            $a = $s->kind[$next + 1];
            return $a === 61 || ($a >= \__McTokId::T_PLUS_EQUAL && $a <= \__McTokId::T_COALESCE_EQUAL) || $a === \__McTokId::T_POW_EQUAL;
        }
        // isValidVariableAssignment
        if ($k === \__McTokId::T_LIST || $k === \__McTokId::T_PRINT || $k === \__McTokId::T_ECHO || $c === CT::DESTRUCTURING_BRACKET_OPEN) {
            $end = -1;
            for ($x = $next + 1; $x < $s->n; $x++) {
                if ($c === CT::DESTRUCTURING_BRACKET_OPEN ? $s->ct[$x] === CT::DESTRUCTURING_BRACKET_CLOSE : ($s->kind[$x] === 41 && $s->ct[$x] === 0)) {
                    $end = $x;
                    break;
                }
            }
            for ($x = $next + 1; $x < $end; $x++) {
                if ($s->kind[$x] === \__McTokId::T_VARIABLE && \str_contains($doc, $s->text($x))) {
                    return true;
                }
            }
            return false;
        }
        return $c === CT::USE_TRAIT;
    }
}
