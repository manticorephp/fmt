<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Doc\TypeExpression;
use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;
use Fmt\Preg;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitDataProviderMethodOrderFixer (priority 64). In a PHPUnit test class each data provider method (named by an `@dataProvider`
 * annotation or a `#[DataProvider('name')]` attribute) is moved right after (placement=after) or right before (placement=before) the first test that uses it; providers of one test
 * keep their order. The class elements are the ones of OrderedClassElementsFixer::getElements; the tokens of the moved elements move as whole ranges.
 * Whole-file FlatTokens port (an upstream assertion that fails leaves the file as it is).
 */
final class PhpUnitDataProviderMethodOrder extends PhpUnitClassRule
{
    private const REGEX_CLASS = '(?:\\\?+' . TypeExpression::REGEX_IDENTIFIER . '(\\\\' . TypeExpression::REGEX_IDENTIFIER . ')*+)';

    private bool $changed = false;

    public function __construct(private bool $after)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_data_provider_method_order';
    }

    public function priority(): int
    {
        return 64;
    }

    protected function candidate(Source $s): bool
    {
        if (!parent::candidate($s)) {
            return false;
        }
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_ATTRIBUTE || (($s->gapFlags[$i] & \Fmt\Gap::G_DOC) !== 0 && \str_contains($s->gapBefore[$i], '@dataProvider'))) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        if ($n === 0) {
            return;
        }
        [$a, $b, $tail] = [0, $n - 1, false];
        $t = $this->t;
        $t->load($s, $a, $b, $tail);
        $this->changed = false;
        $this->run($s, $t);
        if ($this->changed) {
            $t->emit($s, $a, $b, $tail, false, true);
        }
    }

    protected function applyClass(FlatTokens $t, int $startIndex, int $endIndex): void
    {
        $el = $this->elements($t, $startIndex);
        $count = \count($el);
        if ($count === 0) {
            return;
        }
        $endIndex = $el[$count - 1]['end'];
        $pairs = $this->usageProviderPairs($t, $startIndex, $endIndex);
        if ($pairs === null) {
            return;
        }
        /** @var int[] $sorted element numbers in the current order */
        $sorted = \range(0, $count - 1);
        $orig = $sorted;
        $placed = [];
        if (!$this->after) {
            foreach ($pairs as [$usage, $provider]) {
                if (!isset($placed[$provider])) {
                    $placed[$provider] = true;
                    $sorted = $this->move($el, $sorted, $usage, $provider, false);
                    if ($sorted === null) {
                        $this->src->abort = true;
                        return;
                    }
                }
            }
        } else {
            $sameUsage = null;
            $sameProvider = null;
            foreach ($pairs as [$usage, $provider]) {
                if (!isset($placed[$provider])) {
                    $placed[$provider] = true;
                    $before = $sorted;
                    $sorted = $this->move($el, $sorted, ($usage === $sameUsage && $sameProvider !== null) ? $sameProvider : $usage, $provider, true);
                    if ($sorted === null) {
                        $this->src->abort = true;
                        return;
                    }
                    $sameUsage = $usage;
                    $sameProvider = $provider;
                    if ($before !== $sorted) {
                        unset($placed[$provider]);
                    }
                }
            }
        }
        if ($sorted === $orig) {
            return;
        }
        // OrderedClassElementsFixer::sortTokens: the ranges of the elements, concatenated in the new order, replace [start + 1, end]
        /** @var int[] $k */
        $k = [];
        /** @var string[] $c */
        $c = [];
        /** @var int[] $ix */
        $ix = [];
        for ($q = 0; $q <= $startIndex; $q++) {
            $k[] = $t->k[$q];
            $c[] = $t->c[$q];
            $ix[] = $t->i[$q];
        }
        foreach ($sorted as $e) {
            for ($q = $el[$e]['start']; $q <= $el[$e]['end']; $q++) {
                $k[] = $t->k[$q];
                $c[] = $t->c[$q];
                $ix[] = $t->i[$q];
            }
        }
        $m = $t->count();
        for ($q = $endIndex + 1; $q < $m; $q++) {
            $k[] = $t->k[$q];
            $c[] = $t->c[$q];
            $ix[] = $t->i[$q];
        }
        $t->k = $k;
        $t->c = $c;
        $t->i = $ix;
        $this->changed = true;
    }

    /**
     * moveMethodElement: null when an upstream assertion fails (the usage or the provider is no `method` element)
     * @param array<int, array{start:int,end:int,type:string,name:string}> $el
     * @param int[] $elements
     * @return int[]|null
     */
    private function move(array $el, array $elements, string $keep, string $toMove, bool $after): ?array
    {
        $iKeep = -1;
        $iMove = -1;
        foreach ($elements as $i => $e) {
            if ($el[$e]['type'] === 'method') {
                if ($el[$e]['name'] === $keep) {
                    $iKeep = $i;
                } elseif ($el[$e]['name'] === $toMove) {
                    $iMove = $i;
                }
            }
        }
        if ($iKeep < 0 || $iMove < 0) {
            return null;
        }
        if ($iMove === $iKeep + ($after ? 1 : -1)) {
            return $elements;
        }
        $moved = $elements[$iMove];
        /** @var int[] $rest */
        $rest = [];
        foreach ($elements as $x => $e) {
            if ($x !== $iMove) {
                $rest[] = $e;
            }
        }
        $c = $iKeep + ($after ? 1 : 0) + ($iMove < $iKeep ? -1 : 0);
        /** @var int[] $out */
        $out = [];
        $cnt = \count($rest);
        for ($x = 0; $x <= $cnt; $x++) {
            if ($x === $c) {
                $out[] = $moved;
            }
            if ($x < $cnt) {
                $out[] = $rest[$x];
            }
        }
        return $out;
    }

    /**
     * OrderedClassElementsFixer::getElements on flat indexes
     * @return array<int, array{start:int,end:int,type:string,name:string}>
     */
    private function elements(FlatTokens $t, int $startIndex): array
    {
        $m = $t->count();
        ++$startIndex;
        $out = [];
        while (true) {
            $start = $startIndex;
            $type = '';
            $name = '';
            $found = false;
            for ($i = $startIndex; $i < $m; ++$i) {
                if ($t->i[$i] === -1) {
                    continue;
                }
                $k = $t->k[$i];
                if ($k === \__McTokId::T_ATTRIBUTE) {
                    $i = $t->blockEnd($i, CT::ATTRIBUTE_CLOSE);
                    if ($i < 0) {
                        return $out;
                    }
                    continue;
                }
                if ($k === 125) {
                    return $out;
                }
                if ($k !== CT::USE_TRAIT && $k !== \__McTokId::T_CASE && $k !== \__McTokId::T_CONST && $k !== \__McTokId::T_VARIABLE && $k !== \__McTokId::T_FUNCTION) {
                    continue;
                }
                $nm = $t->meaningful($i, 1);
                if ($k === CT::USE_TRAIT) {
                    $type = 'use_trait';
                    $name = $nm < 0 ? '' : $t->content($nm);
                } elseif ($k === \__McTokId::T_CASE) {
                    $type = 'case';
                    $name = $nm < 0 ? '' : $t->content($nm);
                } elseif ($k === \__McTokId::T_CONST) {
                    $type = 'constant';
                    $eq = self::nextOf($t, $i, 61);
                    $pm = $eq < 0 ? -1 : $t->meaningful($eq, -1);
                    $name = $pm < 0 ? '' : $t->content($pm);
                } elseif ($k === \__McTokId::T_VARIABLE) {
                    $type = 'property';
                    $name = $t->content($i);
                } else {
                    $nameTok = $nm < 0 ? '' : $t->content($nm);
                    $isStr = $nm >= 0 && $t->k[$nm] === \__McTokId::T_STRING;
                    $low = \strtolower($nameTok);
                    if ($isStr && $low === '__construct') {
                        $type = 'construct';
                    } elseif ($isStr && $low === '__destruct') {
                        $type = 'destruct';
                    } elseif ($isStr && \in_array($low, ['setupbeforeclass', 'dosetupbeforeclass', 'teardownafterclass', 'doteardownafterclass', 'setup', 'dosetup',
                        'assertpreconditions', 'assertpostconditions', 'teardown', 'doteardown'], true)) {
                        $type = 'phpunit';
                    } else {
                        $type = \str_starts_with($nameTok, '__') ? 'magic' : 'method';
                    }
                    $name = $nameTok;
                }
                $end = $this->elementEnd($t, $i);
                if ($end < 0) {
                    return $out;
                }
                $out[] = ['start' => $start, 'end' => $end, 'type' => $type, 'name' => $name];
                $startIndex = $end + 1;
                $found = true;
                break;
            }
            if (!$found) {
                return $out;
            }
        }
    }

    private function elementEnd(FlatTokens $t, int $index): int
    {
        $m = $t->count();
        $j = -1;
        for ($x = $index + 1; $x < $m; $x++) {
            if ($t->i[$x] !== -1 && ($t->k[$x] === 40 || $t->k[$x] === 123 || $t->k[$x] === 59 || $t->k[$x] === CT::PROPERTY_HOOK_BRACE_OPEN)) {
                $j = $x;
                break;
            }
        }
        if ($j < 0) {
            return -1;
        }
        if ($t->k[$j] === 40) {
            $j = $t->blockEnd($j, 41);
            if ($j < 0) {
                return -1;
            }
            $y = -1;
            for ($x = $j + 1; $x < $m; $x++) {
                if ($t->i[$x] !== -1 && ($t->k[$x] === 123 || $t->k[$x] === 59)) {
                    $y = $x;
                    break;
                }
            }
            if ($y < 0) {
                return -1;
            }
            $j = $y;
        }
        if ($t->k[$j] === 123) {
            $j = $t->blockEnd($j, 125);
        } elseif ($t->k[$j] === CT::PROPERTY_HOOK_BRACE_OPEN) {
            $j = $t->blockEnd($j, CT::PROPERTY_HOOK_BRACE_CLOSE);
        }
        if ($j < 0) {
            return -1;
        }
        ++$j;
        while ($j < $m && (($t->k[$j] === FlatTokens::WS && \strspn($t->c[$j], " \t") === \strlen($t->c[$j])) || $t->k[$j] === FlatTokens::COMMENT)) {
            ++$j;
        }
        --$j;
        return $t->k[$j] === FlatTokens::WS ? $j - 1 : $j;
    }

    /**
     * getDataProvidersWithUsagePairs + getOrigUsageDataProviderOrderPairs: [usage method name, provider method name] in the usage order; null when an upstream assertion fails
     * @return array<int, array{0:string,1:string}>|null
     */
    private function usageProviderPairs(FlatTokens $t, int $startIndex, int $endIndex): ?array
    {
        $m = $t->count();
        /** @var array<string, int> $methods lower-case name => index of the name token */
        $methods = [];
        for ($index = $startIndex; $index < $endIndex && $index < $m; ++$index) {
            if (!self::is($t, $index, \__McTokId::T_FUNCTION)) {
                continue;
            }
            $ni = $t->nonWhitespace($index, 1);
            if ($ni < 0 || !self::is($t, $ni, \__McTokId::T_STRING)) {
                continue;
            }
            $methods[\strtolower($t->content($ni))] = $ni;
        }
        /** @var array<string, array<int, array{0:int,1:int}>> $providers name => usages [index, offset] */
        $providers = [];
        foreach ($methods as $methodIndex) {
            $attr = -1;
            $doc = -1;
            $index = $methodIndex;
            while (true) {
                if ($t->i[$index] !== -1 && ($t->k[$index] === 59 || $t->k[$index] === 123 || $t->k[$index] === 125 || $t->k[$index] === \__McTokId::T_OPEN_TAG)) {
                    break;
                }
                --$index;
                if ($index < 0) {
                    break;
                }
                if ($t->i[$index] !== -1 && $t->k[$index] === \__McTokId::T_ATTRIBUTE) {
                    $attr = $index;
                } elseif ($t->isComment($index) && Trivia::isDoc($t->c[$index])) {
                    $doc = $index;
                }
            }
            if ($attr >= 0) {
                foreach ($this->attributes($t, $attr) as $attribute) {
                    $nameIndex = $this->dataProviderNameIndex($t, $attribute);
                    if ($nameIndex < 0) {
                        continue;
                    }
                    $providers[\substr($t->content($nameIndex), 1, -1)][] = [$nameIndex, 0];
                }
            }
            if ($doc >= 0) {
                Preg::matchAll('/@dataProvider\h+((' . self::REGEX_CLASS . '::)?' . TypeExpression::REGEX_IDENTIFIER . ')/', $t->c[$doc], $mm, \PREG_OFFSET_CAPTURE);
                foreach (($mm[1] ?? []) as $x => $mt) {
                    $providers[(string) $mt[0]][] = [$doc, (int) $mm[0][$x][1]];
                }
            }
        }
        /** @var array<int, array{0:string,1:array<int, array{0:int,1:string,2:int}>}> $analyses method index => [name, usages] */
        $analyses = [];
        foreach ($providers as $providerName => $usages) {
            $lower = \strtolower((string) $providerName);
            if (!isset($methods[$lower])) {
                continue;
            }
            $analyses[$methods[$lower]] = [$t->content($methods[$lower]), $usages];
        }
        \ksort($analyses);
        $flat = [];
        foreach ($analyses as [$providerName, $usages]) {
            /** @var array<int, array{0:int,1:string,2:int}> $found */
            $found = [];
            foreach ($usages as $u) {
                $seq = $this->findFunctionName($t, $u[0], $endIndex);
                if ($seq < 0) {
                    continue;
                }
                $found[$seq] = [$seq, $t->content($seq), $u[1]];
            }
            if ($found === []) {
                $this->src->abort = true; // upstream's \assert([] !== $usages) fails
                return null;
            }
            foreach ($found as $usage) {
                $flat[] = [$usage, $providerName];
            }
        }
        // uasort is stable: by the usage method's index, then the offset of the usage
        $order = \array_keys($flat);
        \usort($order, static function (int $x, int $y) use ($flat): int {
            $cmp = $flat[$x][0][0] <=> $flat[$y][0][0];
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = $flat[$x][0][2] <=> $flat[$y][0][2];
            return $cmp !== 0 ? $cmp : $x <=> $y;
        });
        $out = [];
        foreach ($order as $x) {
            $out[] = [$flat[$x][0][1], $flat[$x][1]];
        }
        return $out;
    }

    /** Tokens::findSequence([[T_FUNCTION], [T_STRING]], $start, $end): the index of the name token, -1 when none */
    private function findFunctionName(FlatTokens $t, int $start, int $end): int
    {
        $end = \min($end, $t->count() - 1);
        for ($index = $start; $index <= $end; ++$index) {
            if (!self::is($t, $index, \__McTokId::T_FUNCTION)) {
                continue;
            }
            $nx = $t->meaningful($index, 1);
            if ($nx < 0 || $nx > $end) {
                continue;
            }
            if (self::is($t, $nx, \__McTokId::T_STRING)) {
                return $nx;
            }
        }
        return -1;
    }

    /**
     * AttributeAnalyzer::collect on flat indexes
     * @return array<int, array{start:int,end:int,name:string}>
     */
    private function attributes(FlatTokens $t, int $index): array
    {
        while (true) {
            $p = $t->meaningful($index, -1);
            if ($p < 0 || !self::is($t, $p, CT::ATTRIBUTE_CLOSE)) {
                break;
            }
            $index = $t->blockStart($p, \__McTokId::T_ATTRIBUTE);
            if ($index < 0) {
                return [];
            }
        }
        $elements = [];
        $opening = $index;
        do {
            $close = $t->blockEnd($opening, CT::ATTRIBUTE_CLOSE);
            if ($close < 0) {
                break;
            }
            foreach ($this->collectAttributes($t, $opening, $close) as $e) {
                $elements[] = $e;
            }
            $endIndex = $t->nonWhitespace($close, 1);
            if ($endIndex < 0) {
                break;
            }
            $opening = $t->meaningful($endIndex - 1, 1);
        } while ($opening >= 0 && self::is($t, $opening, \__McTokId::T_ATTRIBUTE));
        return $elements;
    }

    /** @return array<int, array{start:int,end:int,name:string}> */
    private function collectAttributes(FlatTokens $t, int $index, int $closingIndex): array
    {
        $m = $t->count();
        $elements = [];
        do {
            $attributeStart = $index + 1;
            $nameStart = -1;
            for ($j = $index + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === \__McTokId::T_STRING || $t->k[$j] === \__McTokId::T_NS_SEPARATOR)) {
                    $nameStart = $j;
                    break;
                }
            }
            $index = $this->nextOfAny($t, $attributeStart - 1, [40, 44, CT::ATTRIBUTE_CLOSE]);
            if ($nameStart < 0 || $index < 0) {
                return $elements;
            }
            $last = $t->meaningful($index, -1);
            $name = '';
            for ($j = $nameStart; $j <= $last; $j++) {
                $name .= $t->content($j);
            }
            if (self::is($t, $index, 40)) {
                $index = $t->blockEnd($index, 41);
                $index = $index < 0 ? -1 : $this->nextOfAny($t, $index, [44, CT::ATTRIBUTE_CLOSE]);
                if ($index < 0) {
                    return $elements;
                }
            }
            $elements[] = ['start' => $attributeStart, 'end' => $index - 1, 'name' => $name];
            $next = $index;
            if ($next < $closingIndex) {
                $next = $t->meaningful($index, 1);
            }
        } while ($next < $closingIndex);
        --$index;
        while ($t->isWs($index)) {
            if (Preg::test('/\R/', $t->c[$index])) {
                $elements[\count($elements) - 1]['end'] = $index - 1;
                break;
            }
            --$index;
        }
        return $elements;
    }

    /** @param int[] $ids */
    private function nextOfAny(FlatTokens $t, int $q, array $ids): int
    {
        $m = $t->count();
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && \in_array($t->k[$j], $ids, true)) {
                return $j;
            }
        }
        return -1;
    }

    /** @param array{start:int,end:int,name:string} $attribute */
    private function dataProviderNameIndex(FlatTokens $t, array $attribute): int
    {
        $at = $t->meaningful($attribute['start'], 1);
        if ($at < 0 || $t->i[$at] < 0) {
            return -1;
        }
        if ($this->fqn($attribute['name'], $t->i[$at]) !== 'PHPUnit\Framework\Attributes\DataProvider') {
            return -1;
        }
        $close = -1;
        for ($j = $attribute['end']; $j >= 0; $j--) {
            if ($t->i[$j] !== -1 && ($t->k[$j] === 41 || $t->k[$j] === \__McTokId::T_ATTRIBUTE)) {
                $close = $j;
                break;
            }
        }
        if ($close < 0 || $t->k[$close] === \__McTokId::T_ATTRIBUTE) {
            return -1;
        }
        $nameIndex = $t->meaningful($close, -1);
        if ($nameIndex < 0 || !self::is($t, $nameIndex, \__McTokId::T_CONSTANT_ENCAPSED_STRING)) {
            return -1;
        }
        $open = $t->meaningful($nameIndex, -1);
        if ($open < 0 || !self::is($t, $open, 40)) {
            return -1;
        }
        return $nameIndex;
    }
}
