<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocTagTypeFixer (priority 0): a tag written as an annotation or inline (`{@tag}`) as configured. */
final class PhpdocTagType extends DocRule
{
    private const TAG_REGEX = '/^(?:
        (?<tag>
            (?:@(?<tag_name>.+?)(?:\s.+)?)
        )
        |
        {(?<inlined_tag>
            (?:@(?<inlined_tag_name>.+?)(?:\s.+)?)
        )}
    )$/x';

    private string $regex;

    /** @param array<string, string> $tags lowercased tag name => 'annotation' | 'inline' */
    public function __construct(private array $tags)
    {
        $q = [];
        foreach ($tags as $t => $x) {
            $q[] = \preg_quote((string) $t, '/');
        }
        $this->regex = '/({?@(?:' . \implode('|', $q) . ').*?(?:(?=\s\*\/)|(?=\n)}?))/i';
    }

    public function name(): string
    {
        return 'phpdoc_tag_type';
    }

    public function priority(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        return $this->tags !== [];
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, '@')) {
            return $doc;
        }
        $hit = false;
        foreach ($this->tags as $t => $x) {
            if (\stripos($doc, '@' . $t) !== false) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            return $doc;
        }
        $parts = Preg::split($this->regex, $doc, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $max = \count($parts) - 1;
        for ($j = 1; $j < $max; $j += 2) {
            if (!Preg::match(self::TAG_REGEX, $parts[$j], $m)) {
                continue;
            }
            if ((string) ($m['tag'] ?? '') !== '') {
                $tag = (string) $m['tag'];
                $tagName = (string) $m['tag_name'];
            } else {
                $tag = (string) $m['inlined_tag'];
                $tagName = (string) $m['inlined_tag_name'];
            }
            $tagName = \strtolower($tagName);
            if (!isset($this->tags[$tagName])) {
                continue;
            }
            if ($this->tags[$tagName] === 'inline') {
                $parts[$j] = '{' . $tag . '}';
                continue;
            }
            if (!self::surroundedByText($parts, $j)) {
                $parts[$j] = $tag;
            }
        }
        return \implode('', $parts);
    }

    /** @param string[] $parts */
    private static function surroundedByText(array $parts, int $j): bool
    {
        return Preg::test('/(^|\R)\h*[^@\s]\N*/', self::clean($parts[$j - 1]))
            || Preg::test('/^.*?\R\s*[^@\s]/', self::clean($parts[$j + 1]));
    }

    private static function clean(string $c): string
    {
        $c = Preg::replace('/^\/\*\*|\*\/$/', '', $c);
        return Preg::replace('/(\R)(\h*\*)?\h*/', '$1', $c);
    }
}
