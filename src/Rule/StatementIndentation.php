<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\StatementIndentationFixer (priority -3), both values of
 * stick_comment_to_next_continuous_control_statement (bracesFixerCompatibility is off, as in every preset).
 *
 * Upstream walks every token, trivia included, keeping a stack of scopes (block / block signature / statement) whose end
 * is a token INDEX, and rewrites each whitespace token holding a line break. The port walks the same sequence: gap t's
 * trivia pieces, then token t. A position is `t << 20 | k` for piece k of gap t and `t << 20 | 0xFFFFF` for token t, so
 * every index comparison of upstream is an int comparison here, trivia included (a statement can end on a comment).
 *
 * Not mirrored: when the token right after an open tag has no whitespace before it and gets one inserted, upstream's
 * indices shift by one for the rest of the file (scope ends then point one token early). Here they do not.
 */
final class StatementIndentation extends GapRule
{
    private const SH = 20;
    private const TOKMARK = 0xFFFFF;
    private const BLOCK = 0;
    private const SIG = 1;
    private const STMT = 2;

    private Source $s;
    private int $n = 0;
    /** @var array<int, string[]> trivia pieces per gap, filled on demand (the current gap: its pieces as rewritten so far) */
    private array $pc = [];

    /** @param bool $compat upstream's `bracesFixerCompatibility` (the `braces` fixer runs statement_indentation this way) */
    public function __construct(private string $lineEnding, private bool $stick, private string $indent = "    ", private bool $compat = false)
    {
    }

    public function name(): string
    {
        return 'statement_indentation';
    }

