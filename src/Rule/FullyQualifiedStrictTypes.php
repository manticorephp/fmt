<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Doc\NsScan;
use Fmt\Doc\TypeExpression;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\Invocation;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer (priority 7): class names in signatures, `new`, `instanceof`,
 * `::`, `catch`, attributes, extends / implements, trait uses and phpdoc tags are written as short as the namespace and the imports
 * allow (import_symbols: missing imports are added). Upstream's walk is kept per namespace; a replaced name is a span edit over its
 * name tokens, and a later visit of the same name (a parameter type is reached again from its variable) reads the replacement.
 */
final class FullyQualifiedStrictTypes extends EditRule
{
    private const REGEX_CLASS = '(?:\\\?+' . TypeExpression::REGEX_IDENTIFIER . '(\\\\' . TypeExpression::REGEX_IDENTIFIER . ')*+)';
    private const RESERVED = ['array' => true, 'bool' => true, 'callable' => true, 'false' => true, 'float' => true, 'int' => true, 'iterable' => true,
        'list' => true, 'mixed' => true, 'never' => true, 'null' => true, 'object' => true, 'parent' => true, 'resource' => true, 'self' => true,
        'static' => true, 'string' => true, 'true' => true, 'void' => true];

    private Source $s;
    private string $ns = '';
    /** @var array<string, string> class imports of the namespace: full name => short name */
    private array $uses = [];
    /** @var string[]|null discovered class names (import_symbols' first phase), null outside it */
    private ?array $discovered = null;
    /** @var array<int, array<string, bool>> template / type names declared in docblocks, per brace level */
    private array $reserved = [];
    /** @var array<string, string> */
    private array $forImport = [];
    /** @var array<int, array{0: int, 1: string}> name token range start => [range end, replacement] */
    private array $over = [];
    /** why upstream would throw / a construct that is not modelled; '' = none */
    private string $broken = '';

    /** cache of refreshUsesCache */
    private int $cacheVer = -1;
    /** bumped on every change of $uses / $reserved: the keys of the caches */
    private int $usesVer = 0;
    private int $resVer = 0;
    /** @var array<string, string> determineShortType per name ('' = no change) for the current uses / reserved */
    private array $memo = [];
    private string $memoKey = '';
    /** @var array<int, int> token => start of the replacement that covers it */
    private array $covered = [];
    /** @var array<string, string> */
    private array $byShortLower = [];
    /** @var array<string, string> */
    private array $shortByName = [];
    /** @var array<string, string> */
    private array $shortByNormalized = [];

    /** @param string[] $phpdocTags */
    public function __construct(private bool $leadingBackslash, private bool $importSymbols, private array $phpdocTags, private string $lineEnding) {}

    public function name(): string
    {
        return 'fully_qualified_strict_types';
    }

    public function priority(): int
    {
        return 7;
    }

