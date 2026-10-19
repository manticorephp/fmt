<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\SingleSpaceAroundConstructFixer (priority 36), every option.
 * Three upstream passes, each over the whole file BACKWARDS, in this order: `yield from` text, "preceded by a single space",
 * "followed by a single space". The followed pass looks ahead (multi-line return / const / extends list) at gaps that the
 * same pass already rewrote further down, so the rule is a file rule walking in the upstream order; it only rewrites gaps
 * and one token text. An upstream token is a real token or a trivia piece of a gap: `comment` / `php_doc` constructs are
 * pieces, the blank after a construct is the first piece of the next gap (or the lone gap), a missing blank is an insertion
 * into the gap.
 */
final class SingleSpaceAroundConstruct extends GapRule
{
    /** @var array<int, bool> effective id (custom kind when there is one) => construct followed by a single space */
    private array $followed = [];
    /** @var array<int, bool> */
    private array $preceded = [];
    private bool $yieldFrom;
    private bool $comment = false;
    private bool $phpDoc = false;
    /** @var array<int, bool> */
    private array $skipNext = [];
    /** @var array<int, bool> */
    private array $staticNext = [];

    /**
     * @param string[] $followed option names (constructs_followed_by_a_single_space)
     * @param string[] $preceded constructs_preceded_by_a_single_space
     */
    public function __construct(array $followed, array $preceded, bool $yieldFrom)
    {
        $map = [
            'abstract' => \__McTokId::T_ABSTRACT, 'as' => \__McTokId::T_AS, 'attribute' => CT::ATTRIBUTE_CLOSE, 'break' => \__McTokId::T_BREAK,
            'case' => \__McTokId::T_CASE, 'catch' => \__McTokId::T_CATCH, 'class' => \__McTokId::T_CLASS, 'clone' => \__McTokId::T_CLONE,
            'const' => \__McTokId::T_CONST, 'const_import' => CT::CONST_IMPORT, 'continue' => \__McTokId::T_CONTINUE, 'do' => \__McTokId::T_DO,
            'echo' => \__McTokId::T_ECHO, 'else' => \__McTokId::T_ELSE, 'elseif' => \__McTokId::T_ELSEIF, 'enum' => \__McTokId::T_ENUM,
            'extends' => \__McTokId::T_EXTENDS, 'final' => \__McTokId::T_FINAL, 'finally' => \__McTokId::T_FINALLY, 'for' => \__McTokId::T_FOR,
            'foreach' => \__McTokId::T_FOREACH, 'function' => \__McTokId::T_FUNCTION, 'function_import' => CT::FUNCTION_IMPORT,
            'global' => \__McTokId::T_GLOBAL, 'goto' => \__McTokId::T_GOTO, 'if' => \__McTokId::T_IF, 'implements' => \__McTokId::T_IMPLEMENTS,
            'include' => \__McTokId::T_INCLUDE, 'include_once' => \__McTokId::T_INCLUDE_ONCE, 'instanceof' => \__McTokId::T_INSTANCEOF,
            'insteadof' => \__McTokId::T_INSTEADOF, 'interface' => \__McTokId::T_INTERFACE, 'match' => \__McTokId::T_MATCH,
            'named_argument' => CT::NAMED_ARGUMENT_COLON, 'namespace' => \__McTokId::T_NAMESPACE, 'new' => \__McTokId::T_NEW,
            'open_tag_with_echo' => \__McTokId::T_OPEN_TAG_WITH_ECHO, 'php_open' => \__McTokId::T_OPEN_TAG, 'print' => \__McTokId::T_PRINT,
            'private' => \__McTokId::T_PRIVATE, 'private_set' => \__McTokId::T_PRIVATE_SET, 'protected' => \__McTokId::T_PROTECTED,
            'protected_set' => \__McTokId::T_PROTECTED_SET, 'public' => \__McTokId::T_PUBLIC, 'public_set' => \__McTokId::T_PUBLIC_SET,
            'readonly' => \__McTokId::T_READONLY, 'require' => \__McTokId::T_REQUIRE, 'require_once' => \__McTokId::T_REQUIRE_ONCE,
            'return' => \__McTokId::T_RETURN, 'static' => \__McTokId::T_STATIC, 'switch' => \__McTokId::T_SWITCH, 'throw' => \__McTokId::T_THROW,
            'trait' => \__McTokId::T_TRAIT, 'try' => \__McTokId::T_TRY, 'type_colon' => CT::TYPE_COLON, 'use' => \__McTokId::T_USE,
            'use_lambda' => CT::USE_LAMBDA, 'use_trait' => CT::USE_TRAIT, 'var' => \__McTokId::T_VAR, 'while' => \__McTokId::T_WHILE,
            'yield' => \__McTokId::T_YIELD, 'yield_from' => \__McTokId::T_YIELD_FROM,
        ];
        foreach ($followed as $name) {
            if ($name === 'comment') {
                $this->comment = true;
            } elseif ($name === 'php_doc') {
                $this->phpDoc = true;
            } else {
                $this->followed[$map[$name]] = true;
            }
            if ($name === 'public') {
                $this->followed[CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC] = true;
            } elseif ($name === 'protected') {
                $this->followed[CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED] = true;
            } elseif ($name === 'private') {
                $this->followed[CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE] = true;
            }
        }
        $pm = ['as' => \__McTokId::T_AS, 'else' => \__McTokId::T_ELSE, 'elseif' => \__McTokId::T_ELSEIF, 'use_lambda' => CT::USE_LAMBDA];
        foreach ($preceded as $name) {
            $this->preceded[$pm[$name]] = true;
        }
        $this->yieldFrom = $yieldFrom;
        foreach ([44, 58, 59, 41, CT::ARRAY_BRACKET_CLOSE, CT::DESTRUCTURING_BRACKET_CLOSE] as $k) {
            $this->skipNext[$k] = true;
        }
        foreach ([\__McTokId::T_FN, \__McTokId::T_FUNCTION, \__McTokId::T_NS_SEPARATOR, \__McTokId::T_STRING, \__McTokId::T_VARIABLE,
            CT::ARRAY_TYPEHINT, CT::NULLABLE_TYPE] as $k) {
            $this->staticNext[$k] = true;
        }
    }

