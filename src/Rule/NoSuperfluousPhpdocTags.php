<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\Doc\NsScan;
use Fmt\Doc\TypeExpression;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer (priority 6): `@param` / `@return` / `@var` annotations that only
 * repeat the native type (no description), `@inheritDoc` (remove_inheritdoc), `@abstract` / `@final` next to the modifier. A file rule:
 * upstream's walk keeps the current class name (for `self`), the namespace of each docblock and the file's single imports.
 */
final class NoSuperfluousPhpdocTags extends GapRule
{
    private const RESERVED = ['array', 'bool', 'callable', 'false', 'float', 'int', 'iterable', 'list', 'mixed', 'never', 'null', 'object', 'parent',
        'resource', 'self', 'static', 'string', 'true', 'void'];

    private ?Source $s = null;
    /** @var array<string, string> */
    private array $short = [];
    private ?string $ns = null;
    private ?string $symbol = null;

    public function __construct(private bool $allowMixed, private bool $removeInheritdoc, private bool $allowHiddenParams, private bool $allowUnusedParams)
    {
    }

    public function name(): string
    {
        return 'no_superfluous_phpdoc_tags';
    }

    public function priority(): int
    {
        return 6;
    }

    public function isFileRule(): bool
    {
        return true;
    }

    public function needsMask(): int
    {
        return Gap::G_DOC;
    }

    public function reads(): int
    {
        return self::F_GAP | self::F_NB;
    }

    public function writes(): int
    {
        return self::F_GAP | self::F_NB;
    }

