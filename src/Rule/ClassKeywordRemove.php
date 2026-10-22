<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\ClassKeywordRemoveFixer (deprecated without successor, priority 0). `Foo\Bar::class` -> `'Namespace\Foo\Bar'`,
 * resolved against the `use` imports of the namespace section. Whole-file FlatTokens port: the sections (from the namespace declarations found at load time) keep
 * their load-time indexes while the replacements shift the list, as upstream's do.
 */
final class ClassKeywordRemove extends FlatRule
{
    /** @var string[] import keys ('' = appended without a key) in order */
    private array $impKey = [];
    /** @var string[] */
    private array $impVal = [];

    public function name(): string
    {
        return 'class_keyword_remove';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->ct[$i] === CT::CLASS_CONSTANT) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $prevEnd = 0;
        foreach (\Fmt\FlatNamespaces::declarations($t) as [$name, $start, $end]) {
            $this->section($s, $t, '', $prevEnd, $start);
            $this->section($s, $t, $name, $start, $end);
            $prevEnd = $end;
        }
        $this->section($s, $t, '', $prevEnd, $t->count() - 1);
    }

    private function section(Source $s, FlatTokens $t, string $namespace, int $start, int $end): void
    {
        if ($end - $start < 3) {
            return;
        }
        $this->storeImports($t, $start, $end);
        $found = [];
        for ($i = $start; $i < $end && $i < $t->count(); ++$i) {
            if (self::is($t, $i, CT::CLASS_CONSTANT)) {
                $found[] = $i;
            }
        }
        for ($x = \count($found) - 1; $x >= 0; $x--) {
            $this->replace($s, $t, $namespace, $found[$x]);
        }
    }

    private function setImport(string $key, string $value): void
    {
        if ($key !== '') {
            foreach ($this->impKey as $k => $existing) {
                if ($existing === $key) {
                    $this->impVal[$k] = $value;
                    return;
                }
            }
        }
        $this->impKey[] = $key;
        $this->impVal[] = $value;
    }

    private function storeImports(FlatTokens $t, int $start, int $end): void
    {
        $this->impKey = [];
        $this->impVal = [];
        $m = $t->count();
        // TokensAnalyzer::getImportUseIndexes: T_USE tokens outside class bodies
        $uses = [];
        for ($index = 0; $index < $m; ++$index) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_NAMESPACE) {
                $nx = $t->meaningful($index, 1);
                if (self::is($t, $nx, 123)) {
                    $index = $nx;
                }
                continue;
            }
            if ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) {
                $open = self::nextOf($t, $index, 123);
                if ($open < 0) {
                    break;
                }
                $index = $t->blockEnd($open, 125);
                continue;
            }
            if ($k === \__McTokId::T_USE) {
                $uses[] = $index;
            }
        }
        foreach ($uses as $index) {
            if ($index < $start || $index > $end) {
                continue;
            }
            $import = '';
            while (($index = $t->meaningful($index, 1)) >= 0) {
                if (self::is($t, $index, 59) || self::is($t, $index, CT::GROUP_IMPORT_BRACE_OPEN) || self::is($t, $index, \__McTokId::T_AS)) {
                    break;
                }
                $import .= $t->content($index);
            }
            if ($index < 0) {
                continue;
            }
            if (self::is($t, $index, CT::GROUP_IMPORT_BRACE_OPEN)) {
                $groupEnd = $t->blockEnd($index, CT::GROUP_IMPORT_BRACE_CLOSE);
                foreach (\explode(',', \Fmt\FlatNamespaces::partial($t, $index + 1, $groupEnd - 1)) as $groupImport) {
                    $groupImport = \trim($groupImport);
                    $parts = \explode(' as ', $groupImport);
                    if (\count($parts) === 2) {
                        $this->setImport(\trim($parts[1]), $import . \trim($parts[0]));
                    } else {
                        $this->setImport('', $import . $groupImport);
                    }
                }
            } elseif (self::is($t, $index, \__McTokId::T_AS)) {
                $alias = $t->meaningful($index, 1);
                $this->setImport($t->content($alias), $import);
            } else {
                $this->setImport('', $import);
            }
        }
    }

    private function replace(Source $s, FlatTokens $t, string $namespacePrefix, int $classIndex): void
    {
        $classEnd = $t->meaningful($classIndex, -1);
        $classEnd = $t->meaningful($classEnd, -1);
        if ($classEnd < 0 || !self::is($t, $classEnd, \__McTokId::T_STRING)) {
            return;
        }
        $lower = \strtolower($t->content($classEnd));
        if ($lower === 'self' || $lower === 'static' || $lower === 'parent') {
            return;
        }
        $begin = $classEnd;
        while (true) {
            $prev = $t->meaningful($begin, -1);
            if ($prev < 0 || !(self::is($t, $prev, \__McTokId::T_NS_SEPARATOR) || self::is($t, $prev, \__McTokId::T_STRING))) {
                break;
            }
            $begin = $prev;
        }
        $classString = \Fmt\FlatNamespaces::partial($t, self::is($t, $begin, \__McTokId::T_NS_SEPARATOR) ? $t->meaningful($begin, 1) : $begin, $classEnd);
        $classImport = null;
        if (self::is($t, $begin, \__McTokId::T_NS_SEPARATOR)) {
            $namespacePrefix = '';
        } else {
            foreach ($this->impKey as $x => $alias) {
                $import = $this->impVal[$x];
                if ($alias !== '' && $classString === $alias) {
                    $classImport = $import;
                    break;
                }
                $parts = \explode('\\', $classString);
                $first = $parts[0];
                if (\substr($import, -\strlen($first)) === $first) {
                    $classImport = $import;
                    break;
                }
            }
        }
        $before = $t->meaningful($begin, -1);
        if ($before >= 0 && $t->i[$before] !== -1 && ($t->k[$before] === \__McTokId::T_DOUBLE_COLON || $t->k[$before] === \__McTokId::T_OBJECT_OPERATOR
            || $t->k[$before] === \__McTokId::T_NULLSAFE_OBJECT_OPERATOR || $t->k[$before] === CT::NAMESPACE_OPERATOR)) {
            $s->abort = true; // `E::A::class` -> `E::'A'`, `namespace\Foo::class` -> `namespace'Foo'`: upstream's result does not lint, the file stays as it is
        }
        for ($i = $begin; $i <= $classIndex; ++$i) {
            $k = $t->k[$i];
            if ($k === FlatTokens::COMMENT || ($k === FlatTokens::WS && \str_contains($t->c[$i], "\n"))) {
                continue;
            }
            $t->clearAt($i);
        }
        $t->insertAt($begin, [FlatTokens::code(\__McTokId::T_CONSTANT_ENCAPSED_STRING, "'" . $this->fqn($namespacePrefix, $classImport, $classString) . "'")]);
    }

    private function fqn(string $namespacePrefix, ?string $classImport, string $classString): string
    {
        if ($classImport === null) {
            return ($namespacePrefix !== '' ? $namespacePrefix . '\\' : '') . $classString;
        }
        $str = \explode('\\', $classString);
        $strLen = \count($str);
        $imp = \explode('\\', $classImport);
        $impLen = \count($imp);
        if ($strLen === 1) {
            return $classImport;
        }
        return \implode('\\', \array_merge(\array_slice($imp, 0, $impLen - $strLen + 1), $str));
    }
}