    public function prepare(Source $s): bool
    {
        for ($t = 0; $t < $s->n; $t++) {
            $k = $s->kind[$t];
            $c = $s->ct[$t];
            if ($c === CT::USE_TRAIT || $k === \__McTokId::T_ATTRIBUTE || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_DOUBLE_COLON
                || $k === \__McTokId::T_EXTENDS || ($k === \__McTokId::T_FUNCTION && $c === 0) || $k === \__McTokId::T_IMPLEMENTS
                || $k === \__McTokId::T_INSTANCEOF || $k === \__McTokId::T_NEW || $k === \__McTokId::T_VARIABLE) {
                return true;
            }
        }
        for ($g = 0; $g <= $s->n; $g++) {
            if (($s->gapFlags[$g] & Gap::G_DOC) !== 0) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $this->over = [];
        $this->covered = [];
        $this->broken = '';
        $scan = NsScan::scan($s);
        $allUses = NsScan::uses($s, true);
        $spaces = [];
        if ($scan->global) {
            $spaces[] = ['', 0, $s->n - 1, -1, 0];
        } else {
            foreach ($scan->name as $k => $nm) {
                $spaces[] = [$nm, $scan->start[$k], $scan->tokEnd[$k], $scan->declEnd[$k], $scan->end[$k]];
            }
        }
        /** @var array<int, string> $inserts gap => text */
        $inserts = [];
        /** @var array<int, int> $splitAt gap => where upstream keeps two whitespace tokens in the inserted text */
        $splitAt = [];
        foreach ($spaces as [$nm, $start, $tokEnd, $declEnd, $gapEnd]) {
            if ($scan->global) {
                $gapEnd = $s->n;
            }
            $this->ns = $nm;
            $this->uses = [];
            $lastUseEnd = -1;
            foreach ($allUses as $u) {
                if ($u[5] < $start || $u[5] > $tokEnd || $u[0] !== NsScan::USE_CLASS) {
                    continue;
                }
                $this->uses[\ltrim($u[1], '\\')] = $u[2];
                $lastUseEnd = $u[6];
            }
            $this->usesVer++;
            $this->forImport = [];
            foreach ($this->importSymbols ? [true, false] : [false] as $discover) {
                $this->discovered = $discover ? [] : null;
                $this->reserved = [];
                $this->resVer++;
                $this->walk($start, $tokEnd, $gapEnd, $discover);
                if ($this->broken !== '') {
                    $s->unsupported = 'fully_qualified_strict_types: not modelled (' . $this->broken . ')';
                    return;
                }
                $this->reserved = [];
                $this->resVer++;
                if ($discover) {
                    $this->setupUsesFromDiscovered();
                }
            }
            if ($this->forImport !== []) {
                $at = $this->importPosition($lastUseEnd, $start, $declEnd, $scan->global);
                $names = \array_values($this->forImport);
                \sort($names);
                $txt = '';
                foreach ($names as $name) {
                    $txt .= $this->lineEnding . 'use ' . $name . ';';
                }
                $g = $s->gapBefore[$at];
                $first = $g === '' ? '' : Gap::pieces($g)[0];
                if ($at >= $s->n + 1 || !($first !== '' && Gap::isSpace($first) && \str_contains($first, "\n"))) {
                    $txt .= $this->lineEnding;
                    if ($first !== '' && Gap::isSpace($first)) {
                        $splitAt[$at] = \strlen($txt); // the inserted line break and the blanks after it stay two whitespace tokens
                    }
                }
                $inserts[$at] = ($inserts[$at] ?? '') . $txt;
            }
        }
        foreach ($this->over as $a => [$b, $text]) {
            $s->edit(Source::tokAt($a), Source::tokAt($b) + 1, $text);
        }
        foreach ($inserts as $g => $txt) {
            $s->edit(Source::gapAt($g), Source::gapAt($g), $txt, $splitAt[$g] ?? -1);
        }
    }

    /** the gap where upstream inserts the imports (in front of the item right after the last import / the declaration) */
    private function importPosition(int $lastUseEnd, int $start, int $declEnd, bool $global): int
    {
        if ($lastUseEnd >= 0) {
            return $lastUseEnd + 1;
        }
        if (!$global && $declEnd !== 0) {
            return $declEnd + 1;
        }
        $first = $start + 1;
        if ($first < $this->s->n && $this->s->kind[$first] === \__McTokId::T_DECLARE) {
            for ($x = $first; $x < $this->s->n; $x++) {
                if ($this->s->kind[$x] === 59) {
                    return $x + 1;
                }
            }
        }
        return $start + 1;
    }

    private function walk(int $start, int $tokEnd, int $gapEnd, bool $discover): void
    {
        $s = $this->s;
        $opened = 0;
        for ($t = $start; $t <= $tokEnd + 1 && $this->broken === ''; $t++) {
            // the trivia before token t (gap t lies in the scope when start < t <= gapEnd)
            if ($t > $start && $t <= $gapEnd && ($s->gapFlags[$t] & Gap::G_COMMENT) !== 0) {
                $this->trivia($t, $opened, $discover);
            }
            if ($t > $tokEnd) {
                break;
            }
            $k = $s->kind[$t];
            $c = $s->ct[$t];
            if ($k === 123 && $c === 0) {
                $opened++;
            } elseif ($k === 125 && $c === 0) {
                if (isset($this->reserved[$opened])) {
                    unset($this->reserved[$opened]);
                    $this->resVer++;
                }
                $opened--;
            } elseif ($k === \__McTokId::T_VARIABLE) {
                if ($t > 0 && Invocation::eff($s, $t - 1) === \__McTokId::T_STRING) {
                    $this->fixPrevName($t);
                }
            } elseif ($k === \__McTokId::T_DOUBLE_COLON) {
                $this->fixPrevName($t);
            } elseif ($k === \__McTokId::T_FUNCTION && $c === 0) {
                $this->fixFunction($t);
            } elseif ($k === \__McTokId::T_ATTRIBUTE) {
                $this->fixAttribute($t);
            } elseif ($k === \__McTokId::T_CATCH) {
                $this->fixCatch($t);
            } elseif ($discover && $c === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                $this->fixNextName($t);
            } elseif ($k === \__McTokId::T_EXTENDS || $k === \__McTokId::T_IMPLEMENTS) {
                $this->fixExtendsImplements($t);
            } elseif ($k === \__McTokId::T_INSTANCEOF || $k === \__McTokId::T_NEW || $c === CT::USE_TRAIT || $c === CT::TYPE_COLON) {
                $this->fixNextName($t);
            }
        }
    }

    /** the comments of gap $g, as upstream reaches them in its walk */
    private function trivia(int $g, int $opened, bool $discover): void
    {
        $s = $this->s;
        $p = Gap::pieces($s->gapBefore[$g]);
        $changed = false;
        foreach ($p as $k => $x) {
            if (Gap::isSpace($x)) {
                continue;
            }
            if (\Fmt\DocRule::isDocPiece($s, $g, $x)) {
                if (\str_contains($x, '@') && Preg::matchAll('/\*\h*@(?:psalm-|phpstan-)?(?:template(?:-covariant|-contravariant)?|(?:import-)?type)\h+('
                    . TypeExpression::REGEX_IDENTIFIER . ')(?!\S)/i', $x, $m) > 0) {
                    foreach ($m[1] as $id) {
                        $this->reserved[$opened + 1][(string) $id] = true;
                        $this->resVer++;
                    }
                }
                $new = $this->fixPhpDoc($x);
                if ($new !== $x) {
                    $p[$k] = $new;
                    $changed = true;
                }
            } elseif ($discover && \str_contains($x, '#[') && Preg::match('/#\[\s*(' . self::REGEX_CLASS . ')/', $x, $m)) {
                $this->determineShortType((string) $m[1]);
            }
        }
        if ($changed) {
            $s->setGap($g, \implode('', $p));
        }
    }

    // ---- names: upstream's string logic ----

    private function refreshUsesCache(): void
    {
        if ($this->cacheVer === $this->usesVer) {
            return;
        }
        $this->cacheVer = $this->usesVer;
        $this->byShortLower = [];
        $this->shortByName = [];
        $this->shortByNormalized = [];
        foreach ($this->uses as $long => $short) {
            $long = (string) $long;
            $this->byShortLower[\strtolower($short)] = $long;
            $this->shortByName[$long] = $short;
            $this->shortByNormalized[self::normalizeFqcn($long)] = $short;
        }
    }

    private function isReservedIdentifier(string $symbol): bool
    {
        if (\str_contains($symbol, '\\')) {
            return false;
        }
        $name = \str_starts_with($symbol, '?') ? \substr($symbol, 1) : $symbol;
        if (isset(self::RESERVED[\strtolower($name)])) {
            return true;
        }
        foreach ($this->reserved as $ids) {
            if (isset($ids[$symbol])) {
                return true;
            }
        }
        return false;
    }

    private function resolveSymbol(string $symbol): string
    {
        if (\str_starts_with($symbol, '\\')) {
            return \substr($symbol, 1);
        }
        if ($this->isReservedIdentifier($symbol)) {
            return $symbol;
        }
        $this->refreshUsesCache();
        $arr = \explode('\\', $symbol, 2);
        $lower = \strtolower($arr[0]);
        if (isset($this->byShortLower[$lower])) {
            return $this->byShortLower[$lower] . (isset($arr[1]) ? '\\' . $arr[1] : '');
        }
        return ($this->ns !== '' ? $this->ns . '\\' : '') . $symbol;
    }

    private function shortenSymbol(string $fqcn): string
    {
        if ($this->isReservedIdentifier($fqcn)) {
            return $fqcn;
        }
        $this->refreshUsesCache();
        $res = null;
        $iMin = 0;
        $ns = $this->ns;
        if (\str_starts_with($fqcn, $ns . '\\')) {
            $tmp = \substr($fqcn, \strlen($ns) + 1);
            if (!isset($this->byShortLower[\strtolower(\explode('\\', $tmp, 2)[0])]) && !$this->isReservedIdentifier($tmp)) {
                $res = $tmp;
                $iMin = \substr_count($ns, '\\') + 1;
            }
        }
        $tmp = $fqcn;
        for ($i = \substr_count($fqcn, '\\'); $i >= $iMin; $i--) {
            if (isset($this->shortByName[$tmp])) {
                $r = $this->shortByName[$tmp] . \substr($fqcn, \strlen($tmp));
                if (!$this->isReservedIdentifier($r)) {
                    $res = $r;
                    break;
                }
            }
            if ($i > 0) {
                $tmp = \substr($tmp, 0, (int) \strrpos($tmp, '\\'));
            }
        }
        if ($res === null) {
            $r = $this->shortByNormalized[self::normalizeFqcn($fqcn)] ?? null;
            if ($r !== null && !$this->isReservedIdentifier($r)) {
                $res = $r;
            }
        }
        if ($res === null) {
            $res = $fqcn;
            if ($ns !== '' || $this->leadingBackslash || isset($this->byShortLower[\strtolower(\explode('\\', $res, 2)[0])])) {
                $res = '\\' . $res;
            }
        }
        return $res;
    }

    private static function normalizeFqcn(string $input): string
    {
        $p = \strrpos($input, '\\');
        if ($p === false) {
            return \strtolower($input);
        }
        return \substr($input, 0, $p + 1) . \strtolower(\substr($input, $p + 1));
    }

    /** determineShortType: the shortened text, or null (no change; or the discovery phase, which only records the name) */
    private function determineShortType(string $typeName): ?string
    {
        if ($this->discovered !== null) {
            if (!$this->isReservedIdentifier($typeName)) {
                $this->discovered[] = $typeName;
            }
            return null;
        }
        $key = $this->usesVer . ':' . $this->resVer;
        if ($key !== $this->memoKey) {
            $this->memoKey = $key;
            $this->memo = [];
        }
        if (isset($this->memo[$typeName])) {
            $r = $this->memo[$typeName];
            return $r === '' ? null : $r;
        }
        $short = $this->shortenSymbol($this->resolveSymbol($typeName));
        $r = $short === $typeName ? '' : $short;
        $this->memo[$typeName] = $r;
        return $r === '' ? null : $r;
    }

    private function setupUsesFromDiscovered(): void
    {
        $ns = $this->ns;
        $syms = $this->discovered ?? [];
        $byShort = [];
        if ($ns === '') {
            foreach ($syms as $sym) {
                if (!\str_starts_with($sym, '\\')) {
                    $first = \explode('\\', \ltrim($sym, '\\'), 2)[0];
                    $byShort[\strtolower($first)] = $this->resolveSymbol($first);
                }
            }
        }
        foreach ($this->uses as $long => $short) {
            $byShort[\strtolower($short)] = (string) $long;
        }
        $useByShortLower = [];
        foreach ($this->uses as $short) {
            $useByShortLower[\strtolower($short)] = true;
        }
        $keys = \array_keys($syms);
        \usort($keys, static function (int $a, int $b) use ($syms): int {
            $x = $syms[$a];
            $y = $syms[$b];
            $r = \str_starts_with($x, '\\') <=> \str_starts_with($y, '\\');
            if ($r !== 0) {
                return $r;
            }
            $r = \substr_count($x, '\\') <=> \substr_count($y, '\\');
            return $r !== 0 ? $r : $a <=> $b;
        });
        foreach ($keys as $key) {
            $sym = $syms[$key];
            while (true) {
                $endLower = \strtolower(\str_contains($sym, '\\') ? \substr($sym, (int) \strrpos($sym, '\\') + 1) : $sym);
                if (!isset($byShort[$endLower])) {
                    $startLower = \strtolower(\explode('\\', \ltrim($sym, '\\'), 2)[0]);
                    if (\str_starts_with($sym, '\\') || ($ns === '' && !isset($useByShortLower[$startLower])) || !\str_contains($sym, '\\')) {
                        $byShort[$endLower] = $this->resolveSymbol($sym);
                        break;
                    }
                }
                if (\str_starts_with($sym, '\\') || $ns === '' || !\str_contains($sym, '\\')) {
                    break;
                }
                $sym = \substr($sym, 0, (int) \strrpos($sym, '\\'));
            }
        }
        foreach ($this->uses as $long => $short) {
            $d = $byShort[\strtolower($short)] ?? null;
            if (\strtolower((string) $d) === \strtolower((string) $long)) {
                unset($byShort[\strtolower($short)]);
            }
        }
        $saved = $this->uses;
        foreach ($byShort as $fqcn) {
            $this->uses = [];
            $this->usesVer++;
            $shortened = \ltrim($this->shortenSymbol($fqcn), '\\');
            $this->uses = $saved;
            $this->usesVer++;
            if (\str_contains($shortened, '\\')) {
                $end = \str_contains($fqcn, '\\') ? \substr($fqcn, (int) \strrpos($fqcn, '\\') + 1) : $fqcn;
                $saved[$fqcn] = $end;
                $this->uses = $saved;
                $this->usesVer++;
                $this->forImport[$end] = $fqcn;
            }
        }
        \ksort($this->forImport, \SORT_NATURAL);
    }

    // ---- token walks (code tokens: prev / next meaningful = -1 / +1) ----

    /** generatePartialCode of a name range, with an earlier replacement of the same range applied */
    private function content(int $a, int $b): ?string
    {
        if (isset($this->over[$a])) {
            if ($this->over[$a][0] !== $b) {
                $this->broken = 'a name range overlapping an earlier replacement';
                return null;
            }
            return $this->over[$a][1];
        }
        for ($x = $a; $x <= $b; $x++) {
            if (isset($this->covered[$x])) {
                $this->broken = 'a name range overlapping an earlier replacement';
                return null;
            }
        }
        return NsScan::partial($this->s, $a, $b);
    }

    /** shortenClassIfPossible: upstream's token count change (overrideRange: a shorter name leaves cleared tokens, so < 0 is a shrink) */
    private function shortenRange(int $a, int $b): int
    {
        $c = $this->content($a, $b);
        if ($c === null) {
            return 0;
        }
        $new = $this->determineShortType($c);
        if ($new === null) {
            return 0;
        }
        $this->over[$a] = [$b, $new];
        for ($x = $a; $x <= $b; $x++) {
            $this->covered[$x] = $a;
        }
        return \count(self::nameTexts($new)) - ($b - $a + 1);
    }

    private function isName(int $t): bool
    {
        $e = Invocation::eff($this->s, $t);
        return $e === \__McTokId::T_STRING || $e === \__McTokId::T_NS_SEPARATOR;
    }

    private function fixPrevName(int $t): void
    {
        $start = -1;
        $end = -1;
        while (true) {
            $t--;
            if ($t < 0) {
                return;
            }
            $e = Invocation::eff($this->s, $t);
            if ($e === \__McTokId::T_OBJECT_OPERATOR || $e === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
                return;
            }
            if ($e === \__McTokId::T_STRING || $e === \__McTokId::T_NS_SEPARATOR) {
                $start = $t;
                if ($end < 0) {
                    $end = $t;
                }
            } else {
                if ($end >= 0) {
                    $this->shortenRange($start, $end);
                }
                return;
            }
        }
    }

    private function fixNextName(int $t): void
    {
        $start = -1;
        $end = -1;
        $n = $this->s->n;
        while (true) {
            $t++;
            if ($t < $n && $this->isName($t)) {
                if ($start < 0) {
                    $start = $t;
                }
                $end = $t;
            } else {
                if ($start >= 0) {
                    $this->shortenRange($start, $end);
                }
                return;
            }
        }
    }

    private function fixExtendsImplements(int $t): void
    {
        $s = $this->s;
        $isExtends = $s->kind[$t] === \__McTokId::T_EXTENDS;
        $t++;
        $start = -1;
        $end = -1;
        while ($t < $s->n) {
            $k = $s->kind[$t];
            $brace = $k === 123 && $s->ct[$t] === 0;
            if (($k === 44 && $s->ct[$t] === 0) || $brace || $k === \__McTokId::T_IMPLEMENTS) {
                if ($start >= 0) {
                    $this->shortenRange($start, $end);
                }
                $start = -1;
                if ($brace || ($isExtends && $k === \__McTokId::T_IMPLEMENTS)) {
                    return;
                }
            } else {
                if ($start < 0) {
                    $start = $t;
                }
                $end = $t;
            }
            $t++;
        }
        $this->broken = 'extends / implements without a body (upstream throws)';
    }

    private function fixCatch(int $t): void
    {
        $s = $this->s;
        $t += 2;
        $start = -1;
        $end = -1;
        while ($t < $s->n) {
            $k = $s->kind[$t];
            $close = $k === 41 && $s->ct[$t] === 0;
            if ($close || $k === \__McTokId::T_VARIABLE || $s->ct[$t] === CT::TYPE_ALTERNATION) {
                if ($start < 0) {
                    return;
                }
                if ($this->shortenRange($start, $end) < 0) {
                    // upstream moves its index back by the shrink (the tokens were only cleared), steps onto the same separator
                    // again with no type started and stops: the later types of the catch are left to the `$e` variable's walk
                    return;
                }
                $start = -1;
                if ($close) {
                    return;
                }
            } else {
                if ($start < 0) {
                    $start = $t;
                }
                $end = $t;
            }
            $t++;
        }
        $this->broken = 'catch without its closing parenthesis (upstream throws)';
    }

    /**
     * AttributeAnalyzer::collectOne + upstream's loop over its attributes, on a virtual copy of upstream's token list (code tokens and
     * trivia pieces, earlier replacements applied): the attribute starts are collected first, so after a name of the group changed
     * length a later start is a stale index, which upstream follows wherever it lands (mirrored).
     */
    private function fixAttribute(int $t): void
    {
        $s = $this->s;
        $close = $s->pair[$t];
        if ($close < 0) {
            $this->broken = 'an attribute without its end (upstream throws)';
            return;
        }
        $limit = \min($s->n - 1, $close + ($close - $t) + 4);
        $ft = new FlatTokens();
        $ft->load($s, $t, $limit);
        /** @var int[] $kind */
        $kind = [];
        /** @var string[] $text */
        $text = [];
        /** @var int[] $src source token of an original code item, -1 trivia, -2 a token of a replacement */
        $src = [];
        $m = $ft->count();
        for ($q = 0; $q < $m; $q++) {
            $tok = $ft->i[$q];
            if ($tok < 0) {
                $kind[] = -1;
                $text[] = $ft->c[$q];
                $src[] = -1;
                continue;
            }
            if (isset($this->over[$tok])) {
                $end = $this->over[$tok][0];
                $parts = self::nameTexts($this->over[$tok][1]);
                foreach ($parts as $nt) {
                    $kind[] = $nt === '\\' ? \__McTokId::T_NS_SEPARATOR : \__McTokId::T_STRING;
                    $text[] = $nt;
                    $src[] = -2;
                }
                for ($pad = \count($parts); $pad < $end - $tok + 1; $pad++) {
                    $kind[] = -1; // overrideRange clears the tokens a shorter name does not need
                    $text[] = '';
                    $src[] = -1;
                }
                while ($q + 1 < $m && $ft->i[$q + 1] >= 0 && $ft->i[$q + 1] <= $end) {
                    $q++;
                }
                continue;
            }
            $kind[] = Invocation::eff($s, $tok);
            $text[] = $s->text($tok);
            $src[] = $tok;
        }
        // collectAttributes on the list as it is now
        $closeQ = -1;
        $depth = 0;
        foreach ($kind as $q => $k) {
            if ($k === \__McTokId::T_ATTRIBUTE) {
                $depth++;
            } elseif ($k === CT::ATTRIBUTE_CLOSE) {
                $depth--;
                if ($depth === 0) {
                    $closeQ = $q;
                    break;
                }
            }
        }
        if ($closeQ < 0) {
            $this->broken = 'an attribute without its end (upstream throws)';
            return;
        }
        $starts = [];
        $index = 0;
        do {
            $starts[] = $index + 1;
            $index = self::nextOfKinds($kind, $index + 1, true);
            if ($index < 0) {
                $this->broken = 'an attribute element without its end (upstream throws)';
                return;
            }
            if ($kind[$index] === 40) {
                $d = 0;
                for ($x = $index; $x < \count($kind); $x++) {
                    if ($kind[$x] === 40) {
                        $d++;
                    } elseif ($kind[$x] === 41 && --$d === 0) {
                        break;
                    }
                }
                $index = self::nextOfKinds($kind, $x, false);
                if ($index < 0) {
                    $this->broken = 'an attribute element without its end (upstream throws)';
                    return;
                }
            }
            $next = $index;
            if ($next < $closeQ) {
                $next = self::meaningful($kind, $index, 1);
            }
        } while ($next >= 0 && $next < $closeQ);
        // fixAttribute
        foreach ($starts as $start) {
            $idx = $start;
            if ($idx >= \count($kind)) {
                $this->broken = 'a stale attribute index past the attribute window';
                return;
            }
            while ($idx >= 0 && ($kind[$idx] === \__McTokId::T_STRING || $kind[$idx] === \__McTokId::T_NS_SEPARATOR)) {
                $idx = self::meaningful($kind, $idx, -1);
            }
            // fixNextName
            $a = -1;
            $b = -1;
            for ($j = self::meaningful($kind, $idx, 1); $j >= 0 && ($kind[$j] === \__McTokId::T_STRING || $kind[$j] === \__McTokId::T_NS_SEPARATOR); $j = self::meaningful($kind, $j, 1)) {
                if ($a < 0) {
                    $a = $j;
                }
                $b = $j;
            }
            if ($a < 0) {
                continue;
            }
            $content = '';
            for ($x = $a; $x <= $b; $x++) {
                $content .= $text[$x];
            }
            $new = $this->determineShortType($content);
            if ($new === null) {
                continue;
            }
            // map the item range back to source tokens
            $srcA = -1;
            $srcB = -1;
            $fresh = true;
            for ($x = $a; $x <= $b; $x++) {
                if ($src[$x] === -2) {
                    $fresh = false;
                } elseif ($src[$x] >= 0) {
                    $srcA = $srcA < 0 ? $src[$x] : $srcA;
                    $srcB = $src[$x];
                }
            }
            if ($fresh) {
                if ($srcA < 0) {
                    $this->broken = 'a replaced attribute name that maps to no token';
                    return;
                }
                $this->over[$srcA] = [$srcB, $new];
                for ($x = $srcA; $x <= $srcB; $x++) {
                    $this->covered[$x] = $srcA;
                }
            } else {
                // a replacement made earlier: only the very same range is modelled
                $found = false;
                foreach ($this->over as $oa => [$ob, $old]) {
                    if ($old === $content && $srcA < 0) {
                        $this->over[$oa] = [$ob, $new];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $this->broken = 'an attribute name over part of an earlier replacement';
                    return;
                }
            }
            // the items a..b become the new name's tokens (rebuilt by hand: an array_splice of $kind made the earlier
            // nextOfKinds($kind, ..) read wrong values natively, compiler issue #370)
            $k2 = [];
            $t2 = [];
            $s2 = [];
            $cnt = \count($kind);
            for ($x = 0; $x < $cnt; $x++) {
                if ($x === $a) {
                    $parts = self::nameTexts($new);
                    foreach ($parts as $tx) {
                        $k2[] = $tx === '\\' ? \__McTokId::T_NS_SEPARATOR : \__McTokId::T_STRING;
                        $t2[] = $tx;
                        $s2[] = -2;
                    }
                    for ($pad = \count($parts); $pad < $b - $a + 1; $pad++) {
                        $k2[] = -1;
                        $t2[] = '';
                        $s2[] = -1;
                    }
                }
                if ($x >= $a && $x <= $b) {
                    continue;
                }
                $k2[] = $kind[$x];
                $t2[] = $text[$x];
                $s2[] = $src[$x];
            }
            $kind = $k2;
            $text = $t2;
            $src = $s2;
        }
    }

    /**
     * namespacedStringToTokens, as the token texts (a `\` is a T_NS_SEPARATOR, any other part a T_STRING). Not [kind, text] pairs:
     * an int list fed from such a shape compares unequal to its own values natively (compiler issue #369).
     * @return string[]
     */
    private static function nameTexts(string $name): array
    {
        $out = [];
        if (\str_starts_with($name, '\\')) {
            $out[] = '\\';
            $name = \substr($name, 1);
        }
        $parts = \explode('\\', $name);
        foreach ($parts as $k => $part) {
            $out[] = $part;
            if ($k !== \count($parts) - 1) {
                $out[] = '\\';
            }
        }
        return $out;
    }

    /** @param int[] $kind the next code item after $q (dir 1) or before it (dir -1), -1 when none */
    private static function meaningful(array $kind, int $q, int $dir): int
    {
        $m = \count($kind);
        for ($j = $q + $dir; $j >= 0 && $j < $m; $j += $dir) {
            if ($kind[$j] !== -1) {
                return $j;
            }
        }
        return -1;
    }

    /**
     * getNextTokenOfKind after $q: `(` / `,` / the attribute close ($paren), or `,` / the attribute close
     * @param int[] $kind
     */
    private static function nextOfKinds(array $kind, int $q, bool $paren): int
    {
        $m = \count($kind);
        for ($j = $q + 1; $j < $m; $j++) {
            $k = $kind[$j];
            if (($paren && $k === 40) || $k === 44 || $k === CT::ATTRIBUTE_CLOSE) {
                return $j;
            }
        }
        return -1;
    }

    private function fixFunction(int $t): void
    {
        $s = $this->s;
        $n = $s->n;
        $open = $t + 1;
        while ($open < $n && !($s->kind[$open] === 40 && $s->ct[$open] === 0)) {
            $open++;
        }
        if ($open >= $n || $s->pair[$open] < 0) {
            $this->broken = 'a function without its parameter list (upstream throws)';
            return;
        }
        $close = $s->pair[$open];
        /** @var array<string, int[]> $args name => [type start, type end] (-1: no type) */
        $args = [];
        foreach ($this->arguments($open, $close) as [$a, $b]) {
            [$name, $ts, $te] = $this->argumentInfo($a, $b);
            $args[$name] = [$ts, $te];
        }
        foreach ($args as [$ts, $te]) {
            if ($ts >= 0) {
                $this->replaceByShortType($ts, $te);
            }
        }
        // getFunctionReturnType
        $x = $close + 1;
        if ($x < $n && $s->ct[$x] === CT::USE_LAMBDA) {
            $uo = $x + 1;
            while ($uo < $n && !($s->kind[$uo] === 40 && $s->ct[$uo] === 0)) {
                $uo++;
            }
            if ($uo >= $n || $s->pair[$uo] < 0) {
                $this->broken = 'a closure use list without its end (upstream throws)';
                return;
            }
            $x = $s->pair[$uo] + 1;
        }
        if ($x >= $n || $s->ct[$x] !== CT::TYPE_COLON) {
            return;
        }
        $ts = $x + 1;
        $body = $ts;
        while ($body < $n && !(($s->kind[$body] === 123 && $s->ct[$body] === 0) || $s->kind[$body] === 59 || $s->kind[$body] === \__McTokId::T_DOUBLE_ARROW)) {
            $body++;
        }
        $te = $body - 1 >= $ts ? $body - 1 : $ts;
        $this->replaceByShortType($ts, $te);
    }

    /**
     * ArgumentsAnalyzer::getArguments: [start, end] code-token ranges of the top-level arguments
     * @return array<int, int[]>
     */
    private function arguments(int $open, int $close): array
    {
        $s = $this->s;
        $out = [];
        if ($open + 1 === $close) {
            return $out;
        }
        $start = $open + 1;
        for ($x = $open + 1; $x < $close; $x++) {
            $p = $s->pair[$x];
            if ($p > $x) {
                $x = $p;
                continue;
            }
            if ($s->kind[$x] === 44 && $s->ct[$x] === 0) {
                if ($x + 1 === $close) {
                    break;
                }
                $out[] = [$start, $x - 1];
                $start = $x + 1;
            }
        }
        $out[] = [$start, $x - 1];
        return $out;
    }

    /** @return array{0: string, 1: int, 2: int} name ('' = none), type start, type end (-1 when no type / no name) */
    private function argumentInfo(int $a, int $b): array
    {
        $s = $this->s;
        $name = '';
        $saw = false;
        $ts = -1;
        $te = -1;
        for ($x = $a; $x <= $b; $x++) {
            $k = $s->kind[$x];
            $c = $s->ct[$x];
            if ($k === \__McTokId::T_ATTRIBUTE || $c === CT::PROPERTY_HOOK_BRACE_OPEN) {
                $x = $s->pair[$x] >= 0 ? $s->pair[$x] : $x;
                continue;
            }
            if ($k === \__McTokId::T_ELLIPSIS || ($k === \__McTokId::T_FINAL && $c === 0) || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC
                || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE || $k === \__McTokId::T_READONLY
                || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET
                || $k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG) {
                continue;
            }
            if ($k === \__McTokId::T_VARIABLE) {
                if ($saw) {
                    continue;
                }
                $saw = true;
                $name = $s->text($x);
                continue;
            }
            if ($k === 61) {
                continue;
            }
            if (!$saw) {
                if ($ts < 0) {
                    $ts = $x;
                }
                $te = $x;
            }
        }
        if (!$saw) {
            return ['', -1, -1];
        }
        return [$name, $ts, $te];
    }

    private function replaceByShortType(int $ts, int $te): void
    {
        $s = $this->s;
        if ($s->ct[$ts] === CT::NULLABLE_TYPE) {
            $ts++;
        }
        // getTypes: the name runs between `|`, `&` and DNF parentheses
        $start = -1;
        $end = -1;
        for ($x = $ts; ; $x++) {
            if ($x <= $te && $s->ct[$x] === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_OPEN) {
                $start = -1;
                continue;
            }
            $sep = $x > $te || $s->ct[$x] === CT::TYPE_ALTERNATION || $s->ct[$x] === CT::TYPE_INTERSECTION
                || $s->ct[$x] === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE;
            if ($sep) {
                if ($start >= 0) {
                    $this->shortenRange($start, $end);
                }
                $start = -1;
                if ($x >= $te) {
                    return;
                }
                continue;
            }
            if ($start < 0) {
                $start = $x;
            }
            $end = $x;
        }
    }

    // ---- phpdoc ----

    private function fixPhpDoc(string $doc): string
    {
        if ($this->phpdocTags === [] || !\str_contains($doc, '@')) {
            return $doc;
        }
        $tags = $this->phpdocTags;
        if ($this->discovered === null) {
            // the doc changes only if one of its names shortens: every class-like word is a candidate (a superset of the types the
            // regex below reaches), determineShortType is memoized, and the ~10 us regex runs only for a doc that has one
            \preg_match_all('/\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*/', $doc, $w);
            $need = false;
            $this->refreshUsesCache();
            // in a namespace (no leading-backslash option) an unqualified word can only change through an import: its short name, or
            // an alias of the namespace's own class (shortenSymbol); any other one keeps its spelling
            $quick = $this->ns !== '' && !$this->leadingBackslash;
            foreach ($w[0] as $name) {
                $name = (string) $name;
                if ($quick && !\str_contains($name, '\\') && !isset($this->byShortLower[\strtolower($name)])
                    && !isset($this->shortByName[$this->ns . '\\' . $name])) {
                    continue;
                }
                if ($this->determineShortType($name) !== null) {
                    $need = true;
                    break;
                }
            }
            if (!$need) {
                return $doc;
            }
        }
        $new = Preg::replaceCallback('/([*{]\h*@)(\S+)(\h+)(' . TypeExpression::REGEX_TYPES . ')(?!(?!\})\S)/', function (array $m) use ($tags): string {
            if (!\in_array((string) $m[2], $tags, true)) {
                return (string) $m[0];
            }
            return $m[1] . $m[2] . $m[3] . $this->fixPhpDocType((string) $m[4]);
        }, $doc);
        if (\in_array('see', $tags, true) && \str_contains($new, '@see')) {
            $new = Preg::replaceCallback(
                '/([*{]\h*)@see(\h+)(' . self::REGEX_CLASS . ')(::(?:\$\w+|\w+\(\)))(?!(?!\})\S)/',
                fn(array $m): string => $m[1] . '@see' . $m[2] . $this->fixPhpDocType((string) $m[3]) . $m[5],
                $new,
            );
        }
        return $new;
    }

    private function fixPhpDocType(string $type): string
    {
        return (new TypeExpression($type))->mapTypes(function (TypeExpression $t): TypeExpression {
            $v = $t->toString();
            if ($t->isCompositeType() || !Preg::test('/^' . self::REGEX_CLASS . '$/', $v) || $v === 'min' || $v === 'max') {
                return $t;
            }
            $short = $this->determineShortType($v);
            if ($short === null || $short === $v) {
                return $t;
            }
            return new TypeExpression($short);
        })->toString();
    }
}
