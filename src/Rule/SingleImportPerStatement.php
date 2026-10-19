<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\SingleImportPerStatementFixer (priority 1). `use A, B;` becomes one statement per name: each comma
 * (any depth) turns into `;`, `use ` (+ `function ` / `const `) is inserted before the next name, and the trivia before that name ends in
 * line ending + the `use` line's indent (its last whitespace is replaced; a new break is inserted when it ends in something without "\n").
 * A group import (`use A\{B, C};`) is left alone unless group_to_single_imports.
 */
final class SingleImportPerStatement extends EditRule
{
    public function __construct(private string $le = "\n", private bool $group = true)
    {
    }

    public function name(): string
    {
        return 'single_import_per_statement';
    }

    public function priority(): int
    {
        return 1;
    }

    public function fixFile(Source $s): void
    {
        $n = $s->n;
        for ($i = 0; $i < $n; $i++) {
            if ($s->kind[$i] !== \__McTokId::T_USE || $s->ct[$i] !== 0) {
                continue;
            }
            $end = $i + 1;
            while ($end < $n && $s->kind[$end] !== 59 && $s->kind[$end] !== \__McTokId::T_CLOSE_TAG) {
                $end++;
            }
            if ($end >= $n) {
                continue;
            }
            if ($s->ct[$end - 1] === CT::GROUP_IMPORT_BRACE_CLOSE) {
                if ($this->group) {
                    $this->fixGroup($s, $i, $end);
                }
                continue;
            }
            $lead = $s->ct[$i + 1] === CT::FUNCTION_IMPORT ? 'function ' : ($s->ct[$i + 1] === CT::CONST_IMPORT ? 'const ' : '');
            $indent = null;
            for ($c = $i + 1; $c < $end; $c++) {
                if ($s->kind[$c] !== 44 || $s->ct[$c] !== 0) {
                    continue;
                }
                $indent ??= Analyzer::detectIndent($s, $i, \PHP_INT_MAX);
                $g = $s->gapBefore[$c + 1];
                $nl = $this->le . $indent;
                if ($g === '') {
                    $g = $nl; // the token before is the new `;`
                } else {
                    $p = Gap::hasComment($g) ? Gap::pieces($g) : [$g];
                    $m = \count($p) - 1;
                    if (Gap::isSpace($p[$m])) {
                        $p[$m] = $nl;
                    } elseif (!\str_contains($p[$m], "\n")) {
                        $p[] = $nl;
                    }
                    $g = \implode('', $p);
                }
                $s->edit(Source::tokAt($c), Source::tokAt($c + 1), ';' . $g . 'use ' . $lead);
            }
            $i = $end;
        }
    }

    /** fixGroupUse: `use A\{B, C as D};` becomes one `use A\B;` per member, joined by line endings, on a FlatTokens window */
    private function fixGroup(Source $s, int $i, int $end): void
    {
        $close = $end - 1;
        $t = new FlatTokens();
        $t->load($s, $i, $close);
        $m = $t->count();
        // getGroupDeclaration
        $prefix = '';
        $comment = '';
        $open = -1;
        for ($q = 1; $q < $m; $q++) {
            if ($t->isId($q, CT::GROUP_IMPORT_BRACE_OPEN)) {
                $open = $q;
                break;
            }
            if ($t->isComment($q)) {
                $comment .= $t->c[$q];
                if (!$t->isWs($q - 1) && !$t->isWs($q + 1)) {
                    $prefix .= ' ';
                }
                continue;
            }
            if ($t->isWs($q)) {
                $prefix .= ' ';
                continue;
            }
            $prefix .= $t->content($q);
        }
        if ($open < 0) {
            return;
        }
        $prefix = \rtrim($prefix);
        // getGroupStatements
        $out = [];
        $st = $prefix;
        for ($q = $open + 1; $q < $m; $q++) {
            $code = $t->isCode($q);
            $k = $t->k[$q];
            if ($code && $k === 44 && $t->isId($t->meaningful($q, 1), CT::GROUP_IMPORT_BRACE_CLOSE)) {
                continue;
            }
            if ($code && ($k === 44 || $k === CT::GROUP_IMPORT_BRACE_CLOSE)) {
                $out[] = 'use' . $st . ';';
                $st = $prefix;
                continue;
            }
            if ($t->isWs($q)) {
                $ws = $t->c[$q];
                $j = $t->meaningful($q, 1);
                $kj = $j >= 0 ? $t->k[$j] : 0;
                if ($kj === \__McTokId::T_AS && $t->isCode($j)) {
                    $st .= ' as ';
                    $q += 2;
                } elseif ($kj === CT::FUNCTION_IMPORT) {
                    $st = ' function' . $st;
                    $q += 2;
                } elseif ($kj === CT::CONST_IMPORT) {
                    $st = ' const' . $st;
                    $q += 2;
                }
                if (\strspn($ws, " \t") === \strlen($ws) || !\str_starts_with($t->content($q - 1), '//')) {
                    continue;
                }
                $st .= $ws;
                continue;
            }
            $st .= $t->content($q);
        }
        if ($comment !== '' && $out !== []) {
            $out[0] .= ' ' . $comment;
        }
        $to = $s->kind[$end] === 59 ? Source::tokAt($end) + 1 : Source::tokAt($close) + 1;
        $s->edit(Source::tokAt($i), $to, \implode($this->le, $out));
    }
}
