<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/** Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitAssertNewNamesFixer (priority -10, risky). `$this->assertFileNotExists()` -> `assertFileDoesNotExist()` and the other renamed PHPUnit assertions (same-class calls only). */
final class PhpUnitAssertNewNames extends PhpUnitClassRule
{
    public function name(): string
    {
        return 'php_unit_assert_new_names';
    }

    public function priority(): int
    {
        return -10;
    }

    private static function replacement(string $lower): string
    {
        return match ($lower) {
            'assertnotisreadable' => 'assertIsNotReadable',
            'assertnotiswritable' => 'assertIsNotWritable',
            'assertdirectorynotexists' => 'assertDirectoryDoesNotExist',
            'assertfilenotexists' => 'assertFileDoesNotExist',
            'assertdirectorynotisreadable' => 'assertDirectoryIsNotReadable',
            'assertdirectorynotiswritable' => 'assertDirectoryIsNotWriteable',
            'assertfilenotisreadable' => 'assertFileIsNotReadable',
            'assertfilenotiswritable' => 'assertFileIsNotWriteable',
            'assertregexp' => 'assertMatchesRegularExpression',
            'assertnotregexp' => 'assertDoesNotMatchRegularExpression',
            default => '',
        };
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        for ($index = $end; $index > $start; $index--) {
            $index = self::prevString($t, $index);
            if ($index < 0) {
                return;
            }
            $lower = \strtolower($t->content($index));
            if (!\str_starts_with($lower, 'assert')) {
                continue;
            }
            $open = $t->meaningful($index, 1);
            if (!self::is($t, $open, 40)) {
                continue;
            }
            if (!PhpUnitConstruct::sameClassCall($t, $index)) {
                continue;
            }
            $r = self::replacement($lower);
            if ($r !== '') {
                self::setCode($t, $index, \__McTokId::T_STRING, $r);
            }
        }
    }
}
