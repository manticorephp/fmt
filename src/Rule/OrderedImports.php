<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\OrderedImportsFixer (priority -30). Imports are split per namespace and into runs of consecutive
 * `use` statements; each run's entries (one per comma-separated name, a group import is one entry, its members sorted inside when sorting)
 * are ordered by type (imports_order) and name (sort_algorithm alpha | none, case_sensitive), and every entry slot gets the text of the
 * entry that lands there (`function ` / `const ` written out where the slot is no comma continuation). Upstream's getNewOrder /
 * setNewOrder run on a FlatTokens window per run (the runs are disjoint, so their indexes never go stale), rendered back as one edit.
 */
final class OrderedImports extends EditRule
{
    private const CLASS_ = 'class';
    private FlatTokens $t;

    /** @param string[]|null $order */
    public function __construct(private string $le = "\n", private string $sort = 'alpha', private ?array $order = null, private bool $caseSensitive = false)
    {
        $this->t = new FlatTokens();
    }

    public function name(): string
    {
        return 'ordered_imports';
    }

    public function priority(): int
    {
        return -30;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        /** @var array<int, int[]> $perNs */
        $perNs = [];
        $ns = 0;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] !== 0) {
                continue;
            }
            if ($k === \__McTokId::T_NAMESPACE) {
                $j = $i + 1;
                while ($j < $n && !(($s->kind[$j] === 59 || $s->kind[$j] === 123) && $s->ct[$j] === 0)) {
                    $j++;
                }
                if ($j < $n && $s->kind[$j] === 123) {
                    $i = $j;
                }
                $ns++;
                continue;
            }
            if ($k === \__McTokId::T_USE) {
                $perNs[$ns][] = $i;
            }
        }
        foreach ($perNs as $uses) {
            $runs = [[$uses[0]]];
            $r = 0;
            $cnt = \count($uses);
            for ($u = 0; $u < $cnt - 1; $u++) {
                $e = $this->stmtEnd($s, $uses[$u]);
                if ($e >= 0 && $s->kind[$e] === \__McTokId::T_CLOSE_TAG) {
                    $e = $uses[$u] + 1;
                    while ($e < $n && $s->kind[$e] !== \__McTokId::T_OPEN_TAG) {
                        $e++;
                    }
                }
                if ($e < 0 || $e + 1 !== $uses[$u + 1]) {
                    $runs[++$r] = [];
                }
                $runs[$r][] = $uses[$u + 1];
            }
            foreach ($runs as $run) {
                $this->fixRun($s, $run);
            }
        }
    }

    /** the `;` or `?>` that ends the statement of the `use` at $u, -1 when none */
    private function stmtEnd(Source $s, int $u): int
    {
        for ($j = $u + 1; $j < $s->n; $j++) {
            if (($s->kind[$j] === 59 && $s->ct[$j] === 0) || $s->kind[$j] === \__McTokId::T_CLOSE_TAG) {
                return $j;
            }
        }
        return -1;
    }

    /** @param int[] $run Source indexes of the `use` tokens */
    private function fixRun(Source $s, array $run): void
    {
        $last = $this->stmtEnd($s, $run[\count($run) - 1]);
        if ($last < 0) {
            return;
        }
        $first = $run[0];
        $t = $this->t;
        $t->load($s, $first, $last);
        $uses = [];
        foreach ($run as $u) {
            $uses[] = $t->pos[$u];
        }
        [$order, $same] = $this->newOrder($uses);
        if ($same) {
            return;
        }
        $this->setNewOrder($order);
        $s->edit(Source::tokAt($first), Source::tokAt($last) + 1, $t->render());
    }

    private function isWsOrEmpty(int $q): bool
    {
        return $this->t->k[$q] === FlatTokens::WS;
    }

    private function codeIs(int $q, int $id): bool
    {
        return $q >= 0 && $q < $this->t->count() && $this->t->isCode($q) && $this->t->k[$q] === $id;
    }

    /**
     * getNewOrder; [usesOrder, whether setNewOrder would leave every byte as it is]
     * @param int[] $uses
     * @return array{0: array<int, array{0:string,1:int,2:int,3:string,4:bool}>, 1: bool}
     */
    private function newOrder(array $uses): array
    {
        $t = $this->t;
        $m = $t->count();
        /** @var array<int, array{0:string,1:int,2:int,3:string,4:bool}> $indices start => [namespace, start, end, type, group] */
        $indices = [];
        $orig = [];
        foreach ($uses as $index) {
            $start = $index + 1;
            do {
                $start++;
            } while ($start < $m && $this->isWsOrEmpty($start));
            $end = $start + 1;
            while ($end < $m && !($this->codeIs($end, 59) || $this->codeIs($end, \__McTokId::T_CLOSE_TAG))) {
                $end++;
            }
            if ($end >= $m) {
                return [[], true];
            }
            $group = $this->codeIs($t->meaningful($end, -1), CT::GROUP_IMPORT_BRACE_CLOSE);
            $sk = $t->k[$start];
            if ($sk === CT::CONST_IMPORT || $sk === CT::FUNCTION_IMPORT) {
                $type = $sk === CT::CONST_IMPORT ? 'const' : 'function';
                $index = $start + 1;
                while ($index < $m && $this->isWsOrEmpty($index)) {
                    $index++;
                }
            } else {
                $type = self::CLASS_;
                $index = $start;
            }
            $nsTok = [];
            while ($index <= $end) {
                if ($index === $end || (!$group && $this->codeIs($index, 44))) {
                    if ($group && $this->sort !== 'none') {
                        $namespace = $this->groupNamespace($nsTok);
                    } else {
                        $namespace = '';
                        foreach ($nsTok as $q) {
                            $namespace .= $t->content($q);
                        }
                    }
                    $indices[$start] = [$namespace, $start, $index - 1, $type, $group];
                    $orig[] = $start;
                    if ($index === $end) {
                        break;
                    }
                    $nsTok = [];
                    $nx = $index + 1;
                    while ($nx < $m && ($this->isWsOrEmpty($nx) || $this->codeIs($nx, 44))) {
                        $nx++;
                    }
                    $start = $nx;
                    $index = $nx;
                    continue;
                }
                $nsTok[] = $index;
                $index++;
            }
        }
        if ($this->order !== null) {
            $byType = [];
            foreach ($indices as $st => $item) {
                $byType[$item[3]][$st] = $item;
            }
            $sorted = [];
            foreach ($this->order as $ty) {
                if (isset($byType[$ty])) {
                    foreach ($this->sortItems($byType[$ty]) as $st => $item) {
                        $sorted[$st] = $item;
                    }
                }
            }
            $indices = $sorted;
        } else {
            $indices = $this->sortItems($indices);
        }
        $usesOrder = [];
        $p = -1;
        $same = true;
        foreach ($indices as $v) {
            $p++;
            $slot = $orig[$p];
            $usesOrder[$slot] = $v;
            if ($same) {
                // the bytes setNewOrder writes into this slot, against the slot's bytes
                $txt = $v[0];
                if ($v[3] !== self::CLASS_ && !$this->codeIs($t->meaningful($slot, -1), 44)) {
                    $txt = $v[3] . ' ' . $txt;
                }
                $cur = '';
                $slotEnd = $indices[$slot][2];
                for ($q = $slot; $q <= $slotEnd; $q++) {
                    $cur .= $t->content($q);
                }
                if ($txt !== $cur || $v[4]) {
                    $same = false;
                }
            }
        }
        return [$usesOrder, $same];
    }

    /** the namespace of a group import with its members sorted (`A\{B, C}`), or as written when already sorted */
    private function groupNamespace(array $nsTok): string
    {
        $t = $this->t;
        $cnt = \count($nsTok) - 1;
        $namespace = '';
        $k = 0;
        for (; $k < $cnt; $k++) {
            if ($t->k[$nsTok[$k]] === CT::GROUP_IMPORT_BRACE_OPEN) {
                $namespace .= '{';
                break;
            }
            $namespace .= $t->content($nsTok[$k]);
        }
        $parts = [];
        $firstIndent = '';
        $sep = ', ';
        $lastIndent = '';
        $trailing = false;
        for ($k1 = $k + 1; $k1 < $cnt; $k1++) {
            $comment = '';
            $part = '';
            for ($k2 = $k1; ; $k2++) {
                $q = $nsTok[$k2];
                if ($this->codeIs($q, 44) || $this->codeIs($q, CT::GROUP_IMPORT_BRACE_CLOSE)) {
                    break;
                }
                if ($t->k[$q] === FlatTokens::COMMENT) {
                    $comment .= $t->content($q);
                    continue;
                }
                if ($firstIndent === '' && $t->k[$q] === FlatTokens::WS && \str_contains($t->content($q), $this->le)) {
                    $lastIndent = $this->le;
                    $firstIndent = $this->le . '    ';
                    $sep = ',' . $firstIndent;
                }
                $part .= $t->content($q);
            }
            $part = \trim($part);
            if ($part === '') {
                $trailing = true;
                continue;
            }
            $comment = \trim($comment);
            if ($comment !== '') {
                $part .= ' ' . $comment;
            }
            $parts[] = $part;
            $k1 = $k2;
        }
        $was = $parts;
        \sort($parts);
        if ($was === $parts) {
            $out = '';
            foreach ($nsTok as $q) {
                $out .= $t->content($q);
            }
            return $out;
        }
        return $namespace . $firstIndent . \implode($sep, $parts) . ($trailing ? ',' : '') . $lastIndent . '}';
    }

    /**
     * uasort by the algorithm (stable insertion sort, as PHP's)
     * @param array<int, array{0:string,1:int,2:int,3:string,4:bool}> $items
     * @return array<int, array{0:string,1:int,2:int,3:string,4:bool}>
     */
    private function sortItems(array $items): array
    {
        if ($this->sort !== 'alpha') {
            return $items;
        }
        $keys = \array_keys($items);
        $m = \count($keys);
        $prep = [];
        foreach ($keys as $key) {
            $prep[$key] = \str_replace(['\\', '{'], [' ', ''], \trim((string) \preg_replace('%/\*(.*)\*/%s', '', $items[$key][0])));
        }
        for ($i = 1; $i < $m; $i++) {
            $x = $keys[$i];
            $j = $i - 1;
            while ($j >= 0 && $this->cmp($prep[$keys[$j]], $prep[$x]) > 0) {
                $keys[$j + 1] = $keys[$j];
                $j--;
            }
            $keys[$j + 1] = $x;
        }
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $items[$key];
        }
        return $out;
    }

    private function cmp(string $a, string $b): int
    {
        return $this->caseSensitive ? $a <=> $b : \strcasecmp($a, $b);
    }

    /** @param array<int, array{0:string,1:int,2:int,3:string,4:bool}> $usesOrder */
    private function setNewOrder(array $usesOrder): void
    {
        $t = $this->t;
        $ends = [];
        foreach ($usesOrder as $use) {
            $ends[$use[1]] = $use[2];
        }
        foreach (\array_reverse($usesOrder, true) as $index => $use) {
            $clear = 3;
            if ($use[3] !== self::CLASS_ && $this->codeIs($t->meaningful($index, -1), 44)) {
                $clear = 5;
            }
            $this->overrideRange($index, $ends[$index], self::declaration($use, $clear === 3));
            if ($use[4]) {
                $this->commaToUse($t->meaningful($index, -1));
                $m = $t->count();
                $close = $index + 1;
                while ($close < $m && !$this->codeIs($close, CT::GROUP_IMPORT_BRACE_CLOSE)) {
                    $close++;
                }
                if ($close < $m) {
                    $this->commaToUse($t->meaningful($close, 1));
                }
            }
        }
    }

    /**
     * The tokens of `<?php use [ function ]NS;` without `<?php use ` (+ `function ` after a comma) and `;`: only their bytes and the group
     * closer (setNewOrder looks for it) matter, so NS is one item and a group's `}` another; no re-lexing.
     * @param array{0:string,1:int,2:int,3:string,4:bool} $use
     * @return array<int, array{0:int,1:string,2:int}>
     */
    private static function declaration(array $use, bool $keyword): array
    {
        $txt = ($keyword && $use[3] !== self::CLASS_ ? $use[3] . ' ' : '') . $use[0];
        if ($use[4] && \str_ends_with($txt, '}')) {
            return [FlatTokens::code(\__McTokId::T_STRING, \substr($txt, 0, -1)), FlatTokens::code(CT::GROUP_IMPORT_BRACE_CLOSE, '}')];
        }
        return [FlatTokens::code(\__McTokId::T_STRING, $txt)];
    }

    /** @param array<int, array{0:int,1:string,2:int}> $items */
    private function overrideRange(int $a, int $b, array $items): void
    {
        $t = $this->t;
        $span = $b - $a + 1;
        $cnt = \count($items);
        if ($cnt > $span) {
            $ph = [];
            while ($cnt > $span) {
                $ph[] = FlatTokens::code(0, '__PLACEHOLDER__');
                $span++;
            }
            $t->insertAt($b + 1, $ph);
        }
        foreach ($items as $j => $it) {
            $t->k[$a + $j] = $it[0];
            $t->c[$a + $j] = $it[1];
            $t->i[$a + $j] = $it[2];
        }
        for ($j = $a + $cnt; $j <= $b; $j++) {
            $t->clearAt($j);
        }
    }

    private function commaToUse(int $q): void
    {
        if (!$this->codeIs($q, 44)) {
            return;
        }
        $t = $this->t;
        $t->k[$q] = 59;
        $t->c[$q] = ';';
        $t->i[$q] = FlatTokens::NEW;
        $t->insertAt($q + 1, [FlatTokens::code(\__McTokId::T_USE, 'use')]);
        if (!$t->isWs($q + 2)) {
            $t->insertAt($q + 2, [FlatTokens::ws(' ')]);
        }
    }
}
