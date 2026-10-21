<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\PsrAutoloadingFixer (priority -10, risky). The class declared in a file (one namespace, one classy at most) is renamed after the file name, keeping the
 * `Namespace_Name` prefix style of the namespace parts that sit in the file's directory; with `dir` the namespace is rewritten to the directory too. It needs the file: stdin, names that
 * are no identifier / keyword, and paths under `stub(s)` / `fixture(s)` are skipped as upstream's `supports()`. Whole-file FlatTokens port.
 */
final class PsrAutoloading extends FlatRule
{
    private string $path = '';
    private string $dir = '';

    public function __construct(?string $dir)
    {
        parent::__construct();
        if ($dir !== null) {
            $r = \realpath($dir);
            if ($r === false) {
                throw new \InvalidArgumentException("Failed to resolve configured directory \"$dir\".");
            }
            $this->dir = $r;
        }
    }

    public function name(): string
    {
        return 'psr_autoloading';
    }

    public function priority(): int
    {
        return -10;
    }

    public function forFile(string $path): void
    {
        $r = $path === '' ? false : \realpath($path);
        $this->path = $r === false ? '' : $r;
    }

    private static function keywordKind(int $k): bool
    {
        return $k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_ARRAY || $k === \__McTokId::T_AS || $k === \__McTokId::T_BREAK || $k === \__McTokId::T_CALLABLE || $k === \__McTokId::T_CASE
            || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_CLASS || $k === \__McTokId::T_CLONE || $k === \__McTokId::T_CONST || $k === \__McTokId::T_CONTINUE
            || $k === \__McTokId::T_DECLARE || $k === \__McTokId::T_DEFAULT || $k === \__McTokId::T_DO || $k === \__McTokId::T_ECHO || $k === \__McTokId::T_ELSE || $k === \__McTokId::T_ELSEIF
            || $k === \__McTokId::T_EMPTY || $k === \__McTokId::T_ENDDECLARE || $k === \__McTokId::T_ENDFOR || $k === \__McTokId::T_ENDFOREACH || $k === \__McTokId::T_ENDIF
            || $k === \__McTokId::T_ENDSWITCH || $k === \__McTokId::T_ENDWHILE || $k === \__McTokId::T_EVAL || $k === \__McTokId::T_EXIT || $k === \__McTokId::T_EXTENDS
            || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_FINALLY || $k === \__McTokId::T_FN || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_FUNCTION
            || $k === \__McTokId::T_GLOBAL || $k === \__McTokId::T_GOTO || $k === \__McTokId::T_HALT_COMPILER || $k === \__McTokId::T_IF || $k === \__McTokId::T_IMPLEMENTS
            || $k === \__McTokId::T_INCLUDE || $k === \__McTokId::T_INCLUDE_ONCE || $k === \__McTokId::T_INSTANCEOF || $k === \__McTokId::T_INSTEADOF || $k === \__McTokId::T_INTERFACE
            || $k === \__McTokId::T_ISSET || $k === \__McTokId::T_LIST || $k === \__McTokId::T_LOGICAL_AND || $k === \__McTokId::T_LOGICAL_OR || $k === \__McTokId::T_LOGICAL_XOR
            || $k === \__McTokId::T_NAMESPACE || $k === \__McTokId::T_NEW || $k === \__McTokId::T_PRINT || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED
            || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_REQUIRE || $k === \__McTokId::T_REQUIRE_ONCE || $k === \__McTokId::T_RETURN || $k === \__McTokId::T_STATIC
            || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_THROW || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_TRY || $k === \__McTokId::T_UNSET || $k === \__McTokId::T_USE
            || $k === \__McTokId::T_VAR || $k === \__McTokId::T_WHILE || $k === \__McTokId::T_YIELD || $k === \__McTokId::T_YIELD_FROM || $k === \__McTokId::T_ENUM
            || $k === \__McTokId::T_MATCH || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET || $k === \__McTokId::T_READONLY
            || $k === \__McTokId::T_CLASS_C || $k === \__McTokId::T_DIR || $k === \__McTokId::T_FILE || $k === \__McTokId::T_FUNC_C || $k === \__McTokId::T_LINE || $k === \__McTokId::T_METHOD_C
            || $k === \__McTokId::T_NS_C || $k === \__McTokId::T_TRAIT_C;
    }

    /** PsrAutoloadingFixer::supports for the current file */
    private function supported(): bool
    {
        if ($this->path === '') {
            return false;
        }
        $base = \basename($this->path);
        if (!\str_ends_with($base, '.php')) {
            return false;
        }
        $name = \substr($base, 0, -4);
        if (!Preg::match('/^(?:(?!(?<!\*)\d)[^\x00-\x2f\x3a-\x40\x5b-\x5e\x60\x7b-\x7f]++)$/', $name)) {
            return false;
        }
        $probe = Source::parse('<?php class ' . $name . ' {}');
        if ($probe->n < 3 || self::keywordKind($probe->kind[2]) || $probe->kind[2] !== \__McTokId::T_STRING) {
            return false;
        }
        return !Preg::match('{[/\\\\](stub|fixture)s?[/\\\\]}i', $this->path);
    }

