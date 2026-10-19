<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\NoBreakCommentFixer (priority 0), a file rule. It adds, edits and removes a
 * `// no break` COMMENT before a `case` / `default` that a non-empty case falls into: trivia only. Upstream walks the `case` /
 * `default` tokens from the end; each is processed on a FlatTokens window [its colon .. the code token that ends the scan], with
 * upstream's algorithm and its integer indexes, and the trivia is stored back as gaps.
 */
final class NoBreakComment extends GapRule
{
    private FlatTokens $ft;
    private ?Source $s = null;
    private bool $hasEnum = false;
    private string $pattern = '';

    public function __construct(private string $text = 'no break')
    {
        $this->text = \rtrim($text);
        $q = \preg_quote($this->text, '~');
        $this->pattern = '~^((//|#)\s*' . $q . '\s*)|(/\*\*?\s*' . $q . '(\s+.*)*\*/)$~i';
        $this->ft = new FlatTokens();
    }

    public function name(): string
    {
        return 'no_break_comment';
    }

    public function priority(): int
    {
        return 0;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        $n = $s->n;
        $this->hasEnum = false;
        $sw = false;
        for ($j = 0; $j < $n; $j++) {
            if ($s->kind[$j] === \__McTokId::T_SWITCH) {
                $sw = true;
            } elseif ($s->kind[$j] === \__McTokId::T_ENUM) {
                $this->hasEnum = true;
            }
        }
        return $sw;
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $n = $s->n;
        for ($i = $n - 1; $i >= 0; $i--) {
            if ($s->ct[$i] !== 0) {
                continue;
            }
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_DEFAULT) {
                if ($i + 1 < $n && $s->kind[$i + 1] === \__McTokId::T_DOUBLE_ARROW && $s->ct[$i + 1] === 0) {
                    continue; // `default` of a match
                }
            } elseif ($k !== \__McTokId::T_CASE || $this->isEnumCase($s, $i)) {
                continue;
            }
            $colon = -1;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($s->ct[$j] === 0 && ($s->kind[$j] === 58 || $s->kind[$j] === 59)) {
                    $colon = $j;
                    break;
                }
            }
            if ($colon >= 0) {
                $this->fixCase($s, $colon);
            }
        }
        $this->s = null;
    }

    private function isEnumCase(Source $s, int $case): bool
    {
        if (!$this->hasEnum) {
            return false;
        }
        $p = $case;
        while (true) {
            $p--;
            while ($p >= 0 && !($s->ct[$p] === 0 && ($s->kind[$p] === 125 || $s->kind[$p] === \__McTokId::T_ENUM || $s->kind[$p] === \__McTokId::T_SWITCH))) {
                $p--;
            }
            if ($p < 0) {
                return false;
            }
            if ($s->kind[$p] === 125) {
                $p = $s->pair[$p];
                if ($p < 0) {
                    return false;
                }
                continue;
            }
            return $s->kind[$p] === \__McTokId::T_ENUM;
        }
    }

    // ---- Source-space scan: where does the loop of fixCase end ----------------------------------------------------------------

    private static function isStructure(int $k): bool
    {
        return $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_WHILE || $k === \__McTokId::T_IF
            || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_MATCH;
    }

    /** getStructureEnd over the Source: the code token index the scan resumes after */
    private static function structureEnd(Source $s, int $pos): int
    {
        $n = $s->n;
        $k = $s->kind[$pos];
        $p = $pos;
        if (self::isStructure($k)) {
            $o = self::nextOfKind($s, $p, 40);
            if ($o < 0) {
                return $n;
            }
            $p = $s->pair[$o];
        } elseif ($k === \__McTokId::T_CLASS) {
            if ($p + 1 < $n && $s->kind[$p + 1] === 40) {
                $p = $s->pair[$p + 1];
            }
        }
        if ($k === \__McTokId::T_FUNCTION) {
            $p = self::nextOfKind($s, $p, 123);
            if ($p < 0) {
                return $n;
            }
        } else {
            $p++;
        }
        if ($p >= $n || $s->kind[$p] !== 123) {
            $e = self::nextOfKind($s, $p, 59);
            return $e < 0 ? $n : $e;
        }
        $p = $s->pair[$p];
        if ($k === \__McTokId::T_DO) {
            $o = self::nextOfKind($s, $p, 40);
            if ($o < 0) {
                return $n;
            }
            $e = self::nextOfKind($s, $s->pair[$o], 59);
            return $e < 0 ? $n : $e;
        }
        return $p;
    }

    /** Tokens::getNextTokenOfKind($from, ['x']) for a plain one-character token */
    private static function nextOfKind(Source $s, int $from, int $ch): int
    {
        $n = $s->n;
        for ($j = $from + 1; $j < $n; $j++) {
            if ($s->kind[$j] === $ch && $s->ct[$j] === 0) {
                return $j;
            }
        }
        return -1;
    }

    private static function isStructureOrBlock(int $k): bool
    {
        return self::isStructure($k) || $k === \__McTokId::T_ELSE || $k === \__McTokId::T_DO || $k === \__McTokId::T_CLASS;
    }

    private static function findEnd(Source $s, int $colon): int
    {
        $n = $s->n;
        for ($i = $colon + 1; $i < $n; $i++) {
            $k = $s->kind[$i];
            $c = $s->ct[$i];
            if ($c === 0 && self::isStructureOrBlock($k)) {
                $i = self::structureEnd($s, $i);
                continue;
            }
            if ($c === 0 && ($k === \__McTokId::T_BREAK || $k === \__McTokId::T_CONTINUE || $k === \__McTokId::T_RETURN || $k === \__McTokId::T_EXIT
                || $k === \__McTokId::T_GOTO || $k === \__McTokId::T_THROW)) {
                continue;
            }
            if ($c === 0 && ($k === 125 || $k === \__McTokId::T_ENDSWITCH || $k === \__McTokId::T_CASE || $k === \__McTokId::T_DEFAULT)) {
                return $i;
            }
        }
        return -1;
    }

    // ---- the fixer on the window --------------------------------------------------------------------------------------------

    private function fixCase(Source $s, int $colon): void
    {
        $end = self::findEnd($s, $colon);
        if ($end < 0) {
            return;
        }
        $ft = $this->ft;
        $ft->load($s, $colon, $end);
        $size = $ft->count();
        $empty = true;
        $fall = true;
        $commentPos = -1;
        for ($i = 1; $i < $size; $i++) {
            if ($ft->i[$i] >= 0 && $this->isStructId($ft->k[$i])) {
                $empty = false;
                $i = $this->posOf(self::structureEnd($s, $ft->i[$i]), $size);
                continue;
            }
            $id = $ft->k[$i];
            if ($ft->i[$i] >= 0 && ($id === \__McTokId::T_BREAK || $id === \__McTokId::T_CONTINUE || $id === \__McTokId::T_RETURN || $id === \__McTokId::T_EXIT || $id === \__McTokId::T_GOTO)) {
                $fall = false;
                continue;
            }
            if ($ft->i[$i] >= 0 && $id === \__McTokId::T_THROW) {
                $prev = $this->prevMeaningful($i);
                if ($prev === 0 || $ft->isId($prev, 59) || $ft->isId($prev, 123) || $ft->isId($prev, 125) || $ft->isId($prev, \__McTokId::T_OPEN_TAG)) {
                    $fall = false;
                }
                continue;
            }
            if ($ft->i[$i] >= 0 && ($id === 125 || $id === \__McTokId::T_ENDSWITCH)) {
                if ($commentPos >= 0) {
                    $this->removeComment($commentPos);
                }
                break;
            }
            if ($this->isNoBreak($i)) {
                $commentPos = $i;
                continue;
            }
            if ($ft->i[$i] >= 0 && ($id === \__McTokId::T_CASE || $id === \__McTokId::T_DEFAULT)) {
                if (!$empty && $fall) {
                    if ($commentPos >= 0 && $this->prevNonWs($i) !== $commentPos) {
                        $this->removeComment($commentPos);
                        $commentPos = -1;
                    }
                    if ($commentPos < 0) {
                        $this->insertCommentAt($i);
                    } else {
                        $ft->c[$commentPos] = \str_ireplace($this->text, $this->text, $ft->c[$commentPos]);
                        $this->ensureNewLineAt($commentPos);
                    }
                } elseif ($commentPos >= 0) {
                    $this->removeComment($commentPos);
                }
                break;
            }
            if (!($ft->k[$i] === FlatTokens::WS || ($ft->k[$i] === FlatTokens::COMMENT && !$this->isDoc($i)))) {
                $empty = false;
            }
        }
        $ft->store($s);
    }

    private function isStructId(int $id): bool
    {
        return self::isStructureOrBlock($id);
    }

    /** the view position of the code token with Source index $src (the window end when it lies past it) */
    private function posOf(int $src, int $size): int
    {
        for ($q = 0; $q < $size; $q++) {
            if ($this->ft->i[$q] === $src) {
                return $q;
            }
        }
        return $size;
    }

    private function isDoc(int $q): bool
    {
        $c = $this->ft->c[$q];
        return \strlen($c) >= 4 && \substr($c, 0, 3) === '/**' && ($c[3] === ' ' || $c[3] === "\t" || $c[3] === "\n" || $c[3] === "\r");
    }

    private function isNoBreak(int $q): bool
    {
        if ($this->ft->k[$q] !== FlatTokens::COMMENT) {
            return false;
        }
        $r = \preg_match($this->pattern . 'u', $this->ft->c[$q]);
        if ($r === false) {
            $r = \preg_match($this->pattern, $this->ft->c[$q]);
        }
        return $r === 1;
    }

    private function prevMeaningful(int $q): int
    {
        for ($j = $q - 1; $j >= 0; $j--) {
            if ($this->ft->k[$j] !== FlatTokens::WS && $this->ft->k[$j] !== FlatTokens::COMMENT) {
                return $j;
            }
        }
        return -1;
    }

    /** Tokens::getPrevNonWhitespace: whitespace includes the emptied items */
    private function prevNonWs(int $q): int
    {
        for ($j = $q - 1; $j >= 0; $j--) {
            if ($this->ft->k[$j] !== FlatTokens::WS) {
                return $j;
            }
        }
        return -1;
    }

    /** WhitespacesAnalyzer::detectIndent over the window, then over the file before it */
    private function detectIndent(int $q): string
    {
        $ft = $this->ft;
        $s = $this->s;
        $limit = $q;
        while (true) {
            $w = -1;
            for ($j = $limit - 1; $j >= 0; $j--) {
                if ($ft->k[$j] === FlatTokens::WS && $ft->c[$j] !== '') {
                    $w = $j;
                    break;
                }
            }
            if ($w < 0) {
                return $s === null ? '' : Analyzer::detectIndent($s, $ft->i[0], \PHP_INT_MAX);
            }
            $content = $ft->c[$w];
            $nl = \strrpos($content, "\n");
            if ($nl !== false) {
                return \substr($content, $nl + 1);
            }
            $prev = $w - 1;
            if ($prev >= 0 && $ft->i[$prev] >= 0 && $ft->k[$prev] === \__McTokId::T_OPEN_TAG && $s !== null) {
                $tx = $s->text($ft->i[$prev]);
                if ($tx !== '' && $tx[\strlen($tx) - 1] === "\n") {
                    return $content;
                }
            } elseif ($prev >= 0 && $ft->k[$prev] === FlatTokens::COMMENT && !$this->isDoc($prev) && \substr($ft->c[$prev], -1) === "\n") {
                return $content;
            }
            $limit = $w;
        }
    }

    private function ensureNewLineAt(int $pos): int
    {
        $ft = $this->ft;
        $content = "\n" . $this->detectIndent($pos);
        if (!$ft->isWs($pos - 1)) {
            if ($ft->i[$pos - 1] >= 0 && $ft->k[$pos - 1] === \__McTokId::T_OPEN_TAG && $this->s !== null) {
                $this->s->unsupported = 'no_break_comment: a case right behind the open tag (needs the open tag rewritten)';
                return $pos - 1;
            }
            if ($content !== '') {
                $ft->insertAt($pos, [[FlatTokens::WS, $content, -1]]);
                return $pos;
            }
            return $pos - 1;
        }
        if ($pos >= 2 && $ft->i[$pos - 2] >= 0 && $ft->k[$pos - 2] === \__McTokId::T_OPEN_TAG && $this->s !== null && Gap::pieceHasR($this->s->text($ft->i[$pos - 2]))) {
            $content = (string) \preg_replace('/^\R/', '', $content);
        }
        if (!Gap::pieceHasR($ft->c[$pos - 1])) {
            $ft->c[$pos - 1] = $content;
        }
        return $pos - 1;
    }

    private function insertCommentAt(int $casePos): void
    {
        $ft = $this->ft;
        $np = $this->ensureNewLineAt($casePos);
        $nb = \substr_count($ft->content($np), "\n");
        if ($np >= 1 && $ft->i[$np - 1] >= 0 && $ft->k[$np - 1] === \__McTokId::T_OPEN_TAG && $this->s !== null && Gap::pieceHasR($this->s->text($ft->i[$np - 1]))) {
            $nb++;
            if (!Gap::pieceHasR($ft->content($np))) {
                $ft->c[$np] = "\n" . $ft->c[$np];
            }
        }
        if ($nb > 1) {
            \preg_match('/^(.*?)(\R\h*)$/s', $ft->c[$np], $m);
            $indent = $this->detectIndent($np - 1);
            $ft->c[$np] = ($m[1] ?? '') . "\n" . $indent;
            $np++;
            $ft->insertAt($np, [[FlatTokens::WS, $m[2] ?? '', -1]]);
        }
        $ft->insertAt($np, [[FlatTokens::COMMENT, '// ' . $this->text, -1]]);
        $this->ensureNewLineAt($np);
    }

    private function removeComment(int $cp): void
    {
        $ft = $this->ft;
        $pn = $this->prevNonWs($cp);
        if ($pn >= 0 && $ft->i[$pn] >= 0 && $ft->k[$pn] === \__McTokId::T_OPEN_TAG) {
            $wp = $cp + 1;
            $regex = '/^\R\h*/';
        } else {
            $wp = $cp - 1;
            $regex = '/\R\h*$/';
        }
        if ($wp >= 0 && $wp < $ft->count() && $ft->k[$wp] === FlatTokens::WS && $ft->c[$wp] !== '') {
            $ft->c[$wp] = (string) \preg_replace($regex, '', $ft->c[$wp]);
        }
        $this->clearAndMerge($cp);
    }

    /** Tokens::clearTokenAndMergeSurroundingWhitespace */
    private function clearAndMerge(int $q): void
    {
        $ft = $this->ft;
        $count = $ft->count();
        $ft->clearAt($q);
        if ($q === $count - 1) {
            return;
        }
        $next = $ft->nonEmptySibling($q, 1);
        if ($next < 0 || !$ft->isWs($next)) {
            return;
        }
        $prev = $ft->nonEmptySibling($q, -1);
        if ($prev >= 0 && $ft->isWs($prev)) {
            $ft->c[$prev] = $ft->c[$prev] . $ft->c[$next];
        } elseif ($prev >= 0 && $ft->isEmpty($prev + 1)) {
            $ft->c[$prev + 1] = $ft->c[$next];
        }
        $ft->clearAt($next);
    }
}
