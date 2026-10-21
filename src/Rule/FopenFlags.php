<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\FopenFlagRule;

/** Parity target: PhpCsFixer\Fixer\FunctionNotation\FopenFlagsFixer (priority 0, risky). The mode of `fopen` loses `t`; `b` is added (b_mode) or removed. */
final class FopenFlags extends FopenFlagRule
{
    public function __construct(private bool $bMode)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'fopen_flags';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function fixFlag(FlatTokens $t, int $a, int $b): void
    {
        $f = self::soleString($t, $a, $b);
        if ($f < 0) {
            return;
        }
        $content = $t->content($f);
        $quote = $content[0];
        if ($quote === 'b' || $quote === 'B') {
            $bin = $quote;
            $quote = $content[1];
            $mode = \substr($content, 2, -1);
        } else {
            $bin = '';
            $mode = \substr($content, 1, -1);
        }
        if (!self::validMode($mode)) {
            return;
        }
        $mode = \str_replace('t', '', $mode);
        if ($this->bMode) {
            if (!\str_contains($mode, 'b')) {
                $mode .= 'b';
            }
        } else {
            $mode = \str_replace('b', '', $mode);
        }
        $new = $bin . $quote . $mode . $quote;
        if ($content !== $new) {
            self::setCode($t, $f, \__McTokId::T_CONSTANT_ENCAPSED_STRING, $new);
        }
    }
}
