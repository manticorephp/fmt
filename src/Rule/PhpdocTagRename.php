<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocTagRenameFixer (priority 11), and its proxy phpdoc_no_alias_tag (annotations only,
 * case sensitive). Upstream's regex is built from the (unquoted) tag names, quoted strings are skipped (`(*SKIP)(*FAIL)`).
 */
final class PhpdocTagRename extends DocRule
{
    private string $regex;
    /** every tag name is a plain word, so a docblock without one of them as a substring cannot match */
    private bool $plain = true;

    /**
     * @param array<string, string> $replacements normalized: keys trimmed (lowercased when case insensitive)
     */
    public function __construct(private string $ruleName, private array $replacements, private bool $caseSensitive, bool $fixAnnotation, bool $fixInline)
    {
        if ($fixAnnotation) {
            $re = $fixInline ? '/(["\'])[^\1]*\1(*SKIP)(*FAIL)|\b(?<=@)(?P<tag>%s)\b/' : '/(["\'])[^\1]*\1(*SKIP)(*FAIL)|(?<!\{@)(?<=@)(?P<tag>%s)(?!\})/';
        } else {
            $re = '/(?<={@)(?P<tag>%s)(?=[ \t}])/';
        }
        foreach ($replacements as $from => $to) {
            if (\preg_match('/^[A-Za-z0-9_-]+$/', (string) $from) !== 1) {
                $this->plain = false;
            }
        }
        $this->regex = \sprintf($re, \implode('|', \array_keys($replacements))) . ($caseSensitive ? '' : 'i');
    }

    public function name(): string
    {
        return $this->ruleName;
    }

    public function priority(): int
    {
        return 11;
    }

    public function prepare(Source $s): bool
    {
        return $this->replacements !== [];
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        $hit = !$this->plain;
        foreach ($this->replacements as $from => $to) {
            if ($this->caseSensitive ? \str_contains($doc, (string) $from) : \stripos($doc, (string) $from) !== false) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            return $doc;
        }
        $rep = $this->replacements;
        $ci = !$this->caseSensitive;
        return Preg::replaceCallback($this->regex, static function (array $m) use ($rep, $ci): string {
            $tag = (string) $m['tag'];
            if ($ci) {
                $tag = \strtolower($tag);
            }
            return $rep[$tag];
        }, $doc);
    }
}
