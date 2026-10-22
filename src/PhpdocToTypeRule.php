<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Doc\DocBlock;
use Fmt\Doc\NsContext;
use Fmt\Doc\NsScan;
use Fmt\Doc\TypeExpression;

/**
 * PhpCsFixer\Fixer\AbstractPhpdocToTypeDeclarationFixer on a whole-file FlatTokens (PHP 8.5.x like the oracle: every version specific type is available, union types default on).
 * `Tokens::fromCode`'s syntax check of the new type is TypeSyntax (generated from the real tokenizer: the types are names joined by `|`, so only the reserved words matter);
 * a type for which the parser itself dies with a compile error (upstream's whole process ends) leaves the file as it is, like every other throw.
 */
abstract class PhpdocToTypeRule extends FlatRule
{
    private const SCALAR = ['bool' => true, 'float' => true, 'int' => true, 'string' => true];
    private const VERSION_SPECIFIC = ['void' => true, 'iterable' => true, 'object' => true, 'mixed' => true, 'never' => true];

    protected Source $src;
    /** @var array<string, string> */
    private array $typesMap;
    private bool $scalarTypes;
    private bool $unionTypes;
    private ?NsScan $scan = null;
    /** @var array<int, array{0:int,1:string,2:string}>|null */
    private ?array $uses = null;
    /** @var array<string, bool> */
    private array $reserved = [];
    /** @var array<string, bool> */
    private array $fatal = [];
    /** @var array<string, bool> */
    private array $alone = [];
    /** @var array<string, bool> */
    private array $union = [];

    /** @param array<string, string> $typesMap */
    public function __construct(bool $scalarTypes, bool $unionTypes, array $typesMap)
    {
        parent::__construct();
        $this->scalarTypes = $scalarTypes;
        $this->unionTypes = $unionTypes;
        $this->typesMap = $typesMap;
        foreach (\explode(' ', TypeSyntax::RESERVED) as $w) {
            $this->reserved[$w] = true;
        }
        $tpl = \strtoupper($this->template());
        foreach (\explode(' ', $this->constant('FATAL_ALONE_'.$tpl)) as $w) {
            if ($w !== '') {
                $this->fatal[$w] = true;
            }
        }
        foreach (\explode(' ', $this->constant('ALONE_'.$tpl)) as $w) {
            $this->alone[$w] = true;
        }
        foreach (\explode(' ', $this->constant('UNION_'.$tpl)) as $w) {
            $this->union[$w] = true;
        }
    }

    /** 'param' | 'property' | 'return' */
    abstract protected function template(): string;

    abstract protected function isSkippedType(string $type): bool;

    private function constant(string $name): string
    {
        return (string) \constant(TypeSyntax::class.'::'.$name);
    }

    /** the tag whose docblock a file needs to be changed at all */
    abstract protected function tag(): string;

    /**
     * The cheap Source-level test: a docblock with the tag right in front of a declaration the fixer can type (modifiers, then what shape() accepts at the token behind them).
     * An over-approximation of upstream's walk, so a file with no such pair is never loaded.
     */
    protected function candidate(Source $s): bool
    {
        $tag = $this->tag();
        for ($t = 0; $t < $s->n; $t++) {
            if (($s->gapFlags[$t] & Gap::G_DOC) === 0 || !\str_contains($s->gapBefore[$t], $tag)) {
                continue;
            }
            $u = $t;
            while ($u < $s->n && $s->ct[$u] === 0 && \in_array($s->kind[$u], [
                \__McTokId::T_ABSTRACT, \__McTokId::T_FINAL, \__McTokId::T_PRIVATE, \__McTokId::T_PROTECTED, \__McTokId::T_PUBLIC, \__McTokId::T_STATIC, \__McTokId::T_VAR,
            ], true)) {
                $u++;
            }
            if ($u < $s->n && $this->shape($s, $u)) {
                return true;
            }
        }
        return false;
    }

    /** the declaration starting at Source token $u (behind the modifiers) is one the rule may change */
    abstract protected function shape(Source $s, int $u): bool;

    /** a `function` / `fn` keyword at $u and its parameter list: [open, close], or null */
    protected static function parameterList(Source $s, int $u): ?array
    {
        if (($s->kind[$u] !== \__McTokId::T_FUNCTION && $s->kind[$u] !== \__McTokId::T_FN) || $s->ct[$u] !== 0) {
            return null;
        }
        for ($o = $u + 1; $o < $s->n && $o < $u + 6; $o++) {
            if ($s->kind[$o] === 40) {
                return $s->pair[$o] > $o ? [$o, $s->pair[$o]] : null;
            }
        }
        return null;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        $this->scan = null;
        $this->uses = null;
        $this->apply($s, $t);
    }

