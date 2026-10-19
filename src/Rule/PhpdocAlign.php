<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\Doc\TypeExpression;
use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer (priority -42): consecutive annotation lines (and their description
 * continuation lines) aligned in columns (tag, hint, variable / signature, description), vertically or left.
 */
final class PhpdocAlign extends DocRule
{
    public const TAGS_WITH_NAME = ['param', 'property', 'property-read', 'property-write', 'phpstan-param', 'phpstan-property', 'phpstan-property-read',
        'phpstan-property-write', 'phpstan-assert', 'phpstan-assert-if-true', 'phpstan-assert-if-false', 'psalm-param', 'psalm-param-out',
        'psalm-property', 'psalm-property-read', 'psalm-property-write', 'psalm-assert', 'psalm-assert-if-true', 'psalm-assert-if-false'];
    public const TAGS_WITH_METHOD_SIGNATURE = ['method', 'phpstan-method', 'psalm-method'];

    private string $regex;
    private string $regexCommentLine;

    /**
     * @param string[] $tags
     * @param array<string, int> $spacingMap per tag (`_default` key), used when $spacing is 0
     */
    public function __construct(private array $tags, private bool $vertical, private int $spacing, private array $spacingMap, private string $lineEnding)
    {
        $withName = \array_values(\array_intersect($tags, self::TAGS_WITH_NAME));
        $withSig = \array_values(\array_intersect($tags, self::TAGS_WITH_METHOD_SIGNATURE));
        $without = \array_values(\array_diff($tags, $withName, $withSig));
        $indent = '^(?P<indent>(?:\ {2}|\t)*)\ ?';
        $types = [];
        if ($withName !== []) {
            $types[] = '(?P<tag>' . \implode('|', $withName) . ')\s+(?P<hint>(?:' . TypeExpression::REGEX_TYPES . ')?)\s*(?P<var>(?:&|\.{3})?\$\S+)';
        }
        if ($without !== []) {
            $types[] = '(?P<tag2>' . \implode('|', $without) . ')\s+(?P<hint2>(?:' . TypeExpression::REGEX_TYPES . ')?)';
        }
        if ($withSig !== []) {
            $types[] = '(?P<tag3>' . \implode('|', $withSig) . ')(\s+(?P<static>static))?(\s+(?P<hint3>(?:' . TypeExpression::REGEX_TYPES . ')?))\s+(?P<signature>.+\))';
        }
        $desc = '(?:\s+(?P<desc>\V*))';
        $this->regex = '/' . $indent . '\*\h*@(?J)(?:' . \implode('|', $types) . ')' . $desc . '\h*\r?$/';
        $this->regexCommentLine = '/' . $indent . '\*(?!\h?+@)(?:\s+(?P<desc>\V+))(?<!\*\/)\r?$/';
    }

    public function name(): string
    {
        return 'phpdoc_align';
    }

    public function priority(): int
    {
        return -42;
    }

    public function prepare(Source $s): bool
    {
        return $this->tags !== [];
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        // a matching line starts with blanks and a star: never the first line, so a one-line docblock is left alone
        if (!\str_contains($doc, '@') || \strpbrk($doc, "\r\n") === false) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $this->fixDocBlock($d);
        return $d->getContent();
    }

    private function spacingFor(?string $tag): int
    {
        if ($this->spacing > 0) {
            return $this->spacing;
        }
        return $this->spacingMap[$tag ?? ''] ?? $this->spacingMap['_default'] ?? 1;
    }

    /** @return array<string, string>|null tag ('' = a description line), hint, var, static, desc, indent */
    private function getMatches(string $line, bool $commentOnly = false): ?array
    {
        if (\str_contains($line, '@') && Preg::match($this->regex, $line, $m)) {
            $r = ['tag' => (string) ($m['tag'] ?? ''), 'hint' => (string) ($m['hint'] ?? ''), 'var' => (string) ($m['var'] ?? ''),
                'static' => (string) ($m['static'] ?? ''), 'desc' => (string) ($m['desc'] ?? ''), 'indent' => (string) ($m['indent'] ?? ''), 'kind' => 't'];
            if ((string) ($m['tag2'] ?? '') !== '') {
                $r['tag'] = (string) $m['tag2'];
                $r['hint'] = (string) ($m['hint2'] ?? '');
                $r['var'] = '';
            }
            if ((string) ($m['tag3'] ?? '') !== '') {
                $r['tag'] = (string) $m['tag3'];
                $r['hint'] = (string) ($m['hint3'] ?? '');
                $r['var'] = (string) ($m['signature'] ?? '');
                if ($r['hint'] === '' && $r['static'] !== '') {
                    $r['hint'] = $r['static'];
                    $r['static'] = '';
                }
            }
            $r['hint'] = \trim($r['hint']);
            return $r;
        }
        if ($commentOnly && Preg::match($this->regexCommentLine, $line, $m)) {
            return ['tag' => '', 'hint' => '', 'var' => '', 'static' => '', 'desc' => (string) ($m['desc'] ?? ''), 'indent' => (string) ($m['indent'] ?? ''), 'kind' => 'c'];
        }
        return null;
    }

