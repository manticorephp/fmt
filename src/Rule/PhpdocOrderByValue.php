<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocOrderByValueFixer (priority -10). The annotations of each listed tag (default `covers`) are sorted by their lower-cased
 * comparable content (`@covers Foo::bar` -> `foo::bar`; a property tag by its variable name, `@method` by its name); two annotations with the same key leave
 * one (the later text, at the first one's place) and the surplus lines go, as upstream's array-keyed map does.
 */
final class PhpdocOrderByValue extends DocRule
{
    /** @param string[] $annotations */
    public function __construct(private array $annotations) {}

    public function name(): string
    {
        return 'phpdoc_order_by_value';
    }

    public function priority(): int
    {
        return -10;
    }

    public function prepare(Source $s): bool
    {
        if ($this->annotations === []) {
            return false;
        }
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_CLASS && $s->ct[$i] === 0) {
                return true;
            }
        }
        return false;
    }

    /** @param string[] $p */
    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        foreach ($this->annotations as $type) {
            if (!Preg::test('/@' . $type . '\s.+@' . $type . '\s/s', $doc)) {
                continue;
            }
            $db = new DocBlock($doc);
            $annotations = $db->getAnnotationsOfType([$type]);
            /** @var string[] $keys comparable content, in first-seen order */
            $keys = [];
            /** @var string[] $vals raw content per key */
            $vals = [];
            if ($type === 'property' || $type === 'property-read' || $type === 'property-write') {
                $pattern = '/(?s)\*\s*@' . $type . '\s+(?P<optionalTypes>.+\s+)?\$(?P<comparableContent>\S+).*/';
                $repl = '\2';
            } elseif ($type === 'method') {
                $pattern = '/(?s)\*\s*@method\s+(?P<optionalReturnTypes>.+\s+)?(?P<comparableContent>.+)\(.*/';
                $repl = '\2';
            } else {
                $pattern = '/\*\s*@' . \strtolower($type) . '\s+(?P<comparableContent>.+)/';
                $repl = '\1';
            }
            foreach ($annotations as $a) {
                $raw = $a->getContent();
                $key = Preg::replace($pattern, $repl, \strtolower(\trim($raw)));
                $at = \array_search($key, $keys, true);
                if ($at === false) {
                    $keys[] = $key;
                    $vals[] = $raw;
                } else {
                    $vals[$at] = $raw;
                }
            }
            /** @var int[] $order indexes of the keys in ksort(SORT_STRING) order */
            $order = \array_keys($keys);
            \usort($order, static fn(int $x, int $y): int => \strcmp($keys[$x], $keys[$y]));
            if ($order === \array_keys($keys)) {
                continue;
            }
            $lines = [];
            foreach ($db->lines as $l) {
                $lines[] = $l->get();
            }
            $pop = \count($order) - 1;
            for ($x = \count($annotations) - 1; $x >= 0; --$x) {
                $a = $annotations[$x];
                $rep = [];
                if ($pop >= 0) {
                    $rep[] = $vals[$order[$pop]];
                    --$pop;
                }
                \array_splice($lines, $a->start, $a->end - $a->start + 1, $rep);
            }
            $doc = \implode('', $lines);
        }
        return $doc;
    }
}
