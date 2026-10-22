<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\NumericLiteralSeparatorFixer (priority 0). Integer / float literals get `_` every 3 digits (binary 8, hex 2, octal `0o` 3,
 * legacy octal 3), or lose theirs (strategy no_separator); literals with underscores are kept unless override_existing. Text rule over T_LNUMBER / T_DNUMBER.
 */
final class NumericLiteralSeparator extends TextRule
{
    public function __construct(private bool $override, private bool $separate)
    {
    }

    public function name(): string
    {
        return 'numeric_literal_separator';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_LNUMBER, \__McTokId::T_DNUMBER];
    }

    public function fixText(Source $s, int $i): ?string
    {
        if ($s->kind[$i] !== \__McTokId::T_LNUMBER && $s->kind[$i] !== \__McTokId::T_DNUMBER) {
            return null;
        }
        $old = $s->text($i);
        $new = $this->format($old);
        return $new === $old ? null : $new;
    }

    private function format(string $value): string
    {
        if (!$this->separate) {
            return \str_contains($value, '_') ? \str_replace('_', '', $value) : $value;
        }
        if ($this->override) {
            $value = \str_replace('_', '', $value);
        } elseif (\str_contains($value, '_')) {
            return $value;
        }
        $lower = \strtolower($value);
        if (\str_starts_with($lower, '0b')) {
            return self::everyRight($value, 8, 2);
        }
        if (\str_starts_with($lower, '0x')) {
            return self::everyRight($value, 2, 2);
        }
        if (\str_starts_with($lower, '0o')) {
            return self::everyRight($value, 3, 2);
        }
        if (\str_starts_with($lower, '0') && !\str_contains($lower, '.')) {
            return self::everyRight($value, 3, 1);
        }
        \preg_match('/([0-9-_]+)?((\.)([0-9_]*))?((e)([0-9-_]+))?/i', $value, $m);
        $integer = $m[1] ?? '';
        $joined = self::everyRight($integer, 3, \str_contains($integer, '-') ? 1 : 0);
        $dot = $m[3] ?? '';
        if ($dot !== '') {
            $integer = $m[4] ?? '';
            $joined = $joined . $dot . self::everyLeft($integer, 3, \str_contains($integer, '-') ? 1 : 0);
        }
        $e = $m[6] ?? '';
        if ($e !== '') {
            $integer = $m[7] ?? '';
            $joined = $joined . $e . self::everyRight($integer, 3, \str_contains($integer, '-') ? 1 : 0);
        }
        return $joined;
    }

    private static function everyRight(string $value, int $length, int $offset): string
    {
        $position = $length * -1;
        while ($position > -(\strlen($value) - $offset)) {
            $value = \substr_replace($value, '_', $position, 0);
            $position -= $length + 1;
        }
        return $value;
    }

    private static function everyLeft(string $value, int $length, int $offset): string
    {
        $position = $length;
        while ($position < \strlen($value)) {
            $value = \substr_replace($value, '_', $position, $offset);
            $position += $length + 1;
        }
        return $value;
    }
}
