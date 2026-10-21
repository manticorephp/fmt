<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Doc\DocBlock;
use Fmt\Doc\Line;
use Fmt\DocRule;
use Fmt\Gap;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocAddMissingParamAnnotationFixer (priority 10). A multi-line docblock right before a function (modifiers allowed) gets a
 * `@param <type|mixed> $name` line for every parameter it does not document (only untyped ones with only_untyped), after the last `@param` or before the
 * closing line. A docblock mentioning inheritdoc is skipped. Upstream's loop jumps to the function's token after a docblock that passed those checks, so
 * a second docblock in front of the same token is never visited.
 */
final class PhpdocAddMissingParamAnnotation extends DocRule
{
    private bool $consumed = false;

    public function __construct(private bool $onlyUntyped, private string $lineEnding)
    {
    }

    public function name(): string
    {
        return 'phpdoc_add_missing_param_annotation';
    }

    public function priority(): int
    {
        return 10;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        $this->consumed = false;
        return parent::fixGap($gap, $s, $i);
    }

    /** @param string[] $p */
    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if ($this->consumed) {
            return $doc;
        }
        if (\stripos($doc, 'inheritdoc') !== false) {
            return $doc;
        }
        if (!\str_contains($doc, "\n")) {
            return $doc;
        }
        if ($i >= $s->n) {
            return $doc;
        }
        $this->consumed = true;
        $t = $i;
        while ($t < $s->n && $s->ct[$t] === 0 && \in_array($s->kind[$t], [\__McTokId::T_ABSTRACT, \__McTokId::T_FINAL, \__McTokId::T_PRIVATE, \__McTokId::T_PROTECTED,
            \__McTokId::T_PUBLIC, \__McTokId::T_STATIC], true)) {
            $t++;
        }
        if ($t >= $s->n || $s->kind[$t] !== \__McTokId::T_FUNCTION || $s->ct[$t] !== 0) {
            return $doc;
        }
        $open = $t + 1;
        while ($open < $s->n && !($s->kind[$open] === 40 && $s->ct[$open] === 0)) {
            $open++;
        }
        if ($open >= $s->n || $s->pair[$open] < 0) {
            return $doc;
        }
        $close = $s->pair[$open];
        /** @var array<string, array{0:string,1:string,2:string}> $arguments name => [default, name, type] */
        $arguments = [];
        foreach ($this->argumentRanges($s, $open, $close) as [$a, $b]) {
            $info = $this->argumentInfo($s, $a, $b);
            if (!$this->onlyUntyped || $info[2] === '') {
                $arguments[$info[1]] = $info;
            }
        }
        if ($arguments === []) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $lastParamLine = null;
        foreach ($d->getAnnotationsOfType(['param']) as $a) {
            if (Preg::match('/^[^$]+(\$\w+).*$/s', $a->getContent(), $m)) {
                unset($arguments[(string) $m[1]]);
            }
            $lastParamLine = $lastParamLine === null ? $a->end : \max($lastParamLine, $a->end);
        }
        if ($arguments === []) {
            return $doc;
        }
        $lines = $d->lines;
        $count = \count($lines);
        Preg::match('/^(\s*).*$/', $lines[$count - 1]->get(), $m);
        $indent = (string) ($m[1] ?? '');
        $new = [];
        foreach ($arguments as $arg) {
            $type = $arg[2] !== '' ? $arg[2] : 'mixed';
            if (!\str_starts_with($type, '?') && \strtolower($arg[0]) === 'null') {
                $type = 'null|' . $type;
            }
            $new[] = new Line($indent . '* @param ' . $type . ' ' . $arg[1] . $this->lineEnding, false);
        }
        $at = $lastParamLine !== null && $lastParamLine > 0 ? $lastParamLine + 1 : $count - 1;
        if ($at >= $count) {
            // the last `@param` ends on the closing line: the new lines land behind `*/`, upstream's result does not lint and the file is left as it is
            $s->abort = true;
            return $doc;
        }
        \array_splice($lines, $at, 0, $new);
        $out = '';
        foreach ($lines as $l) {
            $out .= $l->get();
        }
        return $out;
    }

    /**
     * ArgumentsAnalyzer::getArguments over the code tokens strictly between the parentheses: [first, last] of each argument ([] for an empty list).
     * @return array<int, array{0:int,1:int}>
     */
    private function argumentRanges(Source $s, int $open, int $close): array
    {
        if ($open + 1 >= $close) {
            return [];
        }
        $out = [];
        $start = $open + 1;
        $j = $open + 1;
        for (; $j < $close; ++$j) {
            $pj = $s->pair[$j];
            if ($pj > $j) {
                $j = $pj;
                continue;
            }
            if ($s->kind[$j] === 44 && $s->ct[$j] === 0) {
                if ($j + 1 === $close) {
                    break;
                }
                $out[] = [$start, $j - 1];
                $start = $j + 1;
            }
        }
        $out[] = [$start, $j - 1];
        return $out;
    }

    /** @return array{0:string,1:string,2:string} default, name, type */
    private function argumentInfo(Source $s, int $start, int $end): array
    {
        $default = '';
        $name = '';
        $type = '';
        $sawName = false;
        for ($i = $start; $i <= $end; ++$i) {
            $k = $s->kind[$i];
            $c = $s->ct[$i];
            if ($k === \__McTokId::T_ATTRIBUTE && $c === 0) {
                $i = $s->pair[$i];
                continue;
            }
            if ($c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $c === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC
                || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET) {
                continue;
            }
            if ($k === \__McTokId::T_VARIABLE && $c === 0) {
                $sawName = true;
                $name = $s->text($i);
                continue;
            }
            if ($k === 61 && $c === 0) {
                continue;
            }
            if ($sawName) {
                $default .= $s->text($i);
            } elseif (!($k === 38 || $k === \__McTokId::T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG || $k === \__McTokId::T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG)) {
                if ($k === \__McTokId::T_ELLIPSIS) {
                    $type = $type === '' ? 'array' : $type . '[]';
                } else {
                    $type .= $s->text($i);
                }
            }
        }
        return [$default, $name, $type];
    }
}