    abstract protected function apply(Source $s, FlatTokens $t): void;

    protected static function isDocItem(FlatTokens $t, int $q): bool
    {
        return $t->isComment($q) && Trivia::isDoc($t->c[$q]);
    }

    /** findFunctionDocComment: the docblock in front of the function keyword at $index (modifiers and comments between are skipped), -1 when none */
    protected function findFunctionDocComment(FlatTokens $t, int $index): int
    {
        do {
            $index = $t->nonWhitespace($index, -1);
            $skip = $index >= 0 && (($t->isComment($index) && !Trivia::isDoc($t->c[$index])) || ($t->i[$index] !== -1 && \in_array($t->k[$index], [
                \__McTokId::T_ABSTRACT, \__McTokId::T_FINAL, \__McTokId::T_PRIVATE, \__McTokId::T_PROTECTED, \__McTokId::T_PUBLIC, \__McTokId::T_STATIC,
            ], true)));
        } while ($skip);
        return $index >= 0 && self::isDocItem($t, $index) ? $index : -1;
    }

    /** the namespace context of the docblock at item $docIndex; null when no scope holds it (upstream's getNamespaceAt throws) */
    private function context(FlatTokens $t, int $docIndex): ?NsContext
    {
        $s = $this->src;
        if ($this->scan === null) {
            $this->scan = NsScan::scan($s);
            $this->uses = NsScan::uses($s, false);
        }
        $g = $s->n;
        for ($q = $docIndex + 1, $m = $t->count(); $q < $m; $q++) {
            if ($t->i[$q] >= 0) {
                $g = $t->i[$q];
                break;
            }
        }
        $scan = $this->scan;
        $ns = $scan->at($g);
        if ($ns === null) {
            return null;
        }
        $short = [];
        $full = [];
        $scope = -1;
        if (!$scan->global) {
            foreach ($scan->name as $k => $_) {
                if ($scan->start[$k] < $g && $g <= $scan->end[$k]) {
                    $scope = $k;
                    break;
                }
            }
        }
        foreach ($this->uses ?? [] as $u) {
            if ($scope >= 0 && ($u[5] < $scan->start[$scope] || $u[5] > $scan->tokEnd[$scope])) {
                continue;
            }
            $short[] = $u[2];
            $full[] = $u[1];
        }
        return new NsContext($ns, $short, $full);
    }

    /**
     * getAnnotationsFromDocComment; null: upstream throws (no namespace scope holds the docblock)
     * @return \Fmt\Doc\Annotation[]|null
     */
    protected function annotations(string $name, FlatTokens $t, int $docIndex): ?array
    {
        $ctx = $this->context($t, $docIndex);
        if ($ctx === null) {
            return null;
        }
        return (new DocBlock($t->c[$docIndex], $ctx))->getAnnotationsOfType([$name]);
    }

    /**
     * getTypesToExclude; null: upstream dereferences a missing type expression and throws
     * @return string[]|null
     */
    protected static function typesToExclude(string $content): ?array
    {
        $out = [];
        $db = new DocBlock($content);
        foreach ($db->getAnnotationsOfType(['phpstan-type', 'psalm-type']) as $a) {
            $e = $a->getTypeExpression();
            if ($e === null) {
                return null;
            }
            $out[] = $e->toString();
        }
        foreach ($db->getAnnotationsOfType(['phpstan-import-type', 'psalm-import-type']) as $a) {
            $c = \trim($a->getContent());
            if (Preg::match('/\bas\s+(' . TypeExpression::REGEX_IDENTIFIER . ')$/', $c, $m)) {
                $out[] = (string) $m[1];
                continue;
            }
            $e = $a->getTypeExpression();
            if ($e === null) {
                return null;
            }
            $out[] = $e->toString();
        }
        return $out;
    }

    /** @return array{commonType: string, isNullable: bool}|null */
    protected function getCommonTypeInfo(TypeExpression $te, bool $isReturnType): ?array
    {
        $commonType = $te->getCommonType();
        $isNullable = $te->allowsNull();
        if ($commonType === null) {
            return null;
        }
        if ($isNullable && $commonType === 'void') {
            return null;
        }
        if ($commonType === 'static' && !$isReturnType) {
            $commonType = 'self';
        }
        if ($this->isSkippedType($commonType)) {
            return null;
        }
        if (\array_key_exists($commonType, $this->typesMap)) {
            $commonType = $this->typesMap[$commonType];
        }
        if (isset(self::SCALAR[$commonType])) {
            if (!$this->scalarTypes) {
                return null;
            }
        } elseif (!Preg::test('/^(?:\\\?+' . TypeExpression::REGEX_IDENTIFIER . '(\\\\' . TypeExpression::REGEX_IDENTIFIER . ')*+)$/', $commonType)) {
            return null;
        }
        return ['commonType' => $commonType, 'isNullable' => $isNullable];
    }