    public function priority(): int
    {
        return -3;
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
        return self::F_GAP;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    private static function tok(int $t): int
    {
        return ($t << self::SH) | self::TOKMARK;
    }

    /** @return string[] */
    private function pcs(int $g): array
    {
        if (($this->s->gapFlags[$g] & Gap::G_COMMENT) === 0 && !isset($this->pc[$g]) && !isset($this->s->wsSplit[$g])) {
            $gap = $this->s->gapBefore[$g];
            return $gap === '' ? [] : [$gap];
        }
        if (isset($this->pc[$g])) {
            return $this->pc[$g];
        }
        $p = Gap::pieces($this->s->gapBefore[$g]);
        if (isset($this->s->wsSplit[$g])) {
            $p = Gap::splitPieces($p, $this->s->wsSplit[$g]); // upstream's two adjacent whitespace tokens
        }
        $this->pc[$g] = $p;
        return $p;
    }

    /** Number of trivia pieces of gap $g (no allocation for a comment-free gap). */
    private function pcount(int $g): int
    {
        if (($this->s->gapFlags[$g] & Gap::G_COMMENT) === 0 && !isset($this->pc[$g]) && !isset($this->s->wsSplit[$g])) {
            return $this->s->gapBefore[$g] === '' ? 0 : 1;
        }
        return \count($this->pcs($g));
    }

    /** The item after position $p (upstream index + 1). */
    private function nextItem(int $p): int
    {
        $t = $p >> self::SH;
        $k = $p & self::TOKMARK;
        if ($k === self::TOKMARK) {
            if ($t + 1 <= $this->n && $this->pcount($t + 1) > 0) {
                return ($t + 1) << self::SH;
            }
            return self::tok($t + 1);
        }
        if ($k + 1 < $this->pcount($t)) {
            return $p + 1;
        }
        return self::tok($t);
    }

    /** Tokens::getPrevNonWhitespace of token $t: the last comment piece of gap $t, else token $t - 1. */
    private function prevNonWs(int $t): int
    {
        $s = $this->s;
        if (($s->gapFlags[$t] & Gap::G_COMMENT) !== 0) {
            $p = $this->pcs($t);
            for ($k = \count($p) - 1; $k >= 0; $k--) {
                if ($p[$k] !== '' && !Gap::isSpace($p[$k])) {
                    return ($t << self::SH) | $k;
                }
            }
        }
        return self::tok($t - 1);
    }

    /** getPrevMeaningfulToken of a position. */
    private static function prevMeaningful(int $p): int
    {
        $t = $p >> self::SH;
        return self::tok($t - 1);
    }

    private static function isDoc(string $c): bool
    {
        return \strlen($c) > 3 && \str_starts_with($c, '/**') && \strpos(" \t\r\n", $c[3]) !== false;
    }

    // scope stack: flags (type | INCL | IND), end position, initial indent, new indent (statement scopes)
    private const INCL = 4;
    private const IND = 8;
    /** braces compatibility: the parentheses of `function name(` keep the indentation they have */
    private const SKIP = 16;
    private ?FlatTokens $flat = null;
    /** @var int[] */
    private array $sflag = [];
    /** @var int[] */
    private array $send = [];
    /** @var string[] */
    private array $sinit = [];
    /** @var string[] */
    private array $snew = [];
    private int $sp = 0;
    private string $lastIndent = '';
    private string $pli = '';
    private string $pln = '';
    private int $endIndex = 0;
    private int $lastItem = 0;
    /** @var array<int, bool> */
    private array $noBraces = [];
    private int $nbCount = 0;

    private function push(int $flag, int $end, string $init, string $new): void
    {
        $sp = $this->sp;
        $this->sflag[$sp] = $flag;
        $this->send[$sp] = $end;
        $this->sinit[$sp] = $init;
        $this->snew[$sp] = $new;
        $this->sp = $sp + 1;
    }

    /** A no-brace control structure body starts at $pos: an indented block up to the end of its statement. */
    private function noBraceBlock(int $pos): void
    {
        if ($this->nbCount > 0 && isset($this->noBraces[$pos])) {
            $this->endIndex = $this->nextItem($this->statementEnd($pos, $this->lastItem));
            $this->push(self::BLOCK | self::INCL | self::IND, $this->endIndex, $this->lastIndent, '');
        }
    }

    /** Pops the scopes ending at or before $pos; false when none is left (upstream stops there). */
    private function pop(int $pos): bool
    {
        while ($pos >= $this->send[$this->sp - 1]) {
            $this->sp--;
            if ($this->sp === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * The rewrite of one whitespace token holding a line break (`$content`, with the open tag's whitespace in front when
     * it follows one); $first = the first non-whitespace item after it. Returns the new content.
     */
    private function fixWs(string $content, int $first): string
    {
        $cur = $this->sp - 1;
        $f = $this->sflag[$cur];
        if (($f & 3) !== self::STMT) {
            $indent = false;
            if (($f & self::IND) !== 0) {
                $this->endIndex = $this->send[$cur];
                if (($f & self::INCL) === 0) {
                    $this->endIndex = $this->nextItem($this->endIndex);
                }
                $indent = $this->indentAt($first, $this->endIndex);
            }
            $this->pli = Analyzer::extractIndent($content);
            $content = self::replaceTrailing($content, ($f & self::SKIP) !== 0 ? $this->pli : ($indent ? $this->sinit[$cur] . $this->indent : $this->sinit[$cur]));
            $this->pln = Analyzer::extractIndent($content);
            $this->lastIndent = $this->pln;
            return $content;
        }
        $content = self::stmtReplace($content, $this->sinit[$cur], $this->snew[$cur]);
        $this->lastIndent = Analyzer::extractIndent($content);
        return $content;
    }

    /**
     * fixWs for a comment-free whitespace gap ([ \t\r\n] only) with a line break, followed by token $t: the blanks after
     * the last break are the indent, so no regex and no allocation unless the indent changes.
     */
    private function fixPlainWs(Source $s, int $t): void
    {
        $g = $s->gapBefore[$t];
        $len = \strlen($g);
        $p = \strrpos($g, "\n");
        if (($s->gapFlags[$t] & Gap::G_CR) !== 0) {
            $r = \strrpos($g, "\r");
            if ($p === false || ($r !== false && $r > $p)) {
                $p = $r;
            }
        }
        $p = (int) $p;
        $tl = $len - $p - 1;
        $cur = $this->sp - 1;
        $f = $this->sflag[$cur];
        if (($f & 3) !== self::STMT) {
            $indent = false;
            if (($f & self::IND) !== 0) {
                $this->endIndex = $this->send[$cur];
                if (($f & self::INCL) === 0) {
                    $this->endIndex = $this->nextItem($this->endIndex);
                }
                $indent = $t < $this->n && self::tok($t) < $this->endIndex;
            }
            $want = $indent ? $this->sinit[$cur] . $this->indent : $this->sinit[$cur];
            if (($f & self::SKIP) !== 0) {
                $want = $tl === 0 ? '' : \substr($g, $p + 1);
            }
            if ($tl === 0 && $len >= 2 && \ord($g[$len - 1]) === 10 && ($g[$len - 2] === ' ' || $g[$len - 2] === "\t")) {
                // global `(\R+)\h*$`, `$` also before the final "\n": the blank line's blanks AND the empty last line
                // both take the indent
                $b = $len - 1;
                while ($b > 0 && ($g[$b - 1] === ' ' || $g[$b - 1] === "\t")) {
                    $b--;
                }
                if ($b > 0 && ($g[$b - 1] === "\n" || $g[$b - 1] === "\r")) {
                    $this->pli = '';
                    $this->pln = $want;
                    $this->lastIndent = $want;
                    $new = \substr($g, 0, $b) . $want . "\n" . $want;
                    if ($new !== $g) {
                        $s->setGap($t, $new);
                    }
                    return;
                }
            }
            $this->pli = $tl === 0 ? '' : \substr($g, $p + 1);
            $this->pln = $want;
            $this->lastIndent = $want;
            if ($want !== $this->pli) {
                $s->setGap($t, \substr($g, 0, $p + 1) . $want);
            }
            return;
        }
        $init = $this->sinit[$cur];
        $il = \strlen($init);
        if ($tl >= $il && ($il === 0 || \substr_compare($g, $init, $p + 1, $il) === 0)) {
            $new = $this->snew[$cur];
            if ($new !== $init) {
                $g = \substr($g, 0, $p + 1) . $new . \substr($g, $p + 1 + $il);
                $s->setGap($t, $g);
                $tl = $tl - $il + \strlen($new);
                $len = \strlen($g);
            }
        }
        $this->lastIndent = $tl === 0 ? '' : \substr($g, $len - $tl);
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $this->n = $s->n;
        $this->pc = [];
        $n = $s->n;
        if ($n === 0) {
            return;
        }
        $op = \__McTokId::T_OPEN_TAG;
        if ($this->compat) {
            $this->flat = new FlatTokens();
            $this->flat->load($s, 0, $n - 1, true);
        }

        // root scope: up to the last item, minus a trailing whitespace token
        $rootEnd = self::tok($n - 1);
        $tc = $this->pcount($n);
        if ($tc > 0) {
            $tp = $this->pcs($n);
            $rootEnd = ($n << self::SH) | ($tc - 1);
            if (Gap::isSpace($tp[$tc - 1])) {
                $rootEnd = $tc >= 2 ? ($n << self::SH) | ($tc - 2) : self::tok($n - 1);
            }
        }
        $this->lastItem = $tc > 0 ? ($n << self::SH) | ($tc - 1) : self::tok($n - 1);
        $beyond = self::tok($n);

        $this->lastIndent = Analyzer::extractIndent($s->text(0));
        if ($this->compat && $s->kind[0] === $op && Gap::pieceHasR($s->text(0)) && $n > 1 && $this->pcount(1) > 0 && Gap::isSpace($this->pcs(1)[0])
            && \preg_match('/\h+$/D', $this->pcs(1)[0], $mm) === 1) {
            $this->lastIndent = \preg_replace('/.*?(\h+)$/sD', '$1', $this->pcs(1)[0]); // getLineIndentationWithBracesCompatibility($tokens, 0, ..)
        }
        $this->sflag = [];
        $this->send = [];
        $this->sinit = [];
        $this->snew = [];
        $this->sp = 0;
        $this->push(self::BLOCK | self::INCL, $rootEnd, $this->lastIndent, '');
        $this->pli = '';
        $this->pln = '';
        $this->noBraces = [];
        $this->nbCount = 0;
        /** @var array<int, int> $altStarts */
        $altStarts = [];
        /** @var array<int, int> $caseStarts */
        $caseStarts = [];
        $this->endIndex = 0;

        for ($t = 0; $t <= $n; $t++) {
            // ---- the trivia pieces of gap t
            if ($t > 0 && $s->gapBefore[$t] !== '') {
                $fl = $s->gapFlags[$t];
                if (($fl & Gap::G_COMMENT) === 0 && $s->kind[$t - 1] !== $op) {
                    $this->noBraceBlock($t << self::SH);
                    if (($fl & (Gap::G_NL | Gap::G_CR)) !== 0) {
                        $this->fixPlainWs($s, $t);
                    }
                } elseif (!$this->gapPieces($s, $t)) {
                    return;
                }
            }
            if ($t === $n) {
                break;
            }

            // ---- token t
            $pos = self::tok($t);
            $k = $s->kind[$t];
            $c = $s->ct[$t];
            $this->noBraceBlock($pos);
            $plainParen = $k === 40 && $c === 0;
            if (($k === 123 && $c === 0) || $c === CT::DESTRUCTURING_BRACKET_OPEN || $c === CT::USE_TRAIT || $c === CT::GROUP_IMPORT_BRACE_OPEN
                || $c === CT::PROPERTY_HOOK_BRACE_OPEN || $k === \__McTokId::T_ATTRIBUTE
                || ($plainParen && !($s->kind[$t - 1] === \__McTokId::T_ARRAY && $s->ct[$t - 1] === 0))
                || ($k === 58 && (isset($altStarts[$pos]) || isset($caseStarts[$pos])))) {
                $incl = true;
                if ($c === CT::USE_TRAIT) {
                    $this->endIndex = $this->nextTok($t, 59, 0);
                } elseif ($k === 58 && $c === 0) {
                    if (isset($caseStarts[$pos])) {
                        [$this->endIndex, $incl] = $this->caseBlockEnd($t);
                    } elseif ($this->belongsToAlt($t)) {
                        $this->endIndex = self::tok($this->altBlockEnd($altStarts[$pos] ?? $t));
                    }
                } elseif ($c === CT::DESTRUCTURING_BRACKET_OPEN) {
                    $this->endIndex = $this->nextTok($t, -1, CT::DESTRUCTURING_BRACKET_CLOSE);
                } elseif ($c === CT::GROUP_IMPORT_BRACE_OPEN) {
                    $this->endIndex = $this->nextTok($t, -1, CT::GROUP_IMPORT_BRACE_CLOSE);
                } elseif ($c === CT::PROPERTY_HOOK_BRACE_OPEN) {
                    $this->endIndex = $this->nextTok($t, -1, CT::PROPERTY_HOOK_BRACE_CLOSE);
                } else {
                    $this->endIndex = self::tok($s->pair[$t]);
                }
                $cur = $this->sp - 1;
                $init = ($this->sflag[$cur] & 3) === self::SIG ? $this->sinit[$cur] : $this->lastIndent;
                $skip = $this->compat && $t >= 2 && ($s->kind[$t - 2] === \__McTokId::T_FUNCTION || $s->kind[$t - 2] === \__McTokId::T_FN) ? self::SKIP : 0;
                $this->push(self::BLOCK | ($incl ? self::INCL : 0) | self::IND | $skip, $this->endIndex, $init, '');
                if (!$this->pop($pos)) {
                    return;
                }
                continue;
            }
            if ($c === CT::ARRAY_BRACKET_OPEN || ($plainParen && $s->kind[$t - 1] === \__McTokId::T_ARRAY && $s->ct[$t - 1] === 0)) {
                $this->push(self::STMT | self::INCL, self::tok($s->pair[$t]), $this->pli, $this->pln);
                continue;
            }
            $isProp = $this->isPropertyStart($t);
            if ($isProp || ($c === 0 && self::isSigFirst($k))) {
                $this->endIndex = $this->signatureEnd($t, $altStarts, $caseStarts, $beyond);
                $ind = $isProp || ($c === 0 && ($k === \__McTokId::T_EXTENDS || $k === \__McTokId::T_IMPLEMENTS || $k === \__McTokId::T_CONST || $k === \__McTokId::T_CASE));
                $this->push(self::SIG | self::INCL | ($ind ? self::IND : 0), $this->endIndex, $this->lastIndent, '');
                continue;
            }
            if ($k === \__McTokId::T_FUNCTION && $c === 0) {
                $this->endIndex = $beyond;
                for ($j = $t + 1; $j < $n; $j++) {
                    $kj = $s->kind[$j];
                    if ($kj === 40 && $s->ct[$j] === 0) {
                        $j = $s->pair[$j];
                        continue;
                    }
                    if (($kj === 123 && $s->ct[$j] === 0) || $kj === 59) {
                        $this->endIndex = self::tok($j);
                        break;
                    }
                }
                $this->push(self::SIG | self::INCL, $this->endIndex, $this->lastIndent, '');
                continue;
            }
            if ($t > 0 && $s->gapBefore[$t] === '' && $s->kind[$t - 1] === $op) {
                // the token right after an open tag: the tag's own line break is the whitespace
                $prefix = Analyzer::tagSpace($s->text($t - 1));
                if (!Gap::pieceHasR($prefix)) {
                    continue;
                }
                $content = $this->fixWs($prefix, $this->firstNonWsAfter($pos));
                if (\str_starts_with($content, $prefix)) {
                    $content = \substr($content, \strlen($prefix));
                }
                if ($content !== '') {
                    $s->setGap($t, $content);
                }
            }
            if ($k === $op) {
                $tx = $s->text($t);
                $nextExists = $t + 1 < $n || $this->pcount($t + 1) > 0;
                if ($nextExists && !($this->pcount($t + 1) > 0 && Gap::isSpace($this->pcs($t + 1)[0])) && Gap::pieceHasR($tx)) {
                    if ($t > 0 && $s->gapBefore[$t] === '' && ($s->kind[$t - 1] === $op || $s->kind[$t - 1] === \__McTokId::T_CLOSE_TAG)) {
                        $tx = Analyzer::tagSpace($s->text($t - 1)) . $tx;
                    }
                    $this->lastIndent = Analyzer::extractIndent($tx);
                }
            } elseif ($k === \__McTokId::T_INLINE_HTML) {
                $tx = $s->text($t);
                if ($t > 0 && $s->gapBefore[$t] === '' && ($s->kind[$t - 1] === $op || $s->kind[$t - 1] === \__McTokId::T_CLOSE_TAG)) {
                    $tx = Analyzer::tagSpace($s->text($t - 1)) . $tx;
                }
                if (Gap::pieceHasR($tx)) {
                    $this->lastIndent = Analyzer::extractIndent($tx);
                }
            }
            if (!$this->pop($pos)) {
                return;
            }
            if ($k === 59 || $k === 44 || ($k === 125 && $c === 0) || $k === $op || $k === \__McTokId::T_CLOSE_TAG || $c === CT::ATTRIBUTE_CLOSE) {
                continue;
            }
            $cur = $this->sp - 1;
            if (($this->sflag[$cur] & 3) === self::BLOCK) {
                $this->endIndex = $this->statementEnd($pos, $this->send[$cur]);
                if ($this->endIndex === $pos) {
                    continue;
                }
                $this->push(self::STMT | self::IND, $this->endIndex, $this->pli, $this->pln);
            }
        }
    }

    /** The trivia pieces of gap $t one by one (a gap with a comment, or the gap right after an open tag); false = stop. */
    private function gapPieces(Source $s, int $t): bool
    {
        $pieces = $this->pcs($t);
        $cnt = \count($pieces);
        $n = $this->n;
        $changed = false;
        $ok = true;
        for ($k = 0; $k < $cnt; $k++) {
            $pos = ($t << self::SH) | $k;
            $piece = $pieces[$k];
            $isWs = Gap::isSpace($piece);
            if ($isWs) {
                $this->noBraceBlock($pos);
            }
            $afterTag = $k === 0 && $s->kind[$t - 1] === \__McTokId::T_OPEN_TAG;
            if ($isWs || $afterTag) {
                $prefix = $afterTag ? Analyzer::tagSpace($s->text($t - 1)) : '';
                $content = $prefix . ($isWs ? $piece : '');
                if (Gap::pieceHasR($content)) {
                    if ($this->compat && $k + 1 < $cnt && !Gap::isSpace($pieces[$k + 1]) && !$this->fixableComment($t, $k + 1)) {
                        continue; // braces compatibility: a comment whose indentation is not to be fixed
                    }
                    $first = $isWs ? ($k + 1 < $cnt ? $pos + 1 : ($t < $n ? self::tok($t) : -1)) : $this->firstNonWsAfter($pos);
                    $content = $this->fixWs($content, $first);
                    if ($prefix !== '' && \str_starts_with($content, $prefix)) {
                        $content = \substr($content, \strlen($prefix));
                    }
                    if ($isWs) {
                        if ($content !== $piece) {
                            $pieces[$k] = $content;
                            $changed = true;
                        }
                    } elseif ($content !== '') {
                        $pieces[$k] = $content . $piece; // ws inserted before the comment (position numbering kept)
                        $changed = true;
                    }
                    if ($isWs && $k + 1 < $cnt) {
                        $c2 = self::reindentComment($pieces[$k + 1], $this->pli, $this->pln);
                        if ($c2 !== $pieces[$k + 1]) {
                            $pieces[$k + 1] = $c2;
                            $changed = true;
                        }
                    }
                }
                if ($isWs) {
                    continue;
                }
            }
            // a comment: scopes ending here close
            if (!$this->pop($pos)) {
                $ok = false;
                break;
            }
        }
        if ($changed) {
            $this->pc[$t] = $pieces;
            $s->setGap($t, \implode('', $pieces));
        }
        return $ok;
    }

    /**
     * isCommentWithFixableIndentation for piece $k of gap $t, on a snapshot of the token list (line breaks are all it reads of the whitespace, and a rewrite keeps them).
     */
    private function fixableComment(int $t, int $k): bool
    {
        $f = $this->flat;
        if ($f === null) {
            return true;
        }
        $base = ($t < $this->n ? $f->pos[$t] : $f->count()) - $this->pcount($t);
        $index = $base + $k;
        if ($index >= $f->count() || $f->k[$index] !== FlatTokens::COMMENT) {
            return false;
        }
        $c = $f->c[$index];
        if (\str_starts_with($c, '/*')) {
            return true;
        }
        if (Preg::match('~^(//|#)(' . \preg_quote($this->indent, '~') . '.*)?$~', $c)) {
            return false;
        }
        $first = $index;
        while (($x = $this->siblingComment($f, $first, false)) >= 0) {
            $first = $x;
        }
        $last = $index;
        while (($x = $this->siblingComment($f, $last, true)) >= 0) {
            $last = $x;
        }
        if ($first === $last) {
            return true;
        }
        for ($i = $first + 1; $i < $last; ++$i) {
            if ($f->k[$i] !== FlatTokens::WS && $f->k[$i] !== FlatTokens::COMMENT) {
                return false;
            }
        }
        return true;
    }

    /** getSiblingContinuousSingleLineComment: the next / previous `//` or `#` comment when only whitespace with at most one line break lies between, else -1 */
    private function siblingComment(FlatTokens $f, int $index, bool $after): int
    {
        $m = $f->count();
        $sib = $index;
        do {
            $sib += $after ? 1 : -1;
            while ($sib >= 0 && $sib < $m && $f->k[$sib] !== FlatTokens::COMMENT) {
                $sib += $after ? 1 : -1;
            }
            if ($sib < 0 || $sib >= $m) {
                return -1;
            }
        } while (\str_starts_with($f->c[$sib], '/*'));
        $newLines = 0;
        $a = \min($sib, $index);
        $b = \max($sib, $index);
        for ($i = $a + 1; $i < $b; ++$i) {
            if ($f->k[$i] === FlatTokens::WS && \preg_match('/\R/', $f->c[$i]) === 1) {
                if ($newLines === 1 || \preg_match('/\R.*\R/', $f->c[$i]) === 1) {
                    return -1;
                }
                ++$newLines;
            }
        }
        return $sib;
    }

    /**
     * Whether the line starting before item $first (the first non-whitespace item after the whitespace) gets one more
     * indent level in an indented block ending at $endIndex: yes inside the block, except that a `//` comment followed only
     * by `}` and then else/elseif belongs to that else/elseif under stick_comment_to_next_continuous_control_statement.
     */
    private function indentAt(int $first, int $endIndex): bool
    {
        if ($first < 0 || $first >= $endIndex) {
            return false;
        }
        $fk = $first & self::TOKMARK;
        if ($fk === self::TOKMARK) {
            return true;
        }
        $s = $this->s;
        $g = $first >> self::SH;
        $c = $this->pcs($g)[$fk];
        if ($c === '' || Gap::isSpace($c) || self::isDoc($c) || $g >= $this->n || $s->kind[$g] !== 125 || $s->ct[$g] !== 0) {
            return true;
        }
        if ($s->kind[$g - 1] === 123 && $s->ct[$g - 1] === 0) {
            return true;
        }
        if ($g + 1 < $this->n && $s->ct[$g + 1] === 0 && ($s->kind[$g + 1] === \__McTokId::T_ELSE || $s->kind[$g + 1] === \__McTokId::T_ELSEIF)) {
            return !$this->stick;
        }
        return true;
    }

    /** First item after $p that is not whitespace (a comment piece or a token), -1 at the end. */
    private function firstNonWsAfter(int $p): int
    {
        $q = $this->nextItem($p);
        while (true) {
            $t = $q >> self::SH;
            $k = $q & self::TOKMARK;
            if ($k === self::TOKMARK) {
                return $t < $this->n ? $q : -1;
            }
            $pc = $this->pcs($t);
            if (!Gap::isSpace($pc[$k])) {
                return $q;
            }
            $q = $this->nextItem($q);
        }
    }

    /** Next token after $t with kind $kind (or, $kind < 0, with custom kind $ct), as a position; beyond the end when none. */
    private function nextTok(int $t, int $kind, int $ct): int
    {
        $s = $this->s;
        for ($j = $t + 1; $j < $this->n; $j++) {
            if ($kind >= 0 ? $s->kind[$j] === $kind : $s->ct[$j] === $ct) {
                return self::tok($j);
            }
        }
        return self::tok($this->n);
    }

    private static function isSigFirst(int $k): bool
    {
        return $k === \__McTokId::T_USE || $k === \__McTokId::T_IF || $k === \__McTokId::T_ELSE || $k === \__McTokId::T_ELSEIF
            || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_WHILE || $k === \__McTokId::T_DO
            || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_CASE || $k === \__McTokId::T_DEFAULT || $k === \__McTokId::T_TRY
            || $k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_EXTENDS
            || $k === \__McTokId::T_IMPLEMENTS || $k === \__McTokId::T_CONST || $k === \__McTokId::T_MATCH || $k === \__McTokId::T_ENUM;
    }

    private static function isPropKw(int $k): bool
    {
        return $k === \__McTokId::T_VAR || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE
            || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_READONLY;
    }

    /** isPropertyStart: the last modifier of a property declaration that has a visibility (or var) keyword. */
    private function isPropertyStart(int $t): bool
    {
        $s = $this->s;
        if ($t + 1 >= $this->n || $s->ct[$t] !== 0 || !self::isPropKw($s->kind[$t])) {
            return false;
        }
        $nk = $s->kind[$t + 1];
        if ($s->ct[$t + 1] === 0 && (self::isPropKw($nk) || $nk === \__McTokId::T_CONST || $nk === \__McTokId::T_FUNCTION)) {
            return false;
        }
        for ($j = $t; $j >= 0 && $s->ct[$j] === 0 && self::isPropKw($s->kind[$j]); $j--) {
            $kj = $s->kind[$j];
            if ($kj === \__McTokId::T_VAR || $kj === \__McTokId::T_PUBLIC || $kj === \__McTokId::T_PROTECTED || $kj === \__McTokId::T_PRIVATE) {
                return true;
            }
        }
        return false;
    }

    /**
     * The block signature scan: up to `{` `;` `=>` `implements` or a `:` (alternative syntax / case label, recorded), jumping
     * over `( )` and `[ ]`. For an if/else/elseif/for/foreach/while/do without braces it records where the body starts.
     * @param array<int, int> $altStarts
     * @param array<int, int> $caseStarts
     */
    private function signatureEnd(int $i, array &$altStarts, array &$caseStarts, int $beyond): int
    {
        $s = $this->s;
        $n = $this->n;
        $k0 = $s->kind[$i];
        $ctl = $s->ct[$i] === 0 && ($k0 === \__McTokId::T_IF || $k0 === \__McTokId::T_ELSE || $k0 === \__McTokId::T_ELSEIF || $k0 === \__McTokId::T_FOR
            || $k0 === \__McTokId::T_FOREACH || $k0 === \__McTokId::T_WHILE || $k0 === \__McTokId::T_DO);
        $isCase = $s->ct[$i] === 0 && ($k0 === \__McTokId::T_CASE || $k0 === \__McTokId::T_DEFAULT);
        $lastWs = -1;
        $closing = -1;
        $tern = 0;
        for ($j = $i + 1; $j < $n; $j++) {
            if ($ctl && $s->gapBefore[$j] !== '') {
                $pc = $this->pcs($j);
                $m = \count($pc);
                for ($q = 0; $q < $m; $q++) {
                    if (Gap::isSpace($pc[$q])) {
                        $lastWs = ($j << self::SH) | $q;
                    }
                }
            }
            $kj = $s->kind[$j];
            $cj = $s->ct[$j];
            if ($kj === 40 && $cj === 0) {
                $closing = $s->pair[$j];
                $j = $closing;
                continue;
            }
            if ($cj === CT::ARRAY_BRACKET_OPEN) {
                $j = $s->pair[$j];
                continue;
            }
            if (($kj === 123 && $cj === 0) || $kj === 59 || $kj === \__McTokId::T_DOUBLE_ARROW || ($kj === \__McTokId::T_IMPLEMENTS && $cj === 0)) {
                return self::tok($j);
            }
            if ($kj === 63 && $cj === 0) {
                $tern++;
                continue;
            }
            if ($kj === 58 && $cj === 0) {
                if ($tern > 0) {
                    $tern--;
                    continue;
                }
                if ($isCase) {
                    $caseStarts[self::tok($j)] = $i;
                } else {
                    $altStarts[self::tok($j)] = $i;
                }
                return self::tok($j);
            }
            if (!$ctl) {
                continue;
            }
            $this->noBraces[$lastWs >= 0 ? $lastWs : self::tok($j)] = true;
            $this->nbCount++;
            return self::tok($closing >= 0 ? $closing : $i);
        }
        return $beyond;
    }

    /** findStatementEndIndex($index, $parentScopeEndIndex) on positions. */
    private function statementEnd(int $p, int $parentEnd): int
    {
        $s = $this->s;
        $n = $this->n;
        $t = $p >> self::SH;
        $if = 0;
        $do = 0;
        for (; $t < $n && self::tok($t) <= $parentEnd; $t++) {
            $k = $s->kind[$t];
            $c = $s->ct[$t];
            if ($c === 0 && $k === \__McTokId::T_IF && !($t > 0 && $s->kind[$t - 1] === \__McTokId::T_ELSE && $s->ct[$t - 1] === 0)) {
                $if++;
                continue;
            }
            if ($c === 0 && $k === \__McTokId::T_DO) {
                $do++;
                continue;
            }
            if ((($k === 40 || $k === 123) && $c === 0) || $c === CT::ARRAY_BRACKET_OPEN) {
                $t = $s->pair[$t];
                $k = $s->kind[$t];
                $c = $s->ct[$t];
            }
            if (!($k === 59 || $k === 44 || ($k === 125 && $c === 0) || $k === \__McTokId::T_CLOSE_TAG)) {
                continue;
            }
            $cont = $t + 1;
            if ($if > 0 && $cont < $n && $s->ct[$cont] === 0 && ($s->kind[$cont] === \__McTokId::T_ELSE || $s->kind[$cont] === \__McTokId::T_ELSEIF)) {
                if ($s->kind[$cont] === \__McTokId::T_ELSE && !($cont + 1 < $n && $s->kind[$cont + 1] === \__McTokId::T_IF && $s->ct[$cont + 1] === 0)) {
                    $if--;
                }
                $t = $cont;
                continue;
            }
            if ($do > 0 && $cont < $n && $s->kind[$cont] === \__McTokId::T_WHILE && $s->ct[$cont] === 0) {
                $do--;
                $t = $cont;
                continue;
            }
            return $this->prevNonWs($t);
        }
        return self::prevMeaningful($parentEnd);
    }

    /** findCaseBlockEnd for the `:` of a case/default label at token $t. @return array{int, bool} */
    private function caseBlockEnd(int $t): array
    {
        $s = $this->s;
        $n = $this->n;
        for ($j = $t; $j < $n; $j++) {
            $k = $s->kind[$j];
            $c = $s->ct[$j];
            if ($k === \__McTokId::T_SWITCH && $c === 0) {
                $brace = $s->pair[$j + 1] + 1;
                if ($s->kind[$brace] === 58 && $s->ct[$brace] === 0) {
                    $j = $this->altBlockEnd($j);
                } else {
                    $j = $s->pair[$brace];
                }
                continue;
            }
            if ($k === 123 && $c === 0) {
                $j = $s->pair[$j];
                continue;
            }
            if (($k === \__McTokId::T_CASE || $k === \__McTokId::T_DEFAULT) && $c === 0) {
                return [self::tok($j), true];
            }
            if (($k === 125 && $c === 0) || $k === \__McTokId::T_ENDSWITCH) {
                return [$this->prevNonWs($j), false];
            }
        }
        return [self::tok($n), true];
    }

    /** AlternativeSyntaxAnalyzer::belongsToAlternativeSyntax for the `:` at token $t. */
    private function belongsToAlt(int $t): bool
    {
        $s = $this->s;
        $p = $t - 1;
        if ($s->kind[$p] === \__McTokId::T_ELSE && $s->ct[$p] === 0) {
            return true;
        }
        if ($s->kind[$p] !== 41 || $s->ct[$p] !== 0) {
            return false;
        }
        $b = $s->pair[$p] - 1;
        if ($b < 0 || $s->ct[$b] !== 0) {
            return false;
        }
        $k = $s->kind[$b];
        return $k === \__McTokId::T_DECLARE || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH
            || $k === \__McTokId::T_IF || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_WHILE;
    }

    /** @return int[] the end kinds of an alternative-syntax block started by $k ([] when $k starts none) */
    private static function altEnds(int $k): array
    {
        if ($k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF) {
            return [\__McTokId::T_ENDIF, \__McTokId::T_ELSE, \__McTokId::T_ELSEIF];
        }
        if ($k === \__McTokId::T_ELSE) {
            return [\__McTokId::T_ENDIF];
        }
        if ($k === \__McTokId::T_FOR) {
            return [\__McTokId::T_ENDFOR];
        }
        if ($k === \__McTokId::T_FOREACH) {
            return [\__McTokId::T_ENDFOREACH];
        }
        if ($k === \__McTokId::T_WHILE) {
            return [\__McTokId::T_ENDWHILE];
        }
        if ($k === \__McTokId::T_SWITCH) {
            return [\__McTokId::T_ENDSWITCH];
        }
        return [];
    }

    private function isAltStart(int $j): bool
    {
        $s = $this->s;
        if ($s->ct[$j] !== 0 || self::altEnds($s->kind[$j]) === []) {
            return false;
        }
        $q = $j + 1;
        if ($q < $this->n && $s->kind[$q] === 40 && $s->ct[$q] === 0) {
            $q = $s->pair[$q] + 1;
        }
        return $q < $this->n && $s->kind[$q] === 58 && $s->ct[$q] === 0;
    }

    /** findAlternativeSyntaxBlockEnd from the keyword at token $i: the token index of the end keyword. */
    private function altBlockEnd(int $i): int
    {
        $s = $this->s;
        $sk = $s->kind[$i];
        $ends = self::altEnds($sk);
        $j = $i;
        while (true) {
            $f = -1;
            for ($q = $j + 1; $q < $this->n; $q++) {
                if ($s->ct[$q] === 0 && ($s->kind[$q] === $sk || \in_array($s->kind[$q], $ends, true))) {
                    $f = $q;
                    break;
                }
            }
            if ($f < 0) {
                return $this->n;
            }
            if (\in_array($s->kind[$f], $ends, true)) {
                return $f;
            }
            $j = $this->isAltStart($f) ? $this->altBlockEnd($f) : $f;
        }
    }

    /** `Preg::replace('/(\R+)\h*$/', '$1' . $ws, $c)` ($ also matches before a final "\n"). */
    private static function replaceTrailing(string $c, string $ws): string
    {
        $len = \strlen($c);
        if ($len > 0 && $c[$len - 1] !== "\n") {
            $a = \strrpos($c, "\n");
            $b = \strrpos($c, "\r");
            $p = $a === false ? ($b === false ? -1 : $b) : ($b === false || $a > $b ? $a : $b);
            if ($p < 0) {
                return $c;
            }
            return \substr($c, 0, $p + 1) . $ws;
        }
        return (string) \preg_replace('/(\R+)\h*$/', '${1}' . $ws, $c);
    }

    /** `Preg::replace('/(\R)' . $init . '(\h*)$/D', '$1' . $new . '$2', $c)` */
    private static function stmtReplace(string $c, string $init, string $new): string
    {
        $a = \strrpos($c, "\n");
        $b = \strrpos($c, "\r");
        $p = $a === false ? ($b === false ? -1 : $b) : ($b === false || $a > $b ? $a : $b);
        if ($p < 0) {
            return $c;
        }
        $tail = \substr($c, $p + 1);
        if ($init !== '' && !\str_starts_with($tail, $init)) {
            return $c;
        }
        if (\strspn($tail, " \t") !== \strlen($tail)) {
            return $c;
        }
        return \substr($c, 0, $p + 1) . $new . \substr($tail, \strlen($init));
    }

    /** `Preg::replace('/(\R)' . preg_quote($from) . '(\h*\S+.*)/', '$1' . $to . '$2', $comment)` */
    private static function reindentComment(string $c, string $from, string $to): string
    {
        if ($from === $to || \strpbrk($c, "\r\n\x0b\x0c\x85") === false) {
            return $c;
        }
        $pat = '(\R)' . \preg_quote($from, '/') . '(\h*\S+.*)';
        $r = \preg_replace('/' . $pat . '/u', '${1}' . $to . '${2}', $c);
        if ($r === null) {
            $r = \preg_replace('/' . $pat . '/', '${1}' . $to . '${2}', $c);
        }
        return (string) $r;
    }
}
