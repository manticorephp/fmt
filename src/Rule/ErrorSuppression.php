<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\ErrorSuppressionFixer (priority 0, risky). `@` is added in front of `trigger_error(.., E_USER_DEPRECATED)` and (noise_remaining_usages)
 * removed from other global function calls (not the excluded ones: compared with the call's own spelling against the lower-cased list). Whole-file FlatTokens port.
 */
final class ErrorSuppression extends FlatRule
{
    /** @param string[] $exclude */
    public function __construct(private bool $mute, private bool $noise, private array $exclude)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'error_suppression';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        if (self::hasCallOf($s, ['trigger_error'])) {
            return true;
        }
        if (!$this->noise) {
            return false;
        }
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === 64) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $excluded = [];
        foreach ($this->exclude as $e) {
            $excluded[] = \strtolower($e);
        }
        for ($q = $t->count() - 1; $q >= 0; $q--) {
            if ($this->noise && self::is($t, $q, 64)) {
                $t->clearAt($q);
                continue;
            }
            if ($t->i[$q] === -1 || $t->k[$q] !== \__McTokId::T_STRING) {
                continue;
            }
            $pp = $t->meaningful($q, -1);
            if (self::is($t, $pp, \__McTokId::T_NS_SEPARATOR)) {
                $pp = $t->meaningful($pp, -1);
            }
            if (!self::is($t, $pp, 64) && \strtolower($t->content($q)) !== 'trigger_error') {
                continue;
            }
            if (!$this->isGlobalCall($s, $t, $q)) {
                continue;
            }
            $fn = $q;
            $start = $q;
            $prev = $t->meaningful($q, -1);
            if (self::is($t, $prev, \__McTokId::T_NS_SEPARATOR)) {
                $start = $prev;
                $prev = $t->meaningful($start, -1);
            }
            $q = $prev;
            if ($this->isDeprecation($t, $fn)) {
                if (!$this->mute) {
                    continue;
                }
                if (self::is($t, $prev, 64)) {
                    continue;
                }
                $t->insertAt($start, [FlatTokens::code(64, '@')]);
                continue;
            }
            if (!self::is($t, $prev, 64)) {
                continue;
            }
            if ($this->noise && !\in_array($t->content($fn), $excluded, true)) {
                $t->clearAt($q);
            }
        }
    }

    private function isDeprecation(FlatTokens $t, int $q): bool
    {
        if (\strtolower($t->content($q)) !== 'trigger_error') {
            return false;
        }
        $open = -1;
        $m = $t->count();
        for ($j = $q + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && ($t->k[$j] === \__McTokId::T_STRING || $t->k[$j] === 40)) {
                $open = $j;
                break;
            }
        }
        $end = $t->blockEnd($open, 41);
        $prev = $t->meaningful($end, -1);
        if (self::is($t, $prev, 44)) {
            $prev = $t->meaningful($prev, -1);
        }
        return self::is($t, $prev, \__McTokId::T_STRING) && $t->content($prev) === 'E_USER_DEPRECATED';
    }
}