    protected function getUnionTypes(TypeExpression $te, bool $isReturnType): ?string
    {
        if (!$te->isUnionType()) {
            return null;
        }
        if (!$this->unionTypes) {
            return null;
        }
        $isNullable = $te->allowsNull();
        $unionTypes = [];
        $containsOtherThanIterableType = false;
        $containsOtherThanEmptyType = false;
        foreach ($te->getTypes() as $type) {
            if ($type === 'null') {
                continue;
            }
            if ($this->isSkippedType($type)) {
                return null;
            }
            $info = $this->getCommonTypeInfo(new TypeExpression($type, null), $isReturnType);
            if ($info === null) {
                return null;
            }
            $commonType = $info['commonType'];
            if (!$containsOtherThanIterableType && !\in_array($commonType, ['array', 'Traversable', 'iterable'], true)) {
                $containsOtherThanIterableType = true;
            }
            if ($isReturnType && !$containsOtherThanEmptyType && !\in_array($commonType, ['null', 'void', 'never'], true)) {
                $containsOtherThanEmptyType = true;
            }
            if (!$isNullable && $info['isNullable']) {
                $isNullable = true;
            }
            $unionTypes[] = $commonType;
        }
        if (!$containsOtherThanIterableType) {
            return null;
        }
        if ($isReturnType && !$containsOtherThanEmptyType) {
            return null;
        }
        if ($isNullable) {
            $unionTypes[] = 'null';
        }
        return \implode((string) $te->getTypesGlue(), \array_unique($unionTypes));
    }

    /**
     * isValidSyntax of the template: 1 valid, 0 a ParseError, -1 the parser ends the process with a compile error (a modifier where a parameter / property modifier is not allowed).
     * The parts are names joined by `|`; a qualified or fully qualified name always parses, a plain one unless it is a reserved word the position does not take.
     */
    protected function syntax(string $type): int
    {
        $parts = \explode('|', $type);
        $sole = \count($parts) === 1;
        $res = 1;
        foreach ($parts as $p) {
            if (\str_contains($p, '\\')) {
                continue;
            }
            $w = \strtolower($p);
            if (!isset($this->reserved[$w])) {
                continue;
            }
            if (isset($this->fatal[$w])) {
                return -1;
            }
            if (!($sole ? isset($this->alone[$w]) : isset($this->union[$w]))) {
                $res = 0;
            }
        }
        return $res;
    }

    /**
     * createTypeDeclarationTokens as one code item. Upstream cuts the type into T_STRING / T_NS_SEPARATOR tokens and drops the `\` in front of a T_STRING that is a scalar or a version specific type,
     * whichever name that backslash belongs to (`Foo\int` becomes `Fooint`).
     * @return array<int, array{0:int,1:string,2:int}>
     */
    protected function typeItems(string $type, bool $isNullable): array
    {
        $out = '';
        if ($isNullable && $type !== 'mixed') {
            $out = '?';
        }
        $tokens = [];
        $len = \strlen($type);
        for ($p = 0; $p < $len;) {
            $ch = $type[$p];
            if ($ch === '\\' || $ch === '|') {
                $tokens[] = $ch;
                $p++;
                continue;
            }
            $e = $p;
            while ($e < $len && $type[$e] !== '\\' && $type[$e] !== '|') {
                $e++;
            }
            $tokens[] = \substr($type, $p, $e - $p);
            $p = $e;
        }
        $keep = [];
        foreach ($tokens as $i => $tok) {
            $keep[$i] = true;
        }
        foreach ($tokens as $i => $tok) {
            if ($tok !== '\\' && $tok !== '|' && (isset(self::SCALAR[$tok]) || isset(self::VERSION_SPECIFIC[$tok])) && $i > 0 && $tokens[$i - 1] === '\\') {
                $keep[$i - 1] = false;
            }
        }
        foreach ($tokens as $i => $tok) {
            if ($keep[$i]) {
                $out .= $tok;
            }
        }
        return [FlatTokens::code(\__McTokId::T_STRING, $out)];
    }
}
