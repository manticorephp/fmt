<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\Doc\TypeExpression;
use Fmt\DocRule;
use Fmt\Preg;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocVarWithoutNameFixer (priority 0): `@var T $x` -> `@var T` on a property's docblock. */
final class PhpdocVarWithoutName extends DocRule
{
    public function name(): string
    {
        return 'phpdoc_var_without_name';
    }

    public function priority(): int
    {
        return 0;
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function prepare(Source $s): bool
    {
        for ($t = 0; $t < $s->n; $t++) {
            $k = $s->kind[$t];
            if (($k === \__McTokId::T_CLASS && $s->ct[$t] === 0) || $k === \__McTokId::T_TRAIT) {
                return true;
            }
        }
        return false;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (!\str_contains($doc, ' $') || (!\str_contains($doc, '@var') && !\str_contains($doc, '@type'))) {
            return $doc;
        }
        $t = $i;
        if ($t >= $s->n) {
            return $doc;
        }
        if ($s->kind[$t] === \__McTokId::T_STATIC) {
            $t++;
            if ($t >= $s->n) {
                return $doc;
            }
        }
        $kd = $s->kind[$t];
        if ($s->ct[$t] !== 0 || !($kd === \__McTokId::T_PRIVATE || $kd === \__McTokId::T_PROTECTED || $kd === \__McTokId::T_PUBLIC
            || $kd === \__McTokId::T_VAR || $kd === \__McTokId::T_READONLY || $kd === \__McTokId::T_PRIVATE_SET
            || $kd === \__McTokId::T_PROTECTED_SET || $kd === \__McTokId::T_PUBLIC_SET)) {
            return $doc;
        }
        $d = new DocBlock($doc);
        // first-level lines: not inside a `{ ... }` block of the docblock
        $first = [];
        $nested = 0;
        foreach ($d->lines as $index => $line) {
            $c = $line->get();
            if (Preg::test('/\s*\*\s*}$/', $c)) {
                $nested--;
            }
            if ($nested <= 0) {
                $first[$index] = true;
            }
            if (Preg::test('/\s\{$/', $c)) {
                $nested++;
            }
        }
        foreach ($d->getAnnotationsOfType(['type', 'var']) as $a) {
            if (!isset($first[$a->start])) {
                continue;
            }
            $line = $d->lines[$a->start];
            Preg::matchAll('/ \$' . TypeExpression::REGEX_IDENTIFIER . '(?<!\$this)/', $line->get(), $m);
            foreach ($m[0] as $x) {
                $line->set(\str_replace((string) $x, '', $line->get()));
            }
        }
        return $d->getContent();
    }
}
