<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\BacktickToShellExecFixer (priority 17). `` `ls $x` `` -> `shell_exec("ls $x")`; a lone text part becomes a plain
 * string, a lone variable stays bare; a text part holding a quote or a backtick leaves the pair alone. Backticks pair from the end of the file.
 */
final class BacktickToShellExec extends EditRule
{
    public function name(): string
    {
        return 'backtick_to_shell_exec';
    }

    public function priority(): int
    {
        return 17;
    }

    public function prepare(Source $s): bool
    {
        for ($i = 1; $i < $s->n; $i++) {
            if ($s->kind[$i] === 96) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        $b = [];
        for ($i = $s->n - 1; $i > 0; $i--) {
            if ($s->kind[$i] === 96 && $s->ct[$i] === 0) {
                $b[] = $i;
            }
        }
        for ($k = 0; $k + 1 < \count($b); $k += 2) {
            $this->fix($s, $b[$k + 1], $b[$k]);
        }
    }

    private function fix(Source $s, int $open, int $close): void
    {
        $count = 0;
        $parts = [];
        for ($k = $open + 1; $k <= $close; $k++) {
            $g = $s->gapBefore[$k];
            if ($g !== '') {
                $p = Trivia::pieces($g);
                $count += \count($p);
                foreach ($p as $x) {
                    $parts[] = [0, $x];
                }
            }
            if ($k < $close) {
                $count++;
                $parts[] = [$s->kind[$k], $s->text($k)];
            }
        }
        // the closing backtick's own gap is trivia too (count includes pieces only of gaps behind the opener up to the closer)
        $out = '';
        foreach ($parts as [$kind, $text]) {
            if ($kind === \__McTokId::T_ENCAPSED_AND_WHITESPACE) {
                if (Trivia::pmatch('/[`"\']/', $text)) {
                    return;
                }
                if ($count === 1) {
                    $text = '"' . $text . '"';
                }
            }
            $out .= $text;
        }
        if ($count !== 1) {
            $out = '"' . $out . '"';
        }
        $s->edit(Source::tokAt($open), Source::tokAt($close) + 1, 'shell_exec(' . $out . ')');
    }
}