    public function name(): string
    {
        return 'single_space_around_construct';
    }

    public function priority(): int
    {
        return 36;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_TEXT | self::F_NB;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_TEXT | self::F_NB;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        if ($this->yieldFrom || $this->preceded !== []) {
            for ($i = $n - 1; $i > 0; $i--) {
                $k = $s->kind[$i];
                if ($this->yieldFrom && $k === \__McTokId::T_YIELD_FROM && $s->ct[$i] === 0) {
                    $t = $s->text($i);
                    if (\strtolower($t) !== 'yield from') {
                        $s->setText($i, (string) \preg_replace('/\s+/', ' ', $t));
                    }
                } elseif ($this->preceded !== [] && isset($this->preceded[$s->ct[$i] !== 0 ? $s->ct[$i] : $k])) {
                    $this->fixBefore($s, $i);
                }
            }
        }
        if ($this->followed === [] && !$this->comment && !$this->phpDoc) {
            return;
        }
        $commentPass = $this->comment || $this->phpDoc;
        for ($i = $n - 1; $i >= 0; $i--) {
            $c = $s->ct[$i];
            $isC = isset($this->followed[$c !== 0 ? $c : $s->kind[$i]]);
            if ($isC || ($commentPass && Gap::hasComment($s->gapBefore[$i + 1] ?? ''))) {
                $this->fixAfter($s, $i, $isC);
            }
        }
    }

