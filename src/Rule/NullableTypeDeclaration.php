<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\EditRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\TypeWalk;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\NullableTypeDeclarationFixer (priority 2), syntax=question_mark. A property, return or
 * parameter type `T|null` / `null|T` becomes `?T` (overrideRange: the type's tokens, trivia between them included, are overwritten by the
 * new ones, the rest cleared; a space is inserted before it unless whitespace or `(` precedes). Only a file with a `|` next to `null` is
 * looked at; it runs upstream's walk on a whole-file FlatTokens, element order and stale indexes included (non-class functions last first,
 * then class elements last first; an insert shifts the items behind it), and is replaced by its rendering.
 */
final class NullableTypeDeclaration extends EditRule
{
    private FlatTokens $t;
    private TypeWalk $w;
    private bool $changed = false;

    public function __construct()
    {
        $this->t = new FlatTokens();
        $this->w = new TypeWalk($this->t);
    }

    public function name(): string
    {
        return 'nullable_type_declaration';
    }

    public function priority(): int
    {
        return 2;
    }

    private static function candidate(Source $s): bool
    {
        $n = $s->n;
        for ($i = 1; $i + 1 < $n; $i++) {
            if ($s->ct[$i] === CT::TYPE_ALTERNATION && (\strtolower($s->text($i - 1)) === 'null' || \strtolower($s->text($i + 1)) === 'null')) {
                return true;
            }
        }
        return false;
    }

    public function fixFile(Source $s): void
    {
        if (!self::candidate($s)) {
            return;
        }
        $t = $this->t;
        $t->load($s, 0, $s->n - 1);
        $this->changed = false;
        /** @var array<int, bool> $isProp flat index => property (else function), in upstream's insertion order */
        $isProp = [];
        foreach (Analyzer::classyElements($s) as $idx => $info) {
            if ($info[0] === Analyzer::E_METHOD || $info[0] === Analyzer::E_PROPERTY) {
                $isProp[$t->pos[$idx]] = $info[0] === Analyzer::E_PROPERTY;
            }
        }
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($s->ct[$i] === 0 && ($k === \__McTokId::T_FN || $k === \__McTokId::T_FUNCTION) && !isset($isProp[$t->pos[$i]])) {
                $isProp[$t->pos[$i]] = false;
            }
        }
        foreach (\array_reverse($isProp, true) as $q => $prop) {
            if ($prop) {
                $pt = $this->w->propertyType($q);
                if ($pt !== null) {
                    $this->normalize($pt);
                }
                continue;
            }
            $rt = $this->w->returnType($q);
            if ($rt !== null) {
                $this->normalize($rt);
            }
            foreach (\array_reverse($this->w->arguments($q), true) as $a) {
                if ($a !== null) {
                    $this->normalize($a);
                }
            }
        }
        if ($this->changed) {
            $t->emit($s, 0, $s->n - 1, false);
        }
    }

    /** @param array{0:string,1:int,2:int} $ta TypeAnalysis raw name + range */
    private function normalize(array $ta): void
    {
        $raw = $ta[0];
        if (\str_starts_with($raw, '?')) {
            $name = \substr($raw, 1);
            $nullable = true;
        } else {
            $name = $raw;
            $nullable = \in_array('null', \array_map('trim', \explode('|', \strtolower($raw))), true);
        }
        // isTypeNormalizable
        if (\strtolower($name) === 'null' || !$nullable || \str_contains($name, '&')) {
            return;
        }
        if (\str_contains($name, '|') && !(\substr_count($name, '|') === 1 && \preg_match('/(?:\|null$|^null\|)/i', $name) === 1)) {
            return;
        }
        $type = $name;
        if (!\str_contains($type, '|') && !\str_contains($type, '&')) {
            $type = ($nullable ? '?' : '') . $type;
        }
        if (\str_starts_with($type, '?')) {
            return; // convertToNullableType: already `?T`
        }
        $rest = (string) \preg_replace('/(?:\|null$|^null\|)/i', '', $type);
        if ('?' . $rest === $type) {
            return;
        }
        // createTypeDeclarationTokens(['?', rest])
        $items = [FlatTokens::code(CT::NULLABLE_TYPE, '?')];
        $low = \strtolower($rest);
        if ($low === 'array') {
            $items[] = FlatTokens::code(CT::ARRAY_TYPEHINT, $rest);
        } elseif ($low === 'callable') {
            $items[] = FlatTokens::code(\__McTokId::T_CALLABLE, $rest);
        } elseif ($low === 'static') {
            $items[] = FlatTokens::code(\__McTokId::T_STATIC, $rest);
        } else {
            foreach (\explode('\\', $rest) as $ni => $v) {
                if ($ni === 0 && $v === '') {
                    continue;
                }
                if ($ni > 0) {
                    $items[] = FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\');
                }
                $items[] = FlatTokens::code(\__McTokId::T_STRING, $v);
            }
        }
        $this->w->overrideRange($ta[1], $ta[2], $items);
        $this->changed = true;
        $t = $this->t;
        $prev = $ta[1] - 1;
        if ($prev >= 0 && !$t->isWs($prev) && !($t->isCode($prev) && $t->k[$prev] === 40)) {
            if ($t->k[$prev] === \__McTokId::T_OPEN_TAG) {
                $t->c[$prev] = \rtrim($t->content($prev)) . ' '; // ensureWhitespaceAtIndex: the open tag absorbs the space
                $t->i[$prev] = FlatTokens::NEW;
            } else {
                $t->insertAt($prev + 1, [FlatTokens::ws(' ')]);
            }
        }
    }
}
