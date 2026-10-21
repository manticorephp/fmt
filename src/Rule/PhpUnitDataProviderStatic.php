<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/** Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitDataProviderStaticFixer (priority 0, risky). A data provider method becomes `static` (one that uses `$this` only with force). */
final class PhpUnitDataProviderStatic extends PhpUnitClassRule
{
    public function __construct(private bool $force)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_data_provider_static';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        /** @var array<int, bool> $inserts */
        $inserts = [];
        foreach ($this->dataProviders($t, $start, $end) as $p) {
            $ms = self::nextOf($t, $p[0], 123);
            if ($ms >= 0) {
                $me = $t->blockEnd($ms, 125);
                if (!$this->force) {
                    $uses = false;
                    for ($j = $ms; $j <= $me; $j++) {
                        if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_VARIABLE && $t->content($j) === '$this') {
                            $uses = true;
                            break;
                        }
                    }
                    if ($uses) {
                        continue;
                    }
                }
            }
            $fi = -1;
            for ($j = $p[0] - 1; $j >= 0; $j--) {
                if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_FUNCTION) {
                    $fi = $j;
                    break;
                }
            }
            $static = false;
            for ($i = $fi; $i >= 0; $i--) {
                $i = $t->meaningful($i, -1);
                if ($i < 0) {
                    break;
                }
                $k = $t->i[$i] === -1 ? -1 : $t->k[$i];
                if ($k === \__McTokId::T_STATIC) {
                    $static = true;
                    continue;
                }
                if ($k === \__McTokId::T_FINAL || $k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PUBLIC) {
                    continue;
                }
                break;
            }
            if ($static) {
                continue;
            }
            $inserts[$fi] = true;
        }
        \krsort($inserts);
        foreach ($inserts as $at => $_) {
            $t->insertAt($at, [FlatTokens::code(\__McTokId::T_STATIC, 'static'), FlatTokens::ws(' ')]);
        }
    }
}
