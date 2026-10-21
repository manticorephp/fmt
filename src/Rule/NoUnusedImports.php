<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\NoUnusedImportsFixer (priority -10). Per namespace, an import whose short name is not used (a
 * T_STRING of the import's kind outside the imports, anything inside an attribute, a word in a comment) is removed, then every
 * non-aliased import of a class in the namespace itself. The decision runs on the Source: one pass per namespace indexes the T_STRING
 * occurrences by lowercase name and collects the comments, so an import costs a lookup. A file that loses an import runs upstream's
 * token surgery (removeUseDeclaration and its clean-ups, order and repeats included) on a whole-file FlatTokens and is replaced by its
 * rendering; no item is ever inserted there, so the upstream indexes stay the load-time ones.
 */
final class NoUnusedImports extends EditRule
{
    private const TYPE_CLASS = 1;
    private const TYPE_FUNCTION = 2;
    private const TYPE_CONST = 3;

    private ?Source $s = null;
    private FlatTokens $t;
    private bool $hasAttr = false;
    private bool $hasEnum = false;
    /**
     * index(): T_STRING occurrences per lowercase name (token index * 2 + 1 when inside an attribute). Properties, not a returned tuple:
     * int lists vivified in a returned tuple read back as boxed words (compiler issue #365).
     * @var array<string, int[]>
     */
    private array $by = [];
    /** @var array<int, array{0:string,1:string}> comment text, lowercase first namespace segment when it sits in the header after it */
    private array $comments = [];
    /** the comments lowercased, each followed by "\0": one strpos finds the comments an import's name occurs in */
    private string $blob = '';
    /** @var int[] offset of each comment in blob */
    private array $cAt = [];

    public function __construct()
    {
        $this->t = new FlatTokens();
    }

    public function name(): string
    {
        return 'no_unused_imports';
    }

    public function priority(): int
    {
        return -10;
    }

    private function eff(int $i): int
    {
        $s = $this->s;
        return $s->ct[$i] !== 0 ? $s->ct[$i] : $s->kind[$i];
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $n = $s->n;
        $uses = [];
        $this->hasAttr = false;
        $this->hasEnum = false;
        for ($i = 0; $i < $n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_USE && $s->ct[$i] === 0) {
                $uses[] = $i;
            } elseif ($k === \__McTokId::T_ATTRIBUTE) {
                $this->hasAttr = true;
            } elseif ($k === \__McTokId::T_ENUM) {
                $this->hasEnum = true;
            }
        }
        if ($uses === []) {
            return;
        }
        /** @var array<int, array{0:int,1:string,2:string,3:bool,4:bool,5:int,6:int,7:int,8:int}> $decls type, fullName, shortName, aliased, multi, start, end, chunkStart, chunkEnd */
        $decls = [];
        foreach ($uses as $u) {
            $end = $u + 1;
            while ($end < $n && !(($s->kind[$end] === 59 && $s->ct[$end] === 0) || $s->kind[$end] === \__McTokId::T_CLOSE_TAG)) {
                $end++;
            }
            if ($end >= $n) {
                return; // upstream reads past the end
            }
            foreach ($this->parseDeclarations($u, $end) as $d) {
                $decls[] = $d;
            }
        }
        if ($decls === []) {
            return;
        }
        $plan = [];
        foreach ($this->namespaces() as $ns) {
            $cur = [];
            $ignored = [];
            foreach ($decls as $d) {
                if ($d[5] >= $ns[1] && $d[6] <= $ns[2]) {
                    $cur[] = $d;
                    $ignored[$d[5]] = $d[6];
                }
            }
            if ($cur === []) {
                continue;
            }
            $this->index($ns, $ignored);
            $remove = [];
            foreach ($cur as $d) {
                if (!$this->isUsed($d)) {
                    $remove[] = $d;
                }
            }
            $same = [];
            $nsName = $ns[0];
            foreach ($cur as $d) {
                if ($d[3]) {
                    continue;
                }
                $full = \ltrim($d[1], '\\');
                if (\str_starts_with($full, $nsName . '\\') && !\str_contains(\substr($full, \strlen($nsName . '\\')), '\\')) {
                    $same[] = $d;
                }
            }
            if ($remove !== [] || $same !== []) {
                $plan[] = [$remove, $same];
            }
        }
        if ($plan === []) {
            return;
        }
        $t = $this->t;
        // the surgery stays around the imports (a few tokens of margin for the neighbour scans); only imports at the very end of the file reach its trailing whitespace
        $lo = $n;
        $hi = 0;
        foreach ($decls as $d) {
            $lo = \min($lo, $d[5]);
            $hi = \max($hi, $d[6]);
        }
        $wa = \max(0, $lo - 8);
        $wb = \min($n - 1, $hi + 8);
        $tail = $wb === $n - 1;
        $t->load($s, $wa, $wb, $tail);
        foreach ($plan as $p) {
            foreach ($p[0] as $d) {
                $this->removeDeclaration($d, false);
            }
            foreach ($p[1] as $d) {
                $this->removeDeclaration($d, false);
            }
        }
        // upstream's removeLineIfEmpty skips single-character tokens: the last chunk of a multi-line `use A,\n B,\n C;` takes the `;` with it,
        // and upstream's lint step then leaves the file alone
        foreach ($decls as $d) {
            $e = $this->p($d[6]);
            if ($s->kind[$d[6]] !== 59 || $t->isCode($e)) {
                continue;
            }
            for ($q = $this->p($d[5]); $q < $e; $q++) {
                if ($t->isCode($q)) {
                    $s->abort = true;
                    return;
                }
            }
        }
        $t->emit($s, $wa, $wb, $tail);
    }

    // ---- NamespaceUsesAnalyzer::getDeclarations (allowMultiUses), on the Source ----

    /** @return array<int, array{0:int,1:string,2:string,3:bool,4:bool,5:int,6:int,7:int,8:int}> */
    private function parseDeclarations(int $start, int $end): array
    {
        $s = $this->s;
        $n = $s->n;
        $ti = $this->eff($start + 1);
        $type = $ti === CT::FUNCTION_IMPORT ? self::TYPE_FUNCTION : ($ti === CT::CONST_IMPORT ? self::TYPE_CONST : self::TYPE_CLASS);
        $multi = false;
        for ($j = $start + 1; $j < $end; $j++) {
            if (($s->kind[$j] === 44 && $s->ct[$j] === 0) || $s->ct[$j] === CT::GROUP_IMPORT_BRACE_OPEN) {
                $multi = true;
                break;
            }
        }
        $index = $start + 1;
        while ($index < $n && !($s->ct[$index] === 0 && ($s->kind[$index] === \__McTokId::T_STRING || $s->kind[$index] === \__McTokId::T_NS_SEPARATOR))) {
            $index++;
        }
        $out = [];
        while ($index < $n && $index <= $end) {
            $q = $this->qualifiedName($index);
            $after = $q[3];
            if ($after >= $n) {
                break;
            }
            if ($s->ct[$after] === CT::GROUP_IMPORT_BRACE_OPEN) {
                $groupIndex = $after;
                $groupEnd = $s->pair[$after];
                while ($groupIndex < $groupEnd) {
                    $chunkStart = $groupIndex + 1;
                    if ($s->ct[$chunkStart] === CT::GROUP_IMPORT_BRACE_CLOSE) {
                        break;
                    }
                    $g = $this->qualifiedName($chunkStart);
                    $out[] = [$type, $q[0] . $g[0], $g[1], $g[2], true, $start, $end, $chunkStart, $g[3] - 1];
                    $groupIndex = $g[3];
                }
                $index = $groupIndex;
            } elseif (($s->ct[$after] === 0 && ($s->kind[$after] === 44 || $s->kind[$after] === 59)) || $s->kind[$after] === \__McTokId::T_CLOSE_TAG) {
                $prev = $after - 1;
                if ($s->ct[$prev] !== CT::GROUP_IMPORT_BRACE_CLOSE) {
                    $out[] = [$type, $q[0], $q[1], $q[2], $multi, $start, $end, $multi ? $index : -1, $multi ? $prev : -1];
                }
                $index = $after;
            }
            $index++;
        }
        return $out;
    }

    /** @return array{0:string,1:string,2:bool,3:int} fullName, shortName, aliased, index after */
    private function qualifiedName(int $index): array
    {
        $s = $this->s;
        $full = '';
        $short = '';
        $aliased = false;
        $n = $s->n;
        for (; $index < $n; $index++) {
            $k = $s->kind[$index];
            $c = $s->ct[$index];
            if ($c === 0 && $k === \__McTokId::T_STRING) {
                $short = $s->text($index);
                if (!$aliased) {
                    $full .= $short;
                }
            } elseif ($c === 0 && $k === \__McTokId::T_NS_SEPARATOR) {
                $full .= '\\';
            } elseif ($c === 0 && $k === \__McTokId::T_AS) {
                $aliased = true;
            } elseif (($c === 0 && ($k === 44 || $k === 59)) || $c === CT::GROUP_IMPORT_BRACE_OPEN || $c === CT::GROUP_IMPORT_BRACE_CLOSE
                || $k === \__McTokId::T_CLOSE_TAG) {
                break;
            }
        }
        return [$full, $short, $aliased, $index];
    }

    // ---- NamespacesAnalyzer::getDeclarations ----

    /**
     * @return array<int, array{0:string,1:int,2:int,3:bool}> name, scope start token, scope last token, whether the gap after that token
     *         (up to the next namespace / the end of the file) belongs to the scope too
     */
    private function namespaces(): array
    {
        $s = $this->s;
        $n = $s->n;
        $out = [];
        for ($i = 1; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_NAMESPACE || $s->ct[$i] !== 0) {
                continue;
            }
            $d = $i + 1;
            while ($d < $n && !(($s->kind[$d] === 59 || $s->kind[$d] === 123) && $s->ct[$d] === 0)) {
                $d++;
            }
            if ($d >= $n) {
                break;
            }
            $name = '';
            for ($q = $i + 1; $q < $d; $q++) {
                $name .= $s->gapBefore[$q] . $s->text($q);
            }
            $name = \trim($name . $s->gapBefore[$d]);
            if ($s->kind[$d] === 123) {
                $end = $s->pair[$d];
                $out[] = [$name, $i, $end, false];
                $i = $end;
                continue;
            }
            $nx = $d + 1;
            while ($nx < $n && !($s->kind[$nx] === \__McTokId::T_NAMESPACE && $s->ct[$nx] === 0)) {
                $nx++;
            }
            $out[] = [$name, $i, $nx - 1, true];
            $i = $nx - 1;
        }
        if ($out === []) {
            $open = $n > 0 && $s->kind[0] === \__McTokId::T_INLINE_HTML ? 1 : 0;
            $out[] = ['', $open, $n - 1, true];
        }
        return $out;
    }

    // ---- isImportUsed ----

    /**
     * One pass over the namespace scope (imports of this namespace skipped): T_STRING occurrences by lowercase name with whether they sit in
     * an attribute, and the comments (with the lowercase first segment of the namespace name when the comment is in the header after it).
     * @param array{0:string,1:int,2:int,3:bool} $ns
     * @param array<int, int> $ignored
     */
    private function index(array $ns, array $ignored): void
    {
        $s = $this->s;
        $this->by = [];
        $comments = [];
        $inAttr = false;
        $header = -1;
        $headerSeg = '';
        $last = $ns[2];
        for ($i = $ns[1]; $i <= $last + 1; $i++) {
            if ($i > $ns[1] && ($i <= $last || $ns[3]) && $i <= $s->n && ($s->gapFlags[$i] & Gap::G_COMMENT) !== 0) {
                $g = $s->gapBefore[$i];
                if ($g !== '') {
                    foreach (Gap::pieces($g) as $p) {
                        if (!Gap::isSpace($p)) {
                            $comments[] = [$p, $i <= $header ? $headerSeg : ''];
                        }
                    }
                }
            }
            if ($i > $last || $i >= $s->n) {
                break;
            }
            $k = $s->kind[$i];
            $c = $s->ct[$i];
            if ($k === \__McTokId::T_ATTRIBUTE) {
                $inAttr = true;
                continue;
            }
            if ($c === CT::ATTRIBUTE_CLOSE) {
                $inAttr = false;
                continue;
            }
            if (isset($ignored[$i])) {
                $i = $ignored[$i];
                continue;
            }
            if ($k === \__McTokId::T_STRING && $c === 0) {
                if ($i > 0 && $s->kind[$i - 1] === \__McTokId::T_NAMESPACE && $s->ct[$i - 1] === 0) {
                    // upstream skips to the end of the header when this name is the import's: comments up to there do not count for it
                    $header = $i + 1;
                    while ($header < $s->n && !((($s->kind[$header] === 59 || $s->kind[$header] === 123) && $s->ct[$header] === 0) || $s->kind[$header] === \__McTokId::T_CLOSE_TAG)) {
                        $header++;
                    }
                    $headerSeg = \strtolower($s->text($i));
                }
                $this->by[\strtolower($s->text($i))][] = $i * 2 + ($inAttr ? 1 : 0);
            }
        }
        $this->comments = $comments;
        $blob = '';
        $at = [];
        foreach ($comments as $cm) {
            $at[] = \strlen($blob);
            $blob .= \strtolower($cm[0]) . "\0";
        }
        $this->blob = $blob;
        $this->cAt = $at;
    }

    /**
     * @param array{0:int,1:string,2:string,3:bool,4:bool,5:int,6:int,7:int,8:int} $d
     */
    private function isUsed(array $d): bool
    {
        $short = $d[2];
        $low = \strtolower($short);
        foreach ($this->by[$low] ?? [] as $occ) {
            if ($this->qualifies($d, $occ >> 1, ($occ & 1) === 1)) {
                return true;
            }
        }
        $re = '/(?<![[:alnum:]\$_])(?<!\\\)' . $short . '(?![[:alnum:]_])/i';
        $nc = \count($this->cAt);
        $p = \strpos($this->blob, $low);
        while ($p !== false) {
            // the comment holding offset p (the names hold no "\0", so a match never spans two)
            $lo = 0;
            $hi = $nc - 1;
            while ($lo < $hi) {
                $mid = ($lo + $hi + 1) >> 1;
                if ($this->cAt[$mid] <= $p) {
                    $lo = $mid;
                } else {
                    $hi = $mid - 1;
                }
            }
            $p = $lo + 1 < $nc ? \strpos($this->blob, $low, $this->cAt[$lo + 1]) : false;
            $cm = $this->comments[$lo];
            if ($cm[1] !== '' && $cm[1] === $low) {
                continue;
            }
            $r = @\preg_match($re . 'u', $cm[0]);
            if ($r === false) {
                $r = @\preg_match($re, $cm[0]);
            }
            if ($r === 1) {
                return true;
            }
        }
        return false;
    }

    /** one T_STRING occurrence of the import's short name: does it use the import? */
    private function qualifies(array $d, int $i, bool $inAttr): bool
    {
        $s = $this->s;
        $n = $s->n;
        $pk = $i > 0 ? $this->eff($i - 1) : -1;
        if ($pk === \__McTokId::T_NAMESPACE) {
            return false;
        }
        if ($pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_FUNCTION || $pk === \__McTokId::T_DOUBLE_COLON
            || $pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
            return false;
        }
        $nk = $i + 1 < $n ? $this->eff($i + 1) : -1;
        if ($pk === \__McTokId::T_CONST && $nk === 61) {
            return false;
        }
        if ($inAttr) {
            return true;
        }
        if ($nk === 58 && $this->gotoLabel($i + 1)) {
            return false;
        }
        if ($this->isConstantInvocation($i)) {
            $type = self::TYPE_CONST;
        } elseif ($nk === 40 && $pk !== \__McTokId::T_NEW && $pk !== \__McTokId::T_ATTRIBUTE) {
            $type = self::TYPE_FUNCTION;
        } else {
            $type = self::TYPE_CLASS;
        }
        return $d[0] === $type;
    }

    /** GotoLabelAnalyzer::belongsToGoToLabel for the `:` at $i */
    private function gotoLabel(int $i): bool
    {
        $s = $this->s;
        if ($i < 2 || $this->eff($i) !== 58 || $this->eff($i - 1) !== \__McTokId::T_STRING) {
            return false;
        }
        $p = $this->eff($i - 2);
        return $p === 58 || $p === 59 || $p === 123 || $p === 125 || $p === \__McTokId::T_OPEN_TAG;
    }

    private static function isClassy(int $k): bool
    {
        return $k === \__McTokId::T_CLASS || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_ENUM;
    }

    /** TokensAnalyzer::isConstantInvocation for the T_STRING at $i */
    private function isConstantInvocation(int $i): bool
    {
        $s = $this->s;
        $n = $s->n;
        $nx = $i + 1;
        $nk = $nx < $n ? $this->eff($nx) : -1;
        if ($nk === 40 || $nk === 123 || $nk === \__McTokId::T_DOUBLE_COLON || $nk === \__McTokId::T_ELLIPSIS || $nk === \__McTokId::T_NS_SEPARATOR
            || $nk === CT::RETURN_REF || $nk === CT::TYPE_ALTERNATION || $nk === CT::TYPE_INTERSECTION || $nk === \__McTokId::T_VARIABLE) {
            return false;
        }
        $pv = $i - 1;
        if ($nk === \__McTokId::T_AS && $this->eff($pv) !== 40) {
            return false;
        }
        $pk = $this->eff($pv);
        if (self::isClassy($pk)) {
            return false;
        }
        if ($pk === \__McTokId::T_AS || $pk === \__McTokId::T_CONST || $pk === \__McTokId::T_DOUBLE_COLON || $pk === \__McTokId::T_FUNCTION
            || $pk === \__McTokId::T_GOTO || $pk === CT::GROUP_IMPORT_BRACE_OPEN || $pk === CT::TYPE_COLON || $pk === CT::TYPE_ALTERNATION
            || $pk === CT::TYPE_INTERSECTION || $pk === \__McTokId::T_OBJECT_OPERATOR || $pk === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR) {
            return false;
        }
        if ($pk === \__McTokId::T_CASE && $this->hasEnum) {
            for ($j = $i - 1; $j >= 0; $j--) {
                $kj = $this->eff($j);
                if ($kj === \__McTokId::T_SWITCH || $kj === \__McTokId::T_ENUM) {
                    break;
                }
            }
            if ($j < 0 || $this->eff($j) !== \__McTokId::T_SWITCH) {
                return false;
            }
        }
        while ($pv >= 0 && (($pk = $this->eff($pv)) === CT::NAMESPACE_OPERATOR || $pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_STRING || $pk === CT::ARRAY_TYPEHINT)) {
            $pv--;
        }
        $pk = $pv >= 0 ? $this->eff($pv) : -1;
        if ($pk === CT::CONST_IMPORT || $pk === \__McTokId::T_EXTENDS || $pk === CT::FUNCTION_IMPORT || $pk === \__McTokId::T_IMPLEMENTS
            || $pk === \__McTokId::T_INSTANCEOF || $pk === \__McTokId::T_INSTEADOF || $pk === \__McTokId::T_NAMESPACE || $pk === \__McTokId::T_NEW
            || $pk === CT::NULLABLE_TYPE || $pk === CT::TYPE_COLON || $pk === \__McTokId::T_USE || $pk === CT::USE_TRAIT || $pk === CT::TYPE_INTERSECTION
            || $pk === CT::TYPE_ALTERNATION || $pk === \__McTokId::T_CONST || $pk === CT::DISJUNCTIVE_NORMAL_FORM_TYPE_PARENTHESIS_CLOSE) {
            return false;
        }
        if (($nk === 38 || $nk === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $nk === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)
            && $nx + 1 < $n && $this->eff($nx + 1) === \__McTokId::T_VARIABLE) {
            for ($j = $pv - 1; $j >= 0; $j--) {
                $kj = $this->eff($j);
                if ($kj === 59 || $kj === 123 || $kj === 125 || $kj === \__McTokId::T_FUNCTION || $kj === \__McTokId::T_OPEN_TAG || $kj === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                    break;
                }
            }
            if ($j >= 0 && $this->eff($j) === \__McTokId::T_FUNCTION) {
                return false;
            }
        }
        if ($pk === 44) {
            $c = $pv;
            while ($c >= 0 && (($ck = $this->eff($c)) === 44 || $ck === \__McTokId::T_AS || $ck === CT::NAMESPACE_OPERATOR || $ck === \__McTokId::T_NS_SEPARATOR || $ck === \__McTokId::T_STRING)) {
                $c--;
            }
            $ck = $c >= 0 ? $this->eff($c) : -1;
            if ($ck === \__McTokId::T_EXTENDS || $ck === CT::GROUP_IMPORT_BRACE_OPEN || $ck === \__McTokId::T_IMPLEMENTS || $ck === \__McTokId::T_USE || $ck === CT::USE_TRAIT) {
                return false;
            }
        }
        if ($pk === 91 && $nk === 93 && $nx + 1 < $n) {
            $ck = $this->eff($nx + 1);
            if ($ck === 34 || $ck === \__McTokId::T_CURLY_OPEN || $ck === \__McTokId::T_DOLLAR_OPEN_CURLY_BRACES || $ck === \__McTokId::T_ENCAPSED_AND_WHITESPACE || $ck === \__McTokId::T_VARIABLE) {
                return false;
            }
        }
        if ($this->hasAttr && $this->isAttribute($i)) {
            return false;
        }
        if ($nk === 58 && $this->gotoLabel($nx)) {
            return false;
        }
        while ($pv >= 0 && (($pk = $this->eff($pv)) === CT::NAMESPACE_OPERATOR || $pk === \__McTokId::T_NS_SEPARATOR || $pk === \__McTokId::T_STRING || $pk === CT::TYPE_ALTERNATION)) {
            $pv--;
        }
        if ($pv >= 1 && $this->eff($pv) === 40 && $this->eff($pv - 1) === \__McTokId::T_CATCH) {
            return false;
        }
        return true;
    }

    /** AttributeAnalyzer::isAttribute */
    private function isAttribute(int $i): bool
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $k = $this->eff($j);
            if ($k === 59 || $k === 123 || $k === \__McTokId::T_ATTRIBUTE || $k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_OPEN_TAG
                || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC
                || $k === \__McTokId::T_RETURN || $k === \__McTokId::T_VARIABLE || $k === CT::ATTRIBUTE_CLOSE) {
                break;
            }
        }
        if ($j < 0 || $this->s->kind[$j] !== \__McTokId::T_ATTRIBUTE) {
            return false;
        }
        $count = 0;
        for ($q = $j + 1; $q < $i; $q++) {
            $k = $this->eff($q);
            if ($k === 40) {
                $count++;
            } elseif ($k === 41) {
                $count--;
            }
        }
        return $count === 0;
    }

    // ---- removal on the whole-file FlatTokens (upstream indexes = load-time positions) ----

    private function p(int $i): int
    {
        return $this->t->pos[$i];
    }

    /** @param array{0:int,1:string,2:string,3:bool,4:bool,5:int,6:int,7:int,8:int} $d */
    private function removeDeclaration(array $d, bool $force): void
    {
        $t = $this->t;
        $multi = $d[4];
        if ($multi && !$force) {
            $start = $this->p($d[7]);
            $end = $this->p($d[8]);
        } else {
            $start = $this->p($d[5]);
            $end = $this->p($d[6]);
        }
        $loop = $multi || $force ? $end : $end - 1;
        for ($q = $loop; $q >= $start; $q--) {
            if ($t->isComment($q)) {
                continue;
            }
            if (!$t->isWs($q) || !\str_contains($t->c[$q], "\n")) {
                $t->clearAt($q);
                continue;
            }
            $prev = $t->nonWhitespace($q, -1);
            if ($prev >= 0 && $t->isComment($prev)) {
                // the line break after a comment stays, the indent goes
                $content = $t->c[$q];
                $t->c[$q] = \substr($content, (int) \strrpos($content, "\n"));
            } else {
                $this->clearMerge($q);
            }
        }
        if (!$force && $multi) {
            $this->afterChunkRemoval($d);
            return;
        }
        $e = $this->p($d[6]);
        if ($t->isCode($e) && $t->k[$e] === 59) {
            $t->clearAt($e);
        }
        $this->surroundingNewLines($d);
    }

    /** Tokens::clearTokenAndMergeSurroundingWhitespace */
    private function clearMerge(int $q): void
    {
        $t = $this->t;
        $count = $t->count();
        $t->clearAt($q);
        if ($q === $count - 1) {
            return;
        }
        $next = $t->nonEmptySibling($q, 1);
        if ($next < 0 || !$t->isWs($next)) {
            return;
        }
        $prev = $t->nonEmptySibling($q, -1);
        if ($prev >= 0 && $t->isWs($prev)) {
            $t->c[$prev] = $t->c[$prev] . $t->c[$next];
        } elseif ($this->isEmpty($prev + 1)) {
            $t->k[$prev + 1] = FlatTokens::WS;
            $t->c[$prev + 1] = $t->c[$next];
            $t->i[$prev + 1] = -1;
        }
        $t->clearAt($next);
    }

    private function isEmpty(int $q): bool
    {
        $t = $this->t;
        return $q >= 0 && $q < $t->count() && $t->k[$q] === FlatTokens::WS && $t->c[$q] === '';
    }

    /** Token::getId() === null: a single-character token (not retyped) or an emptied one */
    private function idNull(int $q): bool
    {
        $t = $this->t;
        return ($t->isCode($q) && $t->k[$q] < 256) || $this->isEmpty($q);
    }

    /** ensureWhitespaceAtIndex($q, 0, $content) on a whitespace item */
    private function setWs(int $q, string $content): void
    {
        $t = $this->t;
        if ($content === '') {
            $t->clearAt($q);
        } else {
            $t->c[$q] = $content;
        }
    }

    /** @param array{0:int,1:string,2:string,3:bool,4:bool,5:int,6:int,7:int,8:int} $d */
    private function surroundingNewLines(array $d): void
    {
        $t = $this->t;
        $prevIndex = $this->p($d[5]) - 1;
        $prevWs = $prevIndex >= 0 && $t->isWs($prevIndex);
        if ($prevWs) {
            $this->setWs($prevIndex, \rtrim($t->c[$prevIndex], " \t"));
            $prevWs = $t->isWs($prevIndex);
        }
        $e = $this->p($d[6]);
        if ($e + 1 >= $t->count()) {
            return;
        }
        $next = $t->nonEmptySibling($e, 1);
        if ($next < 0) {
            return;
        }
        if ($t->isWs($next)) {
            $content = (string) \preg_replace("#^\r\n|^\n#", '', \ltrim($t->c[$next], " \t"), 1);
            $this->setWs($next, $content);
        }
        if ($prevWs && $prevIndex >= 0 && $t->isWs($prevIndex) && $t->isWs($next)) {
            $this->setWs($next, $t->c[$prevIndex] . $t->c[$next]);
            $t->clearAt($prevIndex);
        }
    }

    /** @return array{0:int,1:bool} scanForNonEmptyTokensUntilNewLineFound: [index of the whitespace starting with "\n" or -1, a non-empty token seen] */
    private function scan(int $q, int $dir): array
    {
        $t = $this->t;
        $nonEmpty = false;
        $nl = -1;
        while ($q >= 0) {
            $q = $t->nonEmptySibling($q, $dir);
            if ($q < 0 || $this->idNull($q)) {
                continue;
            }
            if (!$t->isWs($q)) {
                $nonEmpty = true;
            } elseif (\str_starts_with($t->c[$q], "\n")) {
                $nl = $q;
                break;
            }
        }
        return [$nl, $nonEmpty];
    }

    /** @param array{0:int,1:string,2:string,3:bool,4:bool,5:int,6:int,7:int,8:int} $d */
    private function afterChunkRemoval(array $d): void
    {
        $t = $this->t;
        $before = $t->meaningful($this->p($d[7]), -1);
        $after = $t->meaningful($this->p($d[8]), 1);
        $hb = $this->scan($after, -1);
        $ha = $this->scan($after, 1);
        $safe = $hb[1] || $ha[1];
        if ($after >= 0 && $t->isCode($after) && $t->k[$after] === 44) {
            $safe ? $this->clearMerge($after) : $t->clearAt($after);
        } elseif ($before >= 0 && $t->isCode($before) && $t->k[$before] === 44) {
            $safe ? $this->clearMerge($before) : $t->clearAt($before);
        }
        for ($q = $before; $q <= $after; $q++) {
            if ($this->idNull($q) || !$t->isWs($q) || \strspn($t->c[$q], ' ') !== \strlen($t->c[$q])) {
                continue;
            }
            $nx = $t->meaningful($q, 1);
            $nk = $nx >= 0 ? $t->k[$nx] : -1;
            if ($nk === 44 || $nk === 59 || $nk === CT::GROUP_IMPORT_BRACE_CLOSE) {
                $t->clearAt($q);
            } else {
                $t->c[$q] = ' ';
            }
            $pv = $t->meaningful($q, -1);
            if ($pv >= 0 && $t->k[$pv] === CT::GROUP_IMPORT_BRACE_OPEN) {
                $t->clearAt($q);
            }
        }
        // removeLineIfEmpty
        $b = $this->scan($this->p($d[7]), -1);
        $a = $this->scan($this->p($d[8]), 1);
        if ($b[0] >= 0 && !$b[1] && $a[0] >= 0 && !$a[1]) {
            for ($q = $b[0]; $q <= $a[0] - 1; $q++) {
                $t->clearAt($q);
            }
        }
        // removeImportStatementIfEmpty
        $endI = $this->p($d[6]);
        $brace = -1;
        for ($q = $endI - 1; $q >= 0; $q--) {
            if ($t->isCode($q) && $t->k[$q] === CT::GROUP_IMPORT_BRACE_CLOSE) {
                $brace = $q;
                break;
            }
        }
        if ($brace > $this->p($d[5])) {
            $open = $t->meaningful($brace, -1);
            if ($open >= 0 && $t->k[$open] === CT::GROUP_IMPORT_BRACE_OPEN) {
                $this->removeDeclaration($d, true);
            }
        }
        $bs = $t->meaningful($endI, -1);
        if ($bs >= 0 && (($t->isCode($bs) && $t->k[$bs] === \__McTokId::T_USE) || \in_array($t->content($bs), ['function', 'const'], true))) {
            $this->removeDeclaration($d, true);
        }
    }
}
