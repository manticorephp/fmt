<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\Doc\TypeExpression;
use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocParamOrderFixer (priority 0). The `@param` annotations of every docblock are reordered to the order of the variables of the
 * first parameter list that follows the docblock in the file (upstream does not check that the docblock belongs to that function); annotations matching no variable go last,
 * other annotations between the first and last `@param` follow the params in their original order. A `{`-block annotation travels as a whole.
 */
final class PhpdocParamOrder extends DocRule
{
    public function name(): string
    {
        return 'phpdoc_param_order';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @param string[] $p */
    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, '@param')) {
            return $doc;
        }
        $n = $s->n;
        $fn = false;
        for ($j = $i; $j < $n; $j++) {
            if ($s->ct[$j] === 0 && ($s->kind[$j] === \__McTokId::T_FUNCTION || $s->kind[$j] === \__McTokId::T_FN)) {
                $fn = true;
                break;
            }
        }
        if (!$fn) {
            return $doc;
        }
        $open = -1;
        for ($j = $i; $j < $n; $j++) {
            if ($s->ct[$j] === 0 && $s->kind[$j] === 40) {
                $open = $j;
                break;
            }
        }
        if ($open < 0 || $s->pair[$open] < 0) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $params = $d->getAnnotationsOfType(['param']);
        if ($params === []) {
            return $doc;
        }
        $close = $s->pair[$open];
        /** @var string[] $names */
        $names = [];
        for ($j = $open + 1; $j < $close; $j++) {
            if ($s->kind[$j] === \__McTokId::T_VARIABLE) {
                $names[] = $s->text($j);
            }
        }
        /** @var array<int, string> $valid annotation position => content, in the order found */
        $valid = [];
        /** @var int[] $validOrder */
        $validOrder = [];
        foreach ($names as $name) {
            foreach ($this->findParam($params, $name) as $pos => $annotation) {
                if (!isset($valid[$pos])) {
                    $validOrder[] = $pos;
                }
                $valid[$pos] = $annotation->getContent();
            }
        }
        /** @var string[] $ordered */
        $ordered = [];
        foreach ($validOrder as $pos) {
            $ordered[] = $valid[$pos];
        }
        foreach ($params as $pos => $annotation) {
            if (!isset($valid[$pos])) {
                $ordered[] = $annotation->getContent();
            }
        }
        $first = $params[0]->start;
        $last = $params[\count($params) - 1]->end;
        foreach ($d->getAnnotations() as $annotation) {
            if ($annotation->start < $first || $annotation->end > $last) {
                continue;
            }
            if ($annotation->getTag()->getName() !== 'param') {
                $ordered[] = $annotation->getContent();
            }
        }
        $at = 0;
        foreach ($d->getAnnotations() as $annotation) {
            if ($annotation->start < $first || $annotation->end > $last) {
                continue;
            }
            $annotation->remove();
            $line = $d->getLine($annotation->start);
            if ($line !== null && $at < \count($ordered)) {
                $line->set($ordered[$at]);
            }
            $at++;
        }
        $new = $d->getContent();
        $close = \strpos($new, '*/');
        if ($close !== false && $close !== \strlen($new) - 2) {
            // the last annotation ends on the closing line: its text (with `*/`) lands on the first line while that line keeps its own `*/`; upstream's result does not lint
            $s->abort = true;
            return $doc;
        }
        return $new;
    }

    /**
     * @param Annotation[] $params
     * @return array<int, Annotation> position => annotation
     */
    private function findParam(array $params, string $identifier): array
    {
        $level = 0;
        $match = false;
        /** @var array<int, Annotation> $blocks */
        $blocks = [];
        $regex = '/\*\h*@param\h*(?:|' . TypeExpression::REGEX_TYPES . '\h*)&?(?=\$\b)' . \preg_quote($identifier) . '\b/';
        foreach ($params as $i => $param) {
            $content = $param->getContent();
            $blockStart = Preg::match('/\s*{\s*/', $content);
            $ends = Preg::matchAll('/}[\*\s\n]*/', $content);
            if ($level === 0 && Preg::match($regex, $content)) {
                if ($blockStart) {
                    $match = true;
                } else {
                    return [$i => $param];
                }
            }
            if ($blockStart) {
                ++$level;
            }
            if ($ends !== 0) {
                $level -= $ends;
            }
            if ($match) {
                $blocks[$i] = $param;
                if ($level === 0) {
                    return $blocks;
                }
            }
        }
        return [];
    }
}