    private function indent(int $vertical, int $left = 1): string
    {
        return \str_repeat(' ', $this->vertical ? $vertical : $left);
    }

    private function fixDocBlock(DocBlock $d): void
    {
        $le = $this->lineEnding;
        $l = \count($d->lines);
        for ($i = 0; $i < $l; $i++) {
            $m = $this->getMatches($d->lines[$i]->get());
            if ($m === null) {
                continue;
            }
            $current = $i;
            $items = [$m];
            while (true) {
                $i++;
                if ($i >= $l) {
                    break 2;
                }
                $m = $this->getMatches($d->lines[$i]->get(), true);
                if ($m === null) {
                    break;
                }
                $items[] = $m;
            }
            $hasStatic = false;
            $tagMax = 0;
            $hintMax = 0;
            $varMax = 0;
            foreach ($items as $it) {
                if ($it['kind'] === 'c') {
                    continue;
                }
                $hasStatic = $hasStatic || $it['static'] !== '';
                $tagMax = \max($tagMax, \strlen($it['tag']));
                $hintMax = \max($hintMax, \strlen($it['hint']));
                $varMax = \max($varMax, \strlen($it['var']));
            }
            $open = null;
            $sp = $this->spacingFor(null);
            foreach ($items as $j => $it) {
                if ($it['kind'] === 'c') {
                    if ($it['desc'][0] === '@') {
                        $d->lines[$current + $j]->set($it['indent'] . ' * ' . $it['desc'] . $le);
                        continue;
                    }
                    $extra = 2 * $sp;
                    $otag = $open === null ? '' : $open['tag'];
                    if (\in_array($otag, self::TAGS_WITH_NAME, true) || \in_array($otag, self::TAGS_WITH_METHOD_SIGNATURE, true)) {
                        $extra += $varMax + $sp;
                    }
                    if ($hasStatic) {
                        $extra += 7;
                    }
                    $line = $it['indent'] . ' * ' . (($open !== null && $open['hint'] !== '') ? ' ' : '')
                        . $this->indent($tagMax + $hintMax + $extra, $this->leftDescIndent($items, $j)) . $it['desc'];
                    $d->lines[$current + $j]->set($line . $le);
                    continue;
                }
                $sp = $this->spacingFor($it['tag']);
                $open = $it;
                $line = $it['indent'] . ' * @' . $it['tag'];
                if ($hasStatic) {
                    $line .= $this->indent($tagMax - \strlen($it['tag']) + $sp, $it['static'] !== '' ? $sp : 0)
                        . ($it['static'] !== '' ? $it['static'] : $this->indent(6, 0));
                    $hv = $sp;
                } else {
                    $hv = $tagMax - \strlen($it['tag']) + $sp;
                }
                $line .= $this->indent($hv, $it['hint'] !== '' ? $sp : 0) . $it['hint'];
                if ($it['var'] !== '') {
                    $line .= $this->indent(($hintMax !== 0 ? $hintMax : -1) - \mb_strlen($it['hint']) + $sp, $sp) . $it['var']
                        . ($it['desc'] !== '' ? $this->indent($varMax - \strlen($it['var']) + $sp, $sp) . $it['desc'] : '');
                } elseif ($it['desc'] !== '') {
                    $line .= $this->indent($hintMax - \strlen($it['hint']) + $sp, $sp) . $it['desc'];
                }
                $d->lines[$current + $j]->set($line . $le);
            }
        }
    }

    /** @param array<int, array<string, string>> $items */
    private function leftDescIndent(array $items, int $index): int
    {
        if ($this->vertical) {
            return 0;
        }
        $it = null;
        for (; $index >= 0; $index--) {
            $it = $items[$index];
            if ($it['kind'] !== 'c') {
                break;
            }
        }
        if ($it === null) {
            return 0;
        }
        $sp = $this->spacingFor($it['kind'] === 'c' ? null : $it['tag']);
        return self::sentence($it['static'], $sp) + ($it['kind'] === 'c' ? 0 : self::sentence($it['tag'], $sp))
            + self::sentence($it['hint'], $sp) + self::sentence($it['var'], $sp);
    }

    private static function sentence(string $s, int $sp): int
    {
        $n = \strlen($s);
        return $n === 0 ? 0 : $n + $sp;
    }
}
