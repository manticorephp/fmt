<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\ClassDefinitionFixer (priority 36), a file rule. The header of a class / interface /
 * trait / enum (from its first modifier or `new` to the `{`) is copied into a flat list of items that mirrors upstream's token
 * list exactly (code tokens, whitespace tokens, comment tokens), the fixer's algorithm runs on that list with the same integer
 * indexes (upstream's stale indexes after an insertion included), and the result is written back as gaps. Indentation of a
 * multi-line `extends` / `implements` list is four spaces, the line ending "\n" (the default whitespace config).
 */
final class ClassDefinition extends GapRule
{
    private const T_WS = -1;
    private const T_COMMENT = -2;

    /** @var int[] effective id of an item: custom or lexer kind for code, T_WS / T_COMMENT for trivia */
    private array $vk = [];
    /** @var string[] content of a trivia item ('' for code) */
    private array $vc = [];
    /** @var int[] Source token index of a code item, -1 for trivia */
    private array $vi = [];
    private ?Source $src = null;

    public function __construct(
        private bool $singleLine = false,
        private bool $singleItemSingleLine = false,
        private bool $multiLineExtendsEachSingleLine = false,
        private bool $spaceBeforeParenthesis = false,
        private bool $inlineConstructorArguments = true,
        private string $le = "\n",
        private string $indent = '    ',
    ) {}

    public function name(): string
    {
        return 'class_definition';
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
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_TEXT;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function prepare(Source $s): bool
    {
        $n = $s->n;
        for ($j = 0; $j < $n; $j++) {
            if ($s->ct[$j] === 0 && self::isClassy($s->kind[$j])) {
                return true;
            }
        }
        return false;
    }

    private static function isClassy(int $k): bool
    {
        return $k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM;
    }

    public function fixFile(Source $s): void
    {
        $this->src = $s;
        for ($c = $s->n - 1; $c > 0; $c--) {
            if ($s->ct[$c] === 0 && self::isClassy($s->kind[$c])) {
                $this->fixClassy($s, $c);
            }
        }
        $this->src = null;
    }

    // ---- the item list ----------------------------------------------------------------------------------------------------

    private function load(Source $s, int $a, int $b): void
    {
        $this->vk = [];
        $this->vc = [];
        $this->vi = [];
        for ($c = $a; $c <= $b; $c++) {
            if ($c > $a) {
                $g = $s->gapBefore[$c];
                if ($g !== '') {
                    foreach (Gap::hasComment($g) ? Gap::pieces($g) : [$g] as $p) {
                        $this->vk[] = Gap::isSpace($p) ? self::T_WS : self::T_COMMENT;
                        $this->vc[] = $p;
                        $this->vi[] = -1;
                    }
                }
            }
            $this->vk[] = $s->ct[$c] !== 0 ? $s->ct[$c] : $s->kind[$c];
            $this->vc[] = '';
            $this->vi[] = $c;
        }
    }

    private function store(Source $s): void
    {
        $m = \count($this->vk);
        $acc = '';
        $first = true;
        for ($q = 0; $q < $m; $q++) {
            if ($this->vi[$q] < 0) {
                $acc .= $this->vc[$q];
                continue;
            }
            if (!$first) {
                $s->setGap($this->vi[$q], $acc);
            }
            $first = false;
            $acc = '';
        }
    }

    private function count(): int
    {
        return \count($this->vk);
    }

    private function isWs(int $i): bool
    {
        return $i >= 0 && $i < \count($this->vk) && $this->vk[$i] === self::T_WS;
    }

    private function isComment(int $i): bool
    {
        return $i >= 0 && $i < \count($this->vk) && $this->vk[$i] === self::T_COMMENT;
    }

    private function isDoc(int $i): bool
    {
        if (!$this->isComment($i)) {
            return false;
        }
        $c = $this->vc[$i];
        return \strlen($c) >= 4 && \substr($c, 0, 3) === '/**' && ($c[3] === ' ' || $c[3] === "\t" || $c[3] === "\n" || $c[3] === "\r");
    }

    private function isId(int $i, int $id): bool
    {
        return $i >= 0 && $i < \count($this->vk) && $this->vk[$i] === $id;
    }

    /** a plain one-character token (no custom id) */
    private function plain(int $i, int $ch): bool
    {
        return $i >= 0 && $i < \count($this->vk) && $this->vk[$i] === $ch && $this->vi[$i] >= 0 && $this->src !== null && $this->src->ct[$this->vi[$i]] === 0;
    }

    private function content(int $i): string
    {
        return $this->vi[$i] >= 0 && $this->src !== null ? $this->src->text($this->vi[$i]) : $this->vc[$i];
    }

    private function setWs(int $i, string $c): void
    {
        $this->vc[$i] = $c;
    }

    private function insertWs(int $at, string $c): void
    {
        \array_splice($this->vk, $at, 0, [self::T_WS]);
        \array_splice($this->vc, $at, 0, [$c]);
        \array_splice($this->vi, $at, 0, [-1]);
    }

    private function prevNonWs(int $i): int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if ($this->vk[$j] !== self::T_WS) {
                return $j;
            }
        }
        return -1;
    }

    private function nextMeaningful(int $i): int
    {
        $m = \count($this->vk);
        for ($j = $i + 1; $j < $m; $j++) {
            if ($this->vk[$j] !== self::T_WS && $this->vk[$j] !== self::T_COMMENT) {
                return $j;
            }
        }
        return -1;
    }

    // ---- the fixer --------------------------------------------------------------------------------------------------------

    private function fixClassy(Source $s, int $classy): void
    {
        $n = $s->n;
        $open = -1;
        for ($j = $classy + 1; $j < $n; $j++) {
            if ($s->kind[$j] === 123 && $s->ct[$j] === 0) {
                $open = $j;
                break;
            }
        }
        if ($open < 0) {
            return;
        }
        $k = $s->kind[$classy];
        $extC = -1;
        $implC = -1;
        $anon = false;
        if ($k !== \__McTokId::T_TRAIT) {
            $extC = self::findKind($s, \__McTokId::T_EXTENDS, $classy, $open);
            if ($k !== \__McTokId::T_INTERFACE) {
                $implC = self::findKind($s, \__McTokId::T_IMPLEMENTS, $classy, $open);
                $anon = Analyzer::isAnonymousClass($s, $classy);
            }
        }
        $final = -1;
        $abstract = -1;
        $readonly = -1;
        if ($anon) {
            $startC = $classy - 1;
            while ($startC >= 0 && !($s->kind[$startC] === \__McTokId::T_NEW && $s->ct[$startC] === 0)) {
                $startC--;
            }
            if ($startC < 0) {
                return;
            }
        } else {
            $startC = $classy;
            $j = $classy - 1;
            while ($j >= 0 && $s->ct[$j] === 0) {
                $kk = $s->kind[$j];
                if ($kk === \__McTokId::T_FINAL) {
                    $final = $j;
                } elseif ($kk === \__McTokId::T_ABSTRACT) {
                    $abstract = $j;
                } elseif ($kk === \__McTokId::T_READONLY) {
                    $readonly = $j;
                } else {
                    break;
                }
                $startC = $j;
                $j--;
            }
        }
        $this->load($s, $startC, $open);
        // view indexes: code item of Source token c is at pos[c]
        $pos = [];
        $m = $this->count();
        for ($q = 0; $q < $m; $q++) {
            if ($this->vi[$q] >= 0) {
                $pos[$this->vi[$q]] = $q;
            }
        }
        $vClassy = $pos[$classy];
        $vOpen = $pos[$open];
        $vStart = $pos[$startC];
        $ext = false;
        $impl = false;
        $extStart = $extC >= 0 ? $pos[$extC] : -1;
        $implStart = $implC >= 0 ? $pos[$implC] : -1;
        $extInfo = $extC >= 0 ? $this->inheritanceInfo($extStart) : [0, 0, false];
        $implInfo = $implC >= 0 ? $this->inheritanceInfo($implStart) : [0, 0, false];
        if ($implC >= 0) {
            $implInfo[2] = $this->fixImplements($vOpen, $implStart, $implInfo);
        }
        if ($extC >= 0) {
            $extInfo[2] = $this->fixExtends($implC >= 0 ? $implStart : $vOpen, $extStart, $extInfo);
        }
        $vOpen = $this->fixOpenSpacing($vClassy, $anon, $implC >= 0, $implInfo[2], $extC >= 0, $extInfo[2]);
        if ($implC >= 0) {
            $end = $implStart;
        } elseif ($extC >= 0) {
            $end = $extStart;
        } else {
            $end = $this->prevNonWs($vOpen);
        }
        if ($anon && !$this->inlineConstructorArguments) {
            while (!$this->plain($end, 41) && !$this->isId($end, \__McTokId::T_CLASS)) {
                $st = $this->prevMeaningful($end);
                if ($st < 0) {
                    break;
                }
                $this->makeSingleLine($st, $end);
                $end = $st;
            }
            if ($this->plain($end, 41)) {
                $end = $this->blockStart($end);
            }
        }
        $this->makeSingleLine($vStart, $end);
        $this->store($s);
        if ($readonly >= 0) {
            foreach ([$final, $abstract] as $mod) {
                if ($mod < 0 || $mod < $readonly) {
                    continue;
                }
                $tr = $s->text($readonly);
                $tm = $s->text($mod);
                $kr = $s->kind[$readonly];
                $s->setKind($readonly, $s->kind[$mod]);
                $s->setKind($mod, $kr);
                $s->setText($readonly, $tm);
                $s->setText($mod, $tr);
                break;
            }
        }
    }

    private static function findKind(Source $s, int $kind, int $from, int $to): int
    {
        for ($j = $from; $j < $to; $j++) {
            if ($s->kind[$j] === $kind && $s->ct[$j] === 0) {
                return $j;
            }
        }
        return -1;
    }

    private function prevMeaningful(int $i): int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if ($this->vk[$j] !== self::T_WS && $this->vk[$j] !== self::T_COMMENT) {
                return $j;
            }
        }
        return -1;
    }

    /** Tokens::findBlockStart for a `)` item */
    private function blockStart(int $i): int
    {
        $s = $this->src;
        if ($s === null) {
            return $i;
        }
        $o = $s->pair[$this->vi[$i]];
        for ($j = $i; $j >= 0; $j--) {
            if ($this->vi[$j] === $o) {
                return $j;
            }
        }
        return $i;
    }

    /** @return array{0:int,1:int,2:bool} [start, count, multiLine] */
    private function inheritanceInfo(int $start): array
    {
        $count = 1;
        $multi = false;
        $m = $this->count();
        $from = $start + 1;
        $end = $from;
        for ($j = $from; $j < $m; $j++) {
            if ($this->plain($j, 123) || $this->isId($j, \__McTokId::T_IMPLEMENTS) || $this->isId($j, \__McTokId::T_EXTENDS)) {
                $end = $j;
                break;
            }
        }
        if ($this->plain($end, 123)) {
            $end = $this->prevNonWs($end);
        }
        for ($j = $from; $j < $end; $j++) {
            if ($this->plain($j, 44)) {
                $count++;
                continue;
            }
            if (!$multi && \strpos($this->content($j), "\n") !== false) {
                $multi = true;
            }
        }
        return [$start, $count, $multi];
    }

    /** @param array{0:int,1:int,2:bool} $info */
    private function fixImplements(int $open, int $start, array $info): bool
    {
        $end = $this->prevNonWs($open);
        if ($this->singleLine || !$info[2]) {
            $this->makeSingleLine($start, $end);
            return false;
        }
        if ($this->singleItemSingleLine && $info[1] === 1) {
            $this->makeSingleLine($start, $end);
            return false;
        }
        $this->makeMultiLine($start, $end);
        return true;
    }

    /** @param array{0:int,1:int,2:bool} $info */
    private function fixExtends(int $open, int $start, array $info): bool
    {
        $end = $this->prevNonWs($open);
        if ($this->singleLine || !$info[2]) {
            $this->makeSingleLine($start, $end);
            return false;
        }
        if ($this->singleItemSingleLine && $info[1] === 1) {
            $this->makeSingleLine($start, $end);
            return false;
        }
        if ($this->multiLineExtendsEachSingleLine && $info[2]) {
            $this->makeMultiLine($start, $end);
            return true;
        }
        return $info[2];
    }

    private function fixOpenSpacing(int $classy, bool $anon, bool $hasImpl, bool $implMulti, bool $hasExt, bool $extMulti): int
    {
        if ($anon) {
            if ($hasImpl) {
                $spacing = $implMulti ? $this->le : ' ';
            } elseif ($hasExt) {
                $spacing = $extMulti ? $this->le : ' ';
            } else {
                $spacing = ' ';
            }
        } else {
            $spacing = $this->le;
        }
        $m = $this->count();
        $open = -1;
        for ($j = $classy + 1; $j < $m; $j++) {
            if ($this->plain($j, 123)) {
                $open = $j;
                break;
            }
        }
        if ($open < 0) {
            return $classy;
        }
        if ($spacing !== ' ' && $open > 0 && \strpos($this->content($open - 1), "\n") !== false) {
            return $open;
        }
        if ($this->isWs($open - 1)) {
            if ($spacing !== ' ' || !$this->isComment($this->prevNonWs($open - 1))) {
                $this->setWs($open - 1, $spacing);
            }
            return $open;
        }
        $this->insertWs($open, $spacing);
        return $open + 1;
    }

    private function makeSingleLine(int $startIndex, int $endIndex): void
    {
        for ($i = $endIndex; $i >= $startIndex; $i--) {
            if ($this->isWs($i)) {
                if (\strpos($this->vc[$i], "\n") !== false) {
                    if ($this->isId($i - 1, CT::ATTRIBUTE_CLOSE) || $this->isId($i + 1, \__McTokId::T_ATTRIBUTE)) {
                        continue;
                    }
                    if (($this->isComment($i - 1) && \str_ends_with($this->vc[$i - 1], ']'))
                        || ($this->isComment($i + 1) && \str_starts_with($this->vc[$i + 1], '#['))) {
                        continue;
                    }
                    if ($this->isDoc($i - 1) || $this->isDoc($i + 1)) {
                        continue;
                    }
                }
                if ($this->isComment($i - 1)) {
                    $c = $this->vc[$i - 1];
                    if (!\str_starts_with($c, '//') && !\str_starts_with($c, '#')) {
                        $this->setWs($i, ' ');
                    }
                    continue;
                }
                if ($this->isComment($i + 1)) {
                    if (!\str_starts_with($this->vc[$i + 1], '//')) {
                        $this->setWs($i, ' ');
                    }
                    continue;
                }
                if ($this->isId($i - 1, \__McTokId::T_CLASS) && $this->plain($i + 1, 40)) {
                    $this->setWs($i, $this->spaceBeforeParenthesis ? ' ' : '');
                    continue;
                }
                if ((!$this->plain($i - 1, 44) && ($this->plain($i + 1, 44) || $this->plain($i + 1, 41))) || $this->plain($i - 1, 40)) {
                    $this->setWs($i, '');
                    continue;
                }
                $this->setWs($i, ' ');
                continue;
            }
            if ($this->plain($i, 44) && !$this->isWs($i + 1)) {
                $this->insertWs($i + 1, ' ');
                continue;
            }
            if ($this->spaceBeforeParenthesis && $this->isId($i, \__McTokId::T_CLASS) && !$this->isWs($i + 1)) {
                $this->insertWs($i + 1, ' ');
                continue;
            }
            if (!$this->isComment($i)) {
                continue;
            }
            if (!$this->isWs($i + 1) && !$this->isComment($i + 1) && \strpos($this->vc[$i], "\n") === false) {
                $this->insertWs($i + 1, ' ');
            }
            if (!$this->isWs($i - 1) && !$this->isComment($i - 1)) {
                $this->insertWs($i, ' ');
            }
        }
    }

    private function makeMultiLine(int $startIndex, int $endIndex): void
    {
        for ($i = $endIndex; $i > $startIndex; $i--) {
            $prev = -1;
            for ($j = $i - 1; $j >= 0; $j--) {
                if ($this->plain($j, 44) || $this->isId($j, \__McTokId::T_IMPLEMENTS) || $this->isId($j, \__McTokId::T_EXTENDS)) {
                    $prev = $j;
                    break;
                }
            }
            $breakAt = $this->nextMeaningful($prev);
            $this->makeSingleLine($breakAt, $i);
            $own = false;
            for ($j = $breakAt; $j > $prev; $j--) {
                if (\strpos($this->content($j), "\n") !== false) {
                    $own = true;
                    break;
                }
            }
            if (!$own) {
                if ($this->isWs($breakAt - 1)) {
                    $this->setWs($breakAt - 1, $this->le . $this->indent);
                } else {
                    $this->insertWs($breakAt, $this->le . $this->indent);
                }
            }
            $i = $prev + 1;
        }
    }
}
