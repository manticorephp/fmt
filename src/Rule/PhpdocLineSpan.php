<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Doc\DocBlock;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer (priority 5). The docblock of each class element / class / function / other docblock is made single-line or
 * multi-line as configured per kind (null: left alone). The docblock of an element is the one found walking back over modifiers, types, attributes and plain comments.
 * Whole-file FlatTokens port.
 */
final class PhpdocLineSpan extends FlatRule
{
    /** @param array<string, string> $config kind (class trait_import const property method case function other) => single | multi ('' = null) */
    public function __construct(private array $config, private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'phpdoc_line_span';
    }

    public function priority(): int
    {
        return 5;
    }

    protected function candidate(Source $s): bool
    {
        $multi = false;
        $single = false;
        foreach ($this->config as $mode) {
            $multi = $multi || $mode === 'multi';
            $single = $single || $mode === 'single';
        }
        for ($i = 0; $i <= $s->n; $i++) {
            if (($s->gapFlags[$i] & \Fmt\Gap::G_DOC) === 0) {
                continue;
            }
            foreach (\Fmt\Gap::pieces($s->gapBefore[$i]) as $p) {
                if (!\Fmt\Gap::isDoc($p)) {
                    continue;
                }
                $nl = \str_contains($p, "\n") || \str_contains($p, "\r");
                if (($multi && !$nl) || ($single && $nl)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function partKind(FlatTokens $t, int $q): bool
    {
        if ($t->k[$q] === FlatTokens::COMMENT) {
            return !Trivia::isDoc($t->c[$q]);
        }
        if ($t->i[$q] === -1) {
            return false;
        }
        $k = $t->k[$q];
        return $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_ABSTRACT
            || $k === \__McTokId::T_VAR || $k === \__McTokId::T_STATIC || $k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR
            || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PUBLIC || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PROTECTED || $k === CT::CONSTRUCTOR_PROPERTY_PROMOTION_PRIVATE
            || $k === CT::ARRAY_TYPEHINT || $k === CT::NULLABLE_TYPE || $k === CT::TYPE_ALTERNATION || $k === CT::TYPE_INTERSECTION || $k === \__McTokId::T_ATTRIBUTE
            || $k === \__McTokId::T_READONLY || $k === \__McTokId::T_PRIVATE_SET || $k === \__McTokId::T_PROTECTED_SET || $k === \__McTokId::T_PUBLIC_SET;
    }

    private function docBlockIndex(FlatTokens $t, int $index): int
    {
        do {
            $index = $t->nonWhitespace($index, -1);
            if ($index < 0) {
                return -1;
            }
            if (self::is($t, $index, CT::ATTRIBUTE_CLOSE)) {
                $index = $t->blockStart($index, \__McTokId::T_ATTRIBUTE);
                if ($index < 0) {
                    return -1;
                }
            }
        } while (self::partKind($t, $index));
        return $t->k[$index] === FlatTokens::COMMENT && Trivia::isDoc($t->c[$index]) ? $index : -1;
    }

    private function fixDocBlock(FlatTokens $t, int $docIndex, string $type): void
    {
        $mode = $this->config[$type] ?? '';
        if ($mode === '') {
            return;
        }
        $doc = new DocBlock($t->c[$docIndex]);
        if ($mode === 'multi') {
            $doc->makeMultiLine(self::detectIndent($t, $docIndex), $this->lineEnding);
        } else {
            $doc->makeSingleLine();
        }
        $t->c[$docIndex] = $doc->getContent();
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, bool> $handled */
        $handled = [];
        foreach (Analyzer::classyElements($s) as $idx => [$kind, $cls]) {
            $type = match ($kind) {
                Analyzer::E_METHOD => 'method',
                Analyzer::E_CONST => 'const',
                Analyzer::E_TRAIT_IMPORT => 'trait_import',
                Analyzer::E_CASE => 'case',
                default => 'property',
            };
            $docIndex = $this->docBlockIndex($t, $t->pos[$idx]);
            if ($docIndex < 0) {
                continue;
            }
            $handled[$docIndex] = true;
            $this->fixDocBlock($t, $docIndex, $type);
        }
        if (($this->config['class'] ?? '') === '' && ($this->config['function'] ?? '') === '' && ($this->config['other'] ?? '') === '') {
            return;
        }
        for ($index = $t->count() - 1; $index > 0; --$index) {
            if (isset($handled[$index])) {
                continue;
            }
            if ($t->k[$index] === FlatTokens::COMMENT) {
                if (Trivia::isDoc($t->c[$index])) {
                    $this->fixDocBlock($t, $index, 'other');
                }
                continue;
            }
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_FUNCTION && !Analyzer::isLambda($s, $t->i[$index])) {
                $docIndex = $this->docBlockIndex($t, $index);
                if ($docIndex < 0 || isset($handled[$docIndex])) {
                    continue;
                }
                $handled[$docIndex] = true;
                $this->fixDocBlock($t, $docIndex, 'function');
                continue;
            }
            if ($k !== \__McTokId::T_CLASS && $k !== \__McTokId::T_INTERFACE && $k !== \__McTokId::T_TRAIT && $k !== \__McTokId::T_ENUM) {
                continue;
            }
            $docIndex = $this->docBlockIndex($t, $index);
            if ($docIndex < 0) {
                continue;
            }
            $handled[$docIndex] = true;
            $this->fixDocBlock($t, $docIndex, 'class');
        }
    }
}
