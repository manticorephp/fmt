<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\LanguageConstruct\DirConstantFixer (priority 40, risky). `dirname(__FILE__)` -> `__DIR__`. Whole-file FlatTokens port. */
final class DirConstant extends FlatRule
{
    public function name(): string
    {
        return 'dir_constant';
    }

    public function priority(): int
    {
        return 40;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_FILE) {
                return self::hasCallOf($s, ['dirname']);
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $cur = 0;
        while (true) {
            $b = $this->findCall($s, $t, 'dirname', $cur, $t->count() - 1);
            if ($b === null) {
                return;
            }
            [$name, $open, $close] = $b;
            $cur = $open;
            $right = $t->meaningful($close, -1);
            $comma = -1;
            if (self::is($t, $right, 44)) {
                $comma = $right;
                $right = $t->meaningful($right, -1);
            }
            if (!self::is($t, $right, \__McTokId::T_FILE)) {
                continue;
            }
            $left = $t->meaningful($open, 1);
            if (!self::is($t, $left, \__McTokId::T_FILE)) {
                continue;
            }
            $ns = $t->meaningful($name, -1);
            if (self::is($t, $ns, \__McTokId::T_NS_SEPARATOR)) {
                self::removeWs($t, $ns, 1);
                $t->clearAt($ns);
            }
            if ($comma >= 0) {
                $nn = $t->nonWhitespace($comma, 1);
                if (!$t->isComment($nn)) {
                    self::removeWs($t, $comma, 1);
                }
                self::clearMerge($t, $comma);
            }
            $nn = $t->nonWhitespace($close, 1);
            if (!$t->isComment($nn)) {
                self::removeWs($t, $close, -1);
            }
            self::clearMerge($t, $close);
            $nn = $t->nonWhitespace($open, 1);
            if (!$t->isComment($nn)) {
                self::removeWs($t, $open, -1);
            }
            self::removeWs($t, $open, 1);
            self::clearMerge($t, $open);
            self::setCode($t, $left, \__McTokId::T_DIR, '__DIR__');
            self::clearMerge($t, $name);
        }
    }
}
