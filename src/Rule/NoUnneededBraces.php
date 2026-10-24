<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\NoUnneededBracesFixer (priority 40). A `{ ... }` block that stands directly behind `{`, `}`, the open tag,
 * `:` or `;` loses its braces; a one-line group import with a single entry loses its braces; with `namespaces`, the braces of the only namespace of the
 * file (named, nothing behind the `}`) become `;`. Cleared tokens merge the whitespace around them: as text that is deleting the token unit.
 */
final class NoUnneededBraces extends EditRule
{
    /** @var array<int, bool> */
    private array $del = [];

    public function __construct(private bool $namespaces) {}

    public function name(): string
    {
        return 'no_unneeded_braces';
    }

    public function priority(): int
    {
        return 40;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === 125 || $s->ct[$i] === CT::GROUP_IMPORT_BRACE_CLOSE) {
                return true;
            }
        }
        return false;
    }

    private function prev(Source $s, int $i): int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (!isset($this->del[$j])) {
                return $j;
            }
        }
        return -1;
    }

    public function fixFile(Source $s): void
    {
        $this->del = [];
        $n = $s->n;
        for ($i = $n - 1; $i > 0; $i--) {
            $c = $s->ct[$i];
            if ($c === CT::GROUP_IMPORT_BRACE_OPEN) {
                $e = $s->pair[$i];
                if ($e < 0 || self::multiline($s, $i, $e)) {
                    continue;
                }
                $comma = false;
                for ($j = $i + 1; $j < $e; $j++) {
                    if ($s->kind[$j] === 44 && $s->ct[$j] === 0) {
                        $comma = true;
                        break;
                    }
                }
                if (!$comma) {
                    if ($s->gapBefore[$i] . $s->gapBefore[$i + 1] !== '') {
                        $s->lintUse = true; // `use A\ B;` would not lint unless a later rule removes it: upstream lints once, at the end
                    }
                    $this->del[$e] = true;
                    $this->del[$i] = true;
                }
                continue;
            }
            if ($c !== 0 || $s->kind[$i] !== 123) {
                continue;
            }
            $p = $this->prev($s, $i);
            if ($p < 0) {
                continue;
            }
            $pk = $s->kind[$p];
            if ($pk === \__McTokId::T_OPEN_TAG || ($s->ct[$p] === 0 && ($pk === 123 || $pk === 125 || $pk === 58 || $pk === 59))) {
                $e = $s->pair[$i];
                if ($e > 0) {
                    $this->del[$e] = true;
                    $this->del[$i] = true;
                }
            }
        }
        $ns = null;
        if ($this->namespaces) {
            $ns = $this->namespaceBlock($s);
        }
        foreach ($this->del as $j => $_) {
            $s->edit(Source::tokAt($j), Source::tokAt($j) + 1, '');
        }
        if ($ns !== null) {
            $s->edit(Source::gapAt($ns[0]), Source::tokAt($ns[0]) + 1, $ns[1] . ';');
        }
        $this->del = [];
    }

    private static function multiline(Source $s, int $o, int $e): bool
    {
        for ($k = $o + 1; $k <= $e; $k++) {
            if (($s->gapFlags[$k] & Gap::G_NL) === 0) {
                continue;
            }
            $g = $s->gapBefore[$k];
            if (!Gap::hasComment($g)) {
                if (!($k > 0 && $s->kind[$k - 1] === \__McTokId::T_END_HEREDOC)) {
                    return true;
                }
                continue;
            }
            foreach (Gap::pieces($g) as $x) {
                if (Gap::isSpace($x) && \str_contains($x, "\n")) {
                    return true;
                }
            }
        }
        return false;
    }

    /** @return array{0:int,1:string}|null the `{` of the only, named namespace and its new leading gap; the `}` is added to the deletions */
    private function namespaceBlock(Source $s): ?array
    {
        $n = $s->n;
        $first = -1;
        $count = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_NAMESPACE && $s->ct[$i] === 0) {
                $count++;
                if ($first < 0) {
                    $first = $i;
                }
            }
        }
        if ($count !== 1) {
            return null;
        }
        $i = $first + 1;
        $named = false;
        while ($i < $n && $s->ct[$i] === 0 && ($s->kind[$i] === \__McTokId::T_STRING || $s->kind[$i] === \__McTokId::T_NS_SEPARATOR)) {
            $i++;
            $named = true;
        }
        if (!$named || $i >= $n || $s->kind[$i] !== 123 || $s->ct[$i] !== 0) {
            return null;
        }
        $e = $s->pair[$i];
        if ($e < 0) {
            return null;
        }
        $after = $e + 1;
        while ($after < $n && isset($this->del[$after])) {
            $after++;
        }
        if ($after < $n && !($s->kind[$after] === \__McTokId::T_CLOSE_TAG && $after + 1 >= $n)) {
            return null;
        }
        $this->del[$e] = true;
        $g = $s->gapBefore[$i];
        if ($g !== '') {
            $p = Trivia::pieces($g);
            $m = \count($p);
            if (Trivia::blank($p[$m - 1]) && ($m < 2 || !Trivia::isComment($p[$m - 2]))) {
                \array_pop($p);
                $g = \implode('', $p);
            }
        }
        return [$i, $g];
    }
}