    protected function candidate(Source $s): bool
    {
        $has = false;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if (($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM) && $s->ct[$i] === 0) {
                $has = true;
                break;
            }
        }
        return $has && $this->supported();
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        if ($this->dir !== '' && !\str_starts_with($this->path, $this->dir)) {
            return;
        }
        $namespace = null;
        $nsStart = -1;
        $nsEnd = -1;
        $classyName = null;
        $classyIdx = -1;
        $m = $t->count();
        for ($index = 0; $index < $m; $index++) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_NAMESPACE) {
                if ($namespace !== null) {
                    return;
                }
                $nsStart = $t->meaningful($index, 1);
                $nsEnd = self::nextOf($t, $nsStart, 59);
                $code = '';
                for ($j = $nsStart; $j < $nsEnd; $j++) {
                    $code .= $t->i[$j] === -1 ? $t->c[$j] : $t->content($j);
                }
                $namespace = \trim($code);
            } elseif (($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM)) {
                if ($t->i[$index] >= 0 && \Fmt\Analyzer::isAnonymousClass($s, $t->i[$index])) {
                    continue;
                }
                if ($classyName !== null) {
                    return;
                }
                $classyIdx = $t->meaningful($index, 1);
                $classyName = $t->content($classyIdx);
            }
        }
        if ($classyName === null) {
            return;
        }
        $expected = $this->classyName($namespace, $classyName);
        if ($classyName !== $expected) {
            if (!Preg::match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $expected)) {
                $s->abort = true; // a directory name that is no identifier: upstream's result does not lint, the file stays as it is
                return;
            }
            self::setCode($t, $classyIdx, \__McTokId::T_STRING, $expected);
        }
        if ($this->dir === '' || $namespace === null) {
            return;
        }
        if (!\is_dir($this->dir)) {
            return;
        }
        $configured = \realpath($this->dir);
        $fileDir = \dirname($this->path);
        if ($configured === false || \strlen($configured) >= \strlen($fileDir)) {
            return;
        }
        $newNs = \substr(\str_replace('/', '\\', $fileDir), \strlen($configured) + 1);
        $origNs = \substr($namespace, -\strlen($newNs));
        if ($origNs !== $newNs && \strtolower($origNs) === \strtolower($newNs)) {
            for ($i = $nsStart; $i <= $nsEnd; $i++) {
                $t->clearAt($i);
            }
            $namespace = \substr($namespace, 0, -\strlen($newNs)) . $newNs;
            foreach (\explode('\\', $namespace) as $part) {
                if (!Preg::match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $part)) {
                    $s->abort = true; // as above: the rewritten namespace would not lint
                    return;
                }
            }
            $items = [];
            foreach (\explode('\\', $namespace) as $x => $part) {
                if ($x > 0) {
                    $items[] = FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\');
                }
                $items[] = FlatTokens::code(\__McTokId::T_STRING, $part);
            }
            $items[] = FlatTokens::code(59, ';');
            $t->insertAt($nsStart, $items);
        }
    }

    private function classyName(?string $namespace, string $current): string
    {
        $name = \basename($this->path, '.php');
        $max = $this->maxNamespace($namespace);
        if ($this->dir !== '') {
            return ($max !== '' ? \str_replace('\\', '_', $max) . '_' : '') . $name;
        }
        $parts = \array_reverse(\explode('\\', $max));
        foreach ($parts as $part) {
            $cand = $part . '_' . $name;
            if (\strtolower($cand) !== \strtolower(\substr($current, -\strlen($cand)))) {
                break;
            }
            $name = $cand;
        }
        return $name;
    }

    private function maxNamespace(?string $namespace): string
    {
        if ($this->dir === '') {
            $root = \dirname($this->path);
            while ($root !== \dirname($root)) {
                $root = \dirname($root);
            }
        } else {
            $root = (string) \realpath($this->dir);
        }
        $loc = \trim(\str_replace('/', '\\', \substr(\dirname($this->path), \strlen($root))), '\\');
        if ($namespace === null) {
            return $loc;
        }
        $locRev = \array_reverse(\explode('\\', $loc));
        $nsRev = \array_reverse(\explode('\\', $namespace));
        foreach ($nsRev as $key => $part) {
            if (!isset($locRev[$key])) {
                break;
            }
            if (\strtolower($part) !== \strtolower($locRev[$key])) {
                break;
            }
            unset($locRev[$key]);
        }
        return \implode('\\', \array_reverse($locRev));
    }
}
