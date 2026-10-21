<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Alias\RandomApiMigrationFixer (priority 0, risky). Calls of `rand` / `srand` / `getrandmax` (and the other configured ones) are renamed after the
 * `replacements` map (default: getrandmax -> mt_getrandmax, rand -> mt_rand, srand -> mt_srand); a no-argument `rand()` replaced by `random_int` gets `0, getrandmax()`. A call whose
 * argument count does not fit stops the scan for that function, as upstream's `continue 2`. Whole-file FlatTokens port.
 */
final class RandomApiMigration extends FlatRule
{
    /** @param array<string, string> $replacements */
    public function __construct(private array $replacements)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'random_api_migration';
    }

    public function priority(): int
    {
        return 0;
    }

    public static function knownFunction(string $f): bool
    {
        return $f === 'mt_getrandmax' || $f === 'getrandmax' || $f === 'mt_rand' || $f === 'rand' || $f === 'srand' || $f === 'mt_srand' || $f === 'random_int';
    }

    /** @return int[] */
    private static function counts(string $f): array
    {
        return match ($f) {
            'mt_getrandmax', 'getrandmax' => [0],
            'mt_rand' => [1, 2],
            default => $f === 'srand' || $f === 'mt_srand' ? [0, 1] : [0, 2],
        };
    }

    protected function candidate(Source $s): bool
    {
        $names = [];
        foreach ($this->replacements as $from => $to) {
            if ($from !== $to) {
                $names[] = \strtolower((string) $from);
            }
        }
        return $names !== [] && self::hasCallOf($s, $names);
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        foreach ($this->replacements as $identity => $replacement) {
            $identity = (string) $identity;
            if ($identity === $replacement) {
                continue;
            }
            $cur = 0;
            while (true) {
                $b = $this->findCall($s, $t, \strtolower($identity), $cur, $t->count() - 1);
                if ($b === null) {
                    break;
                }
                [$name, $open, $close] = $b;
                $count = \count(self::callArguments($t, $open, $close));
                if (!\in_array($count, self::counts($identity), true)) {
                    break;
                }
                $cur = $open;
                self::setCode($t, $name, \__McTokId::T_STRING, $replacement);
                if ($count === 0 && $replacement === 'random_int') {
                    $t->insertAt($cur + 1, [FlatTokens::code(\__McTokId::T_LNUMBER, '0'), FlatTokens::code(44, ','), FlatTokens::ws(' '), FlatTokens::code(\__McTokId::T_STRING, 'getrandmax'),
                        FlatTokens::code(40, '('), FlatTokens::code(41, ')')]);
                    $cur += 6;
                }
            }
        }
    }
}
