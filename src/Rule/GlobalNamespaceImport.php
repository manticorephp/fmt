<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\Doc\NsScan;
use Fmt\DocRule;
use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Invocation;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\GlobalNamespaceImportFixer (priority 0) for import_constants / import_functions /
 * import_classes = false or null (the sets' configuration; importing, `true`, reports the file as not fixed): in a file with one namespace, a global
 * symbol that is imported (`use DateTime;`, `use function strlen;`, `use const PHP_EOL;`) is written fully qualified wherever it is
 * used, docblock types included. The imports themselves stay (no_unused_imports removes them).
 */
final class GlobalNamespaceImport extends EditRule
{
    public function __construct(private ?bool $consts, private ?bool $functions, private ?bool $classes) {}

    public function name(): string
    {
        return 'global_namespace_import';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        if ($this->consts === null && $this->functions === null && $this->classes === null) {
            return false;
        }
        $ns = 0;
        $open = 0;
        $html = 0;
        $any = false;
        for ($t = 0; $t < $s->n; $t++) {
            $k = $s->kind[$t];
            if ($k === \__McTokId::T_NAMESPACE && $s->ct[$t] === 0) {
                $ns++;
            } elseif ($k === \__McTokId::T_OPEN_TAG || $k === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                $open++;
            } elseif ($k === \__McTokId::T_INLINE_HTML) {
                $html++;
            } elseif ($k === \__McTokId::T_NS_SEPARATOR || ($k === \__McTokId::T_USE && $s->ct[$t] === 0)) {
                $any = true;
            }
        }
        if ($ns !== 1 || $open !== 1 || ($html > 1 || ($html === 1 && !($s->kind[0] === \__McTokId::T_INLINE_HTML && Preg::test('/^#!.+$/', $s->text(0)))))) {
            return false;
        }
        if (!$any) {
            for ($g = 0; $g <= $s->n && !$any; $g++) {
                $any = ($s->gapFlags[$g] & Gap::G_DOC) !== 0;
            }
        }
        return $any;
    }

    public function fixFile(Source $s): void
    {
        $scan = NsScan::scan($s);
        if (\count($scan->name) !== 1 || $scan->name[0] === '') {
            return;
        }
        if ($this->consts === true || $this->functions === true || $this->classes === true) {
            $s->unsupported = 'global_namespace_import: importing (import_* = true) is not implemented';
            return;
        }
        $uses = NsScan::uses($s, false);
        /** @var array<int, bool> $ins tokens that get a `\` in front */
        $ins = [];
        $hasAttr = false;
        $hasEnum = false;
        $hasConstImport = false;
        $hasFnImport = false;
        $hasUse = false;
        for ($t = 0; $t < $s->n; $t++) {
            $k = $s->kind[$t];
            $c = $s->ct[$t];
            $hasAttr = $hasAttr || $k === \__McTokId::T_ATTRIBUTE;
            $hasEnum = $hasEnum || ($k === \__McTokId::T_ENUM && $c === 0);
            $hasConstImport = $hasConstImport || $c === CT::CONST_IMPORT;
            $hasFnImport = $hasFnImport || $c === CT::FUNCTION_IMPORT;
            $hasUse = $hasUse || ($k === \__McTokId::T_USE && $c === 0);
        }
        if ($this->consts === false && $hasConstImport) {
            $global = self::globals($uses, NsScan::USE_CONST, true);
            if ($global !== []) {
                for ($t = $s->n - 1; $t >= 0; $t--) {
                    if (Invocation::eff($s, $t) !== \__McTokId::T_STRING || !isset($global[$s->text($t)]) || self::prevIsNs($s, $t, $ins)) {
                        continue;
                    }
                    if (Invocation::isConstantInvocation($s, $t, $hasEnum, $hasAttr)) {
                        $ins[$t] = true;
                    }
                }
            }
        }
        if ($this->functions === false && $hasFnImport) {
            $global = self::globals($uses, NsScan::USE_FUNCTION, false);
            if ($global !== []) {
                $decl = Invocation::functionDeclarations($s);
                $imports = [];
                foreach ($uses as $u) {
                    if ($u[0] === NsScan::USE_FUNCTION) {
                        $imports[] = [$u[5], $u[6], $u[2], $u[1]];
                    }
                }
                for ($t = $s->n - 1; $t >= 0; $t--) {
                    if (Invocation::eff($s, $t) !== \__McTokId::T_STRING || !isset($global[\strtolower($s->text($t))]) || self::prevIsNs($s, $t, $ins)) {
                        continue;
                    }
                    if (Invocation::isGlobalFunctionUsage($s, $t, $scan, $decl, $imports, $hasAttr)) {
                        $ins[$t] = true;
                    }
                }
            }
        }
        if ($this->classes === false && $hasUse) {
            $global = self::globals($uses, NsScan::USE_CLASS, false);
            if ($global !== []) {
                for ($t = $s->n; $t >= 0; $t--) {
                    DocRule::mapDocs($s, $t, static fn(string $d): string => self::qualifyDoc($d, $global));
                    if ($t === $s->n) {
                        continue;
                    }
                    if (Invocation::eff($s, $t) !== \__McTokId::T_STRING || !isset($global[\strtolower($s->text($t))]) || self::prevIsNs($s, $t, $ins)) {
                        continue;
                    }
                    if (Invocation::isClassyInvocation($s, $t, $hasAttr)) {
                        $ins[$t] = true;
                    }
                }
            }
        }
        foreach ($ins as $t => $x) {
            $s->edit(Source::tokAt($t), Source::tokAt($t), '\\');
        }
    }

    /** @param array<int, bool> $ins */
    private static function prevIsNs(Source $s, int $t, array $ins): bool
    {
        return isset($ins[$t]) || ($t > 0 && $s->kind[$t - 1] === \__McTokId::T_NS_SEPARATOR);
    }

    /**
     * filterUseDeclarations: the imports of global (no namespace part), not aliased symbols of a type
     * @param array<int, array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int}> $uses
     * @return array<string, bool>
     */
    private static function globals(array $uses, int $type, bool $caseSensitive): array
    {
        $out = [];
        foreach ($uses as $u) {
            if ($u[0] !== $type || $u[3] !== 0) {
                continue;
            }
            $full = \ltrim($u[1], '\\');
            if (\str_contains($full, '\\')) {
                continue;
            }
            $out[$caseSensitive ? $full : \strtolower($full)] = true;
        }
        return $out;
    }

    /** @param array<string, bool> $global */
    private static function qualifyDoc(string $doc, array $global): string
    {
        $hit = false;
        foreach ($global as $name => $x) {
            if (\stripos($doc, (string) $name) !== false) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $changed = false;
        foreach ($d->getAnnotationsOfType(Annotation::TAGS_WITH_TYPES) as $a) {
            $types = $a->getTypes();
            $new = [];
            foreach ($types as $full) {
                Preg::matchAll('/[\\\\\w]+(?![\\\\\w:])/', $full, $m, \PREG_OFFSET_CAPTURE);
                foreach (\array_reverse($m[0]) as $hit2) {
                    $type = (string) $hit2[0];
                    if (isset($global[\strtolower($type)])) {
                        $full = \substr_replace($full, '\\' . $type, (int) $hit2[1], \strlen($type));
                    }
                }
                $new[] = $full;
            }
            if ($new !== $types) {
                $a->setTypes($new);
                $changed = true;
            }
        }
        return $changed ? $d->getContent() : $doc;
    }
}