    private function fixBefore(Source $s, int $i): void
    {
        $g = $s->gapBefore[$i];
        // ensureWhitespaceAtIndex($i - 1, 1, ' ') on an open tag: the tag absorbs the space (its text becomes rtrim + ' '), nothing is inserted
        // and a whitespace token between the tag and the construct is cleared
        if ($s->kind[$i - 1] === \__McTokId::T_OPEN_TAG && $s->ct[$i - 1] === 0 && ($g === '' || !Gap::hasComment($g))) {
            $s->setText($i - 1, \rtrim($s->text($i - 1)) . ' ');
            $s->setGap($i, '');
            return;
        }
        if ($g === '') {
            $s->setGap($i, ' ');
            return;
        }
        if (!Gap::hasComment($g)) {
            if ($g !== ' ') {
                $s->setGap($i, ' ');
            }
            return;
        }
        $p = Gap::pieces($g);
        $m = \count($p);
        $z = $p[$m - 1];
        $space = Gap::isSpace($z);
        $prev = $space ? ($m > 1 ? $p[$m - 2] : null) : $z;
        if ($prev !== null && ($prev[0] === '#' || \substr($prev, 0, 2) === '//')) {
            return; // a full-line comment right before
        }
        if ($space) {
            $p[$m - 1] = ' ';
        } else {
            $p[] = ' ';
        }
        $s->setGap($i, \implode('', $p));
    }

    private static function isDoc(string $c): bool
    {
        if (\strlen($c) < 4 || \substr($c, 0, 3) !== '/**') {
            return false;
        }
        $x = \ord($c[3]);
        return $x === 32 || $x === 9 || $x === 10 || $x === 13;
    }

    private function eff(Source $s, int $i): int
    {
        return $s->ct[$i] !== 0 ? $s->ct[$i] : $s->kind[$i];
    }

    /** the construct at real token $i (when $isC) and the comment constructs among the pieces of the gap after it, last piece first */
    private function fixAfter(Source $s, int $i, bool $isC): void
    {
        $n = $s->n;
        $g = $i + 1;
        $gap = $s->gapBefore[$g];
        if ($g === $n && $gap === '') {
            return; // nothing follows the last token
        }
        $hasComment = $gap !== '' && Gap::hasComment($gap);
        $p = $gap === '' ? [] : ($hasComment ? Gap::pieces($gap) : [$gap]);
        $before = $p;
        $next = $g < $n ? $this->eff($s, $g) : -1;
        $m = \count($p);
        if ($hasComment && ($this->comment || $this->phpDoc)) {
            for ($k = $m - 1; $k >= 0; $k--) {
                if (Gap::isSpace($p[$k]) || ($k === $m - 1 && $g === $n)) {
                    continue; // the last token of the file is never a construct
                }
                $doc = self::isDoc($p[$k]);
                if (($doc && $this->phpDoc) || (!$doc && $this->comment)) {
                    $this->follow($s, $p, $k + 1, $next, -1);
                }
            }
        }
        if ($isC) {
            $this->follow($s, $p, 0, $next, $i);
        }
        if ($p !== $before) {
            $s->setGap($g, \implode('', $p));
        }
    }

    /**
     * Upstream's per-construct body: the blank is (or is inserted as) piece $at of $p. $i is the real construct token, -1 for a comment piece.
     * @param string[] $p
     */
    private function follow(Source $s, array &$p, int $at, int $next, int $i): void
    {
        $has = isset($p[$at]);
        $ws = $has && Gap::isSpace($p[$at]);
        if (!$has && isset($this->skipNext[$next])) {
            return;
        }
        $id = $i >= 0 ? $this->eff($s, $i) : 0;
        if ($i >= 0) {
            if ($id === \__McTokId::T_STATIC && !isset($this->staticNext[$next])) {
                return;
            }
            if ($id === \__McTokId::T_OPEN_TAG) {
                if ($ws && \strpos($p[$at], "\n") === false && \strpos($s->text($i), "\n") === false) {
                    \array_splice($p, $at, 1);
                }
                return;
            }
            if ($id === \__McTokId::T_CLASS && $next === 40) {
                return;
            }
            if (($id === \__McTokId::T_EXTENDS || $id === \__McTokId::T_IMPLEMENTS) && $this->multilineAncestors($s, $i)) {
                return;
            }
            if ($id === \__McTokId::T_RETURN && $this->multilineReturn($s, $i)) {
                return;
            }
            if ($id === \__McTokId::T_CONST && $this->multilineConst($s, $i)) {
                return;
            }
        }
        $nl = $ws && \strpos($p[$at], "\n") !== false;
        if ($nl && ($i < 0 || $id === CT::ATTRIBUTE_CLOSE)) {
            return; // after a comment / attribute: a line break stays
        }
        if ($nl) {
            if (isset($p[$at + 1])) {
                $nn = $p[$at + 1];
                if (!Gap::isSpace($nn) && (\substr($nn, 0, 2) === '#[' || self::isDoc($nn))) {
                    return;
                }
            } elseif ($next === \__McTokId::T_ATTRIBUTE) {
                return;
            }
        }
        if ($ws) {
            $p[$at] = ' ';
        } else {
            \array_splice($p, $at, 0, [' ']);
        }
    }

