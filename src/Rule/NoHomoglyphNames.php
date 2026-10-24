<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Preg;
use Fmt\Source;
use Fmt\TextRule;

/**
 * Parity target: PhpCsFixer\Fixer\Naming\NoHomoglyphNamesFixer (priority 0, risky). Every non-ASCII character of a T_STRING / T_VARIABLE that is in upstream's homoglyph table is
 * replaced by its ASCII look-alike. Text rule; the table is upstream's.
 */
final class NoHomoglyphNames extends TextRule
{
    private const REPLACEMENTS = [
        'O' => '0',
        '０' => '0',
        'I' => '1',
        '１' => '1',
        '２' => '2',
        '３' => '3',
        '４' => '4',
        '５' => '5',
        '６' => '6',
        '７' => '7',
        '８' => '8',
        '９' => '9',
        'Α' => 'A',
        'А' => 'A',
        'Ａ' => 'A',
        'ʙ' => 'B',
        'Β' => 'B',
        'В' => 'B',
        'Ｂ' => 'B',
        'Ϲ' => 'C',
        'С' => 'C',
        'Ⅽ' => 'C',
        'Ｃ' => 'C',
        'Ⅾ' => 'D',
        'Ｄ' => 'D',
        'Ε' => 'E',
        'Е' => 'E',
        'Ｅ' => 'E',
        'Ϝ' => 'F',
        'Ｆ' => 'F',
        'ɢ' => 'G',
        'Ԍ' => 'G',
        'Ｇ' => 'G',
        'ʜ' => 'H',
        'Η' => 'H',
        'Н' => 'H',
        'Ｈ' => 'H',
        'l' => 'I',
        'Ι' => 'I',
        'І' => 'I',
        'Ⅰ' => 'I',
        'Ｉ' => 'I',
        'Ј' => 'J',
        'Ｊ' => 'J',
        'Κ' => 'K',
        'К' => 'K',
        'K' => 'K',
        'Ｋ' => 'K',
        'ʟ' => 'L',
        'Ⅼ' => 'L',
        'Ｌ' => 'L',
        'Μ' => 'M',
        'М' => 'M',
        'Ⅿ' => 'M',
        'Ｍ' => 'M',
        'ɴ' => 'N',
        'Ν' => 'N',
        'Ｎ' => 'N',
        'Ο' => 'O',
        'О' => 'O',
        'Ｏ' => 'O',
        'Ρ' => 'P',
        'Р' => 'P',
        'Ｐ' => 'P',
        'Ｑ' => 'Q',
        'ʀ' => 'R',
        'Ｒ' => 'R',
        'Ѕ' => 'S',
        'Ｓ' => 'S',
        'Τ' => 'T',
        'Т' => 'T',
        'Ｔ' => 'T',
        'Ｕ' => 'U',
        'Ѵ' => 'V',
        'Ⅴ' => 'V',
        'Ｖ' => 'V',
        'Ｗ' => 'W',
        'Χ' => 'X',
        'Х' => 'X',
        'Ⅹ' => 'X',
        'Ｘ' => 'X',
        'ʏ' => 'Y',
        'Υ' => 'Y',
        'Ү' => 'Y',
        'Ｙ' => 'Y',
        'Ζ' => 'Z',
        'Ｚ' => 'Z',
        '＿' => '_',
        'ɑ' => 'a',
        'а' => 'a',
        'ａ' => 'a',
        'Ь' => 'b',
        'ｂ' => 'b',
        'ϲ' => 'c',
        'с' => 'c',
        'ⅽ' => 'c',
        'ｃ' => 'c',
        'ԁ' => 'd',
        'ⅾ' => 'd',
        'ｄ' => 'd',
        'е' => 'e',
        'ｅ' => 'e',
        'ｆ' => 'f',
        'ɡ' => 'g',
        'ｇ' => 'g',
        'һ' => 'h',
        'ｈ' => 'h',
        'ɩ' => 'i',
        'і' => 'i',
        'ⅰ' => 'i',
        'ｉ' => 'i',
        'ј' => 'j',
        'ｊ' => 'j',
        'ｋ' => 'k',
        'ⅼ' => 'l',
        'ｌ' => 'l',
        'ⅿ' => 'm',
        'ｍ' => 'm',
        'ｎ' => 'n',
        'ο' => 'o',
        'о' => 'o',
        'ｏ' => 'o',
        'р' => 'p',
        'ｐ' => 'p',
        'ｑ' => 'q',
        'ｒ' => 'r',
        'ѕ' => 's',
        'ｓ' => 's',
        'ｔ' => 't',
        'ｕ' => 'u',
        'ν' => 'v',
        'ѵ' => 'v',
        'ⅴ' => 'v',
        'ｖ' => 'v',
        'ѡ' => 'w',
        'ｗ' => 'w',
        'х' => 'x',
        'ⅹ' => 'x',
        'ｘ' => 'x',
        'у' => 'y',
        'ｙ' => 'y',
        'ｚ' => 'z',
    ];

    public function name(): string
    {
        return 'no_homoglyph_names';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        return \preg_match('/[\x80-\xff]/', $s->code) === 1;
    }

    /** @return int[] */
    public function textKinds(): array
    {
        return [\__McTokId::T_STRING, \__McTokId::T_VARIABLE];
    }

    public function fixText(Source $s, int $i): ?string
    {
        $k = $s->kind[$i];
        if ($k !== \__McTokId::T_STRING && $k !== \__McTokId::T_VARIABLE) {
            return null;
        }
        $t = $s->text($i);
        if (\preg_match('/[\x80-\xff]/', $t) !== 1) {
            return null;
        }
        $count = 0;
        $r = Preg::replaceCallback('/[^[:ascii:]]/', static fn(array $m): string => self::REPLACEMENTS[$m[0]] ?? $m[0], $t);
        return $r === $t ? null : $r;
    }
}