    public function prepare(Source $s): bool
    {
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
        $this->short = NsScan::shortNames($s);
        $scan = null;
        $this->symbol = null;
        $symbolEnd = -1;
        $n = $s->n;
        for ($t = 0; $t <= $n; $t++) {
            if (($s->gapFlags[$t] & Gap::G_DOC) !== 0) {
                $p = Gap::pieces($s->gapBefore[$t]);
                $changed = false;
                foreach ($p as $k => $x) {
                    if (!Gap::isDoc($x)) {
                        continue;
                    }
                    $el = $this->documentedElement($t);
                    if ($el === null) {
                        continue;
                    }
                    if ($el[0] === -2) {
                        $s->abort = true; // an attribute without its end: upstream throws
                        return;
                    }
                    if ($scan === null) {
                        $scan = NsScan::scan($s);
                    }
                    $ns = $scan->at($t);
                    if ($ns === null) {
                        $s->abort = true; // no namespace scope holds the docblock: upstream's getNamespaceAt throws
                        return;
                    }
                    $this->ns = $ns === '' ? null : $ns;
                    $new = $this->fixDoc($x, $el);
                    if ($new === null) {
                        $s->abort = true;
                        return;
                    }
                    if ($new !== $x) {
                        $p[$k] = $new;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $s->setGap($t, \implode('', $p));
                }
            }
            if ($t >= $n) {
                break;
            }
            if ($t === $symbolEnd) {
                $this->symbol = null;
                $symbolEnd = -1;
                continue;
            }
            $kd = $s->kind[$t];
            if ($kd === \__McTokId::T_CLASS && Analyzer::isAnonymousClass($s, $t)) {
                continue;
            }
            if (($kd === \__McTokId::T_CLASS && $s->ct[$t] === 0) || $kd === \__McTokId::T_INTERFACE || $kd === \__McTokId::T_ENUM) {
                $this->symbol = $t + 1 < $n ? $s->text($t + 1) : null;
                $open = -1;
                for ($x = $t + 1; $x < $n; $x++) {
                    if ($s->kind[$x] === 123 && $s->ct[$x] === 0) {
                        $open = $x;
                        break;
                    }
                }
                if ($open < 0 || $s->pair[$open] < 0) {
                    $s->abort = true;
                    return;
                }
                $symbolEnd = $s->pair[$open];
            }
        }
    }

    /**
     * findDocumentedElement: [type (0 classy, 1 function, 2 property; -2 broken attribute), element index, first type index or -1, modifier bits]
     * @return int[]|null
     */
    private function documentedElement(int $i): ?array
    {
        $s = $this->s;
        $n = $s->n;
        $index = $i < $n ? $i : -1;
        if ($index >= 0 && $s->kind[$index] === \__McTokId::T_ATTRIBUTE) {
            do {
                $e = $s->pair[$index];
                if ($e < 0) {
                    return [-2, 0, -1, 0];
                }
                $index = $e + 1 < $n ? $e + 1 : -1;
            } while ($index >= 0 && $s->kind[$index] === \__McTokId::T_ATTRIBUTE);
        }
        $firstType = -1;
        $mods = 0;
        while ($index >= 0) {
            $k = $s->kind[$index];
            $c = $s->ct[$index];
            if ($c === 0 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                return [0, $index, $firstType, $mods];
            }
            if ($c === 0 && ($k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_FN)) {
                return [1, $index, $firstType, $mods];
            }
            if ($k === \__McTokId::T_VARIABLE) {
                return [2, $index, $firstType, $mods];
            }
            if ($c === 0 && ($k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_ABSTRACT
                || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET
                || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET)) {
                if ($k === \__McTokId::T_ABSTRACT) {
                    $mods |= 1;
                } elseif ($k === \__McTokId::T_FINAL) {
                    $mods |= 2;
                }
            } elseif ($c === CT::NULLABLE_TYPE || $c === CT::ARRAY_TYPEHINT || $c === CT::TYPE_ALTERNATION || $c === CT::TYPE_INTERSECTION
                || ($c === 0 && ($k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR))) {
                if ($firstType < 0) {
                    $firstType = $index;
                }
            } else {
                return null;
            }
            $index = $index + 1 < $n ? $index + 1 : -1;
        }
        return null;
    }

    /** @param int[] $el */
    private function fixDoc(string $doc, array $el): ?string
    {
        $content = $doc;
        if ($this->removeInheritdoc && \stripos($content, 'inheritdoc') !== false) {
            $content = self::removeInheritDoc($content);
        }
        if ($el[0] === 1) {
            if (\str_contains($content, '@') || $el[3] !== 0) {
                $r = $this->fixFunction($content, $el);
                if ($r === null) {
                    return null;
                }
                $content = $r;
            }
        } elseif ($el[0] === 2) {
            if (\str_contains($content, '@var')) {
                $info = $el[2] >= 0 ? $this->parseTypeHint($el[2]) : [[], true];
                $d = new DocBlock($content);
                foreach ($d->getAnnotationsOfType(['var']) as $a) {
                    if ($this->superfluous($a, $info)) {
                        $a->remove();
                    }
                }
                $content = $d->getContent();
            }
        } elseif ($el[3] !== 0 && \str_contains($content, '@')) {
            $d = new DocBlock($content);
            self::removeModifierAnnotations($d, $el[3]);
            $content = $d->getContent();
        }
        return $content === '' ? '/**  */' : $content;
    }

    /** @param int[] $el */
    private function fixFunction(string $content, array $el): ?string
    {
        $s = $this->s;
        $n = $s->n;
        $open = -1;
        for ($x = $el[1] + 1; $x < $n; $x++) {
            if ($s->kind[$x] === 40 && $s->ct[$x] === 0) {
                $open = $x;
                break;
            }
        }
        if ($open < 0 || $s->pair[$open] < 0) {
            return null;
        }
        $close = $s->pair[$open];
        $d = new DocBlock($content);
        $params = $d->getAnnotationsOfType(['param']);
        $returns = $d->getAnnotationsOfType(['return']);
        if ($params !== []) {
            /** @var array<string, array{0: string[], 1: bool}> $args */
            $args = $this->argumentsInfo($open, $close);
            foreach ($params as $a) {
                $name = $a->getVariableName();
                if ($name === null) {
                    if ($this->superfluous($a, [[], true])) {
                        $a->remove();
                    }
                    continue;
                }
                if (!isset($args[$name]) && $this->allowUnusedParams) {
                    continue;
                }
                if (!isset($args[$name]) || $this->superfluous($a, $args[$name])) {
                    $a->remove();
                }
            }
        }
        if ($returns !== []) {
            $info = [[], true];
            if ($close + 1 < $n && $s->ct[$close + 1] === CT::TYPE_COLON && $close + 2 < $n) {
                $info = $this->parseTypeHint($close + 2);
            }
            foreach ($returns as $a) {
                if ($this->superfluous($a, $info)) {
                    $a->remove();
                }
            }
        }
        self::removeModifierAnnotations($d, $el[3]);
        return $d->getContent();
    }

    /** @return array<string, array{0: string[], 1: bool}> */
    private function argumentsInfo(int $open, int $close): array
    {
        $s = $this->s;
        $out = [];
        for ($t = $open + 1; $t < $close; $t++) {
            if ($s->kind[$t] !== \__McTokId::T_VARIABLE) {
                continue;
            }
            $before = $t - 1;
            while ($before >= 0 && !(($s->kind[$before] === 40 && $s->ct[$before] === 0) || $s->kind[$before] === 44 || $s->ct[$before] === CT::ATTRIBUTE_CLOSE)) {
                $before--;
            }
            $typeIndex = $before + 1;
            $info = $typeIndex !== $t ? $this->parseTypeHint($typeIndex) : [[], true];
            if (!$info[1] && $t + 2 < $s->n && $s->kind[$t + 1] === 61 && $s->kind[$t + 2] === \__McTokId::T_STRING && $s->ct[$t + 2] === 0
                && \strtolower($s->text($t + 2)) === 'null') {
                $info[1] = true;
            }
            $out[$s->text($t)] = $info;
        }
        if ($this->allowHiddenParams) {
            $code = NsScan::partial($s, $open + 1, $close - 1, true) . $s->gapBefore[$close];
            if ($close === $open + 1) {
                $code = $s->gapBefore[$close];
            }
            if (\str_contains($code, '/*')) {
                Preg::matchAll('~/\*.*?\*/~s', $code, $m);
                foreach ($m[0] as $comment) {
                    Preg::matchAll('~\$\w+~', (string) $comment, $pm);
                    foreach ($pm[0] as $hidden) {
                        $out[(string) $hidden] = [[], true];
                    }
                }
            }
        }
        return $out;
    }

    /** @return array{0: string[], 1: bool} types, allows null */
    private function parseTypeHint(int $index): array
    {
        $s = $this->s;
        $n = $s->n;
        $null = false;
        $types = [];
        while ($index < $n) {
            $type = '';
            $k = $s->kind[$index];
            if ($s->ct[$index] === 0 && ($k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET
                || $k === \__McTokId::T_PUBLIC_SET)) {
                $index++;
                if ($index >= $n) {
                    break;
                }
            }
            $c = $s->ct[$index];
            if ($c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE) {
                $index++;
                continue;
            }
            if ($c === CT::NULLABLE_TYPE) {
                $null = true;
                $index++;
            }
            while ($index < $n) {
                $k = $s->kind[$index];
                $c = $s->ct[$index];
                if (!(($c === 0 && ($k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_STRING || $k === \__McTokId::T_CALLABLE))
                    || $c === CT::ARRAY_TYPEHINT)) {
                    break;
                }
                $type .= $s->text($index);
                $index++;
            }
            if ($type === '') {
                break;
            }
            $types[] = $type;
            if ($index >= $n || ($s->ct[$index] !== CT::TYPE_ALTERNATION && $s->ct[$index] !== CT::TYPE_INTERSECTION)) {
                break;
            }
            $index++;
        }
        return [$types, $null];
    }

    /** @param array{0: string[], 1: bool} $info */
    private function superfluous(Annotation $a, array $info): bool
    {
        $tag = $a->getTag()->getName();
        if ($tag === 'param') {
            $re = '{\*\h*@param(?:\h+' . TypeExpression::REGEX_TYPES . ')?(?!\S)(?:\h+(?:\&\h*)?(?:\.{3}\h*)?\$\S+)?(?:\s+(?<description>(?!\*+\/)\S+))?}s';
        } elseif ($tag === 'var') {
            $re = '{\*\h*@var(?:\h+' . TypeExpression::REGEX_TYPES . ')?(?!\S)(?:\h+\$\S+)?(?:\s+(?<description>(?!\*\/)\S+))?}s';
        } else {
            $re = '{\*\h*@return(?:\h+' . TypeExpression::REGEX_TYPES . ')?(?!\S)(?:\s+(?<description>(?!\*\/)\S+))?}s';
        }
        if (!Preg::match($re, $a->getContent(), $m)) {
            return false;
        }
        if (isset($m['description'])) {
            return false;
        }
        if (!isset($m['types']) || $m['types'] === '') {
            return true;
        }
        $ann = $this->comparable($a->getTypes());
        if ($ann === ['null'] && $info[0] !== ['null']) {
            return false;
        }
        if ($ann === ['mixed'] && $info[0] === []) {
            return !$this->allowMixed;
        }
        $actual = $info[0];
        if ($info[1]) {
            $actual[] = 'null';
        }
        $actual = $this->comparable($actual);
        if ($ann === $actual) {
            return true;
        }
        $ann[] = 'null';
        \sort($ann);
        return $actual === $ann;
    }

    /**
     * toComparableNames
     * @param string[] $types
     * @return string[]
     */
    private function comparable(array $types): array
    {
        if (isset($types[0][0]) && $types[0][0] === '?') {
            $types = [\substr($types[0], 1), 'null'];
        }
        $out = [];
        foreach ($types as $type) {
            if (\str_contains($type, '&')) {
                $out[] = \implode('&', $this->comparable(\explode('&', $type)));
                continue;
            }
            if ($type === 'self' && $this->symbol !== null) {
                $type = $this->symbol;
            }
            $type = \strtolower($type);
            if (isset($this->short[$type])) {
                $out[] = $this->short[$type];
                continue;
            }
            if (\str_starts_with($type, '\\')) {
                $out[] = \substr($type, 1);
                continue;
            }
            if ($this->ns !== null && !\in_array(\str_starts_with($type, '?') ? \substr($type, 1) : $type, self::RESERVED, true)) {
                $type = \strtolower($this->ns) . '\\' . $type;
            }
            $out[] = $type;
        }
        \sort($out);
        return $out;
    }

    private static function removeModifierAnnotations(DocBlock $d, int $mods): void
    {
        if (($mods & 1) !== 0) {
            foreach ($d->getAnnotationsOfType(['abstract']) as $a) {
                $a->remove();
            }
        }
        if (($mods & 2) !== 0) {
            foreach ($d->getAnnotationsOfType(['final']) as $a) {
                $a->remove();
            }
        }
    }

    private static function removeInheritDoc(string $doc): string
    {
        return Preg::replace('~
            # $1: before @inheritDoc tag
            (
                # beginning of comment or a PHPDoc tag
                (?:
                    ^/\*\*
                    (?:
                        \R
                        [ \t]*(?:\*[ \t]*)?
                    )*?
                    |
                    @\N+
                )
                # empty comment lines
                (?:
                    \R
                    [ \t]*(?:\*[ \t]*?)?
                )*
            )
            # spaces before @inheritDoc tag
            [ \t]*
            # @inheritDoc tag
            (?:@inheritDocs?|\{@inheritDocs?\})
            # $2: after @inheritDoc tag
            (
                # empty comment lines
                (?:
                    \R
                    [ \t]*(?:\*[ \t]*)?
                )*
                # a PHPDoc tag or end of comment
                (?:
                    @\N+
                    |
                    (?:
                        \R
                        [ \t]*(?:\*[ \t]*)?
                    )*
                    [ \t]*\*/$
                )
            )
        ~ix', '$1$2', $doc);
    }
}