    private function multilineAncestors(Source $s, int $i): bool
    {
        $more = false;
        $n = $s->n;
        for ($j = $i + 1; $j < $n; $j++) {
            if ($more && \strpos($s->gapBefore[$j], "\n") !== false) {
                return true;
            }
            $k = $s->kind[$j];
            if ($s->ct[$j] === 0) {
                if ($k === 44) {
                    $more = true;
                } elseif ($k === 123) {
                    return false;
                }
            }
            if ($more && \strpos($s->text($j), "\n") !== false) {
                return true;
            }
        }
        return false;
    }

    private function multilineReturn(Source $s, int $i): bool
    {
        $n = $s->n;
        $g = $i + 1;
        if ($g >= $n) {
            return false;
        }
        $gap = $s->gapBefore[$g];
        if ($gap === '') {
            return false;
        }
        $p = Gap::hasComment($gap) ? Gap::pieces($gap) : [$gap];
        if (!Gap::isSpace($p[0]) || \strpos($p[0], "\n") === false) {
            return false;
        }
        for ($k = 1, $m = \count($p) - ($g === $n ? 1 : 0); $k < $m; $k++) {
            if (\strpos($p[$k], "\n") !== false) {
                return true;
            }
        }
        $last = $s->gapBefore[$n] === '' ? $n - 1 : $n; // upstream stops before its last token
        $nest = 0;
        for ($j = $g; $j < $n; $j++) {
            if ($j > $g && \strpos($s->gapBefore[$j], "\n") !== false) {
                return true;
            }
            if ($j >= $last) {
                return false;
            }
            if (\strpos($s->text($j), "\n") !== false) {
                return true;
            }
            $k = $s->kind[$j];
            if ($s->ct[$j] === 0 && $k === 123) {
                $nest++;
            } elseif ($s->ct[$j] === 0 && $k === 125) {
                $nest--;
            } elseif ($nest === 0 && (($k === 59 && $s->ct[$j] === 0) || $k === \__McTokId::T_CLOSE_TAG)) {
                return false;
            }
        }
        if ($last === $n) {
            $q = $s->gapBefore[$n];
            $pp = Gap::hasComment($q) ? Gap::pieces($q) : [$q];
            for ($k = 0, $m = \count($pp) - 1; $k < $m; $k++) {
                if (\strpos($pp[$k], "\n") !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    private function multilineConst(Source $s, int $i): bool
    {
        $n = $s->n;
        $multi = false;
        $more = false;
        $j = $i;
        while ($j + 1 < $n) {
            $t = $j + 1;
            if (!$multi && \strpos($s->gapBefore[$t], "\n") !== false) {
                $multi = true;
            }
            if (!$multi && \strpos($s->text($t), "\n") !== false) {
                $multi = true;
            }
            if ($s->kind[$t] === 44 && $s->ct[$t] === 0) {
                $more = true;
            }
            $j = $s->pair[$t] > $t ? $s->pair[$t] : $t;
            if (($s->kind[$j] === 59 && $s->ct[$j] === 0) || $s->kind[$j] === \__McTokId::T_CLOSE_TAG) {
                break;
            }
        }
        return $more && $multi;
    }
}
