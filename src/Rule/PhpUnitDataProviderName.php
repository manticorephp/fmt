<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;
use Fmt\Preg;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitDataProviderNameFixer (priority 0, risky). A data provider used by exactly one test (and that test using only it) is renamed to
 * `<prefix><Test name without "test">/<suffix>` unless a method of that name exists; its `@dataProvider` annotations / `#[DataProvider('..')]` strings follow. Whole-file FlatTokens port.
 */
final class PhpUnitDataProviderName extends PhpUnitClassRule
{
    public function __construct(private string $prefix, private string $suffix)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_data_provider_name';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        $providers = $this->dataProviders($t, $start, $end);
        /** @var array<int, array<string, int>> $methodsProviders */
        $methodsProviders = [];
        /** @var array<string, array<int, int>> $providersMethods */
        $providersMethods = [];
        $m = $t->count();
        foreach ($providers as $p) {
            foreach ($p[2] as $u) {
                $mi = -1;
                for ($j = $u[0] + 1; $j < $m; $j++) {
                    if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_FUNCTION) {
                        $mi = $j;
                        break;
                    }
                }
                $methodsProviders[$mi][$p[1]] = $u[0];
                $providersMethods[$p[1]][$mi] = $u[0];
            }
        }
        foreach ($providers as $p) {
            if (\count($providersMethods[$p[1]]) > 1) {
                continue;
            }
            $mi = -1;
            for ($j = $p[2][0][0] + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_FUNCTION) {
                    $mi = $j;
                    break;
                }
            }
            if (\count($methodsProviders[$mi]) > 1) {
                continue;
            }
            $new = $this->newName($t, $mi);
            $clash = false;
            for ($j = $start; $j < $end; $j++) {
                if ($t->i[$j] === -1 || $t->k[$j] !== \__McTokId::T_FUNCTION) {
                    continue;
                }
                $n = $t->meaningful($j, 1);
                if ($n >= 0 && $t->i[$n] !== -1 && $t->k[$n] === \__McTokId::T_STRING && $t->content($n) === $new) {
                    $clash = true;
                    break;
                }
            }
            if ($clash) {
                continue;
            }
            foreach ($p[2] as $u) {
                self::setCode($t, $p[0], \__McTokId::T_STRING, $new);
                if ($t->i[$u[0]] === -1) {
                    $t->c[$u[0]] = Preg::replace('/(@dataProvider\s+)' . $p[1] . '/', '$1' . $new, $t->c[$u[0]]);
                } else {
                    $c = $t->content($u[0]);
                    self::setCode($t, $u[0], $t->k[$u[0]], $c[0] . $new . $c[0]);
                }
            }
        }
    }

    private function newName(FlatTokens $t, int $index): string
    {
        do {
            if ($t->i[$index] !== -1 && $t->k[$index] === \__McTokId::T_ATTRIBUTE) {
                $index = $t->blockEnd($index, \Fmt\CT::ATTRIBUTE_CLOSE);
            }
            $index = $t->meaningful($index, 1);
        } while ($index >= 0 && !($t->i[$index] !== -1 && $t->k[$index] === \__McTokId::T_STRING));
        $name = $t->content($index);
        $name = Preg::replace('/^test_*/i', '', $name);
        if ($this->prefix === '') {
            $name = \lcfirst($name);
        } elseif (\substr($this->prefix, -1) !== '_') {
            $name = \ucfirst($name);
        }
        return $this->prefix . $name . $this->suffix;
    }
}
