<?php

declare(strict_types=1);

namespace Fmt\Doc;

use Fmt\Preg;

/** Port of PhpCsFixer\DocBlock\Annotation: the lines of one tag. Lines are shared with the DocBlock. */
final class Annotation
{
    public const TAGS_WITH_TYPES = ['extends', 'implements', 'method', 'param', 'param-out', 'phpstan-import-type', 'phpstan-type', 'phpstan-var',
        'property', 'property-read', 'property-write', 'psalm-import-type', 'psalm-type', 'psalm-var', 'return', 'throws', 'type', 'var'];

    private ?Tag $tag = null;
    private ?string $typesContent = null;
    private bool $typesDone = false;
    /** @var string[]|null */
    private ?array $types = null;

    /**
     * @param Line[] $lines
     */
    public function __construct(public array $lines, public int $start, public int $end, public ?NsContext $ns = null)
    {
    }

    public function getTag(): Tag
    {
        if ($this->tag === null) {
            $this->tag = new Tag($this->lines[0]);
        }
        return $this->tag;
    }

    public function getContent(): string
    {
        $out = '';
        foreach ($this->lines as $l) {
            $out .= $l->content;
        }
        return $out;
    }

    public function supportTypes(): bool
    {
        return \in_array($this->getTag()->getName(), self::TAGS_WITH_TYPES, true);
    }

    public function getTypesContent(): ?string
    {
        if (!$this->typesDone) {
            $this->typesDone = true;
            $name = $this->getTag()->getName();
            if (Preg::match('{^(?:\h*\*|/\*\*)[\h*]*@' . $name . '\h+' . TypeExpression::REGEX_TYPES . '(?:(?:[*\h\v]|\&?[\.\$\s]).*)?\r?$}is',
                $this->getContent(), $m)) {
                $this->typesContent = (string) $m['types'];
            }
        }
        return $this->typesContent;
    }

    public function getTypeExpression(): ?TypeExpression
    {
        $c = $this->getTypesContent();
        return $c === null ? null : new TypeExpression($c, $this->ns);
    }

    /** @return string[] */
    public function getTypes(): array
    {
        if ($this->types === null) {
            $t = $this->getTypeExpression();
            $this->types = $t === null ? [] : $t->getTypes();
        }
        return $this->types;
    }

    /** @param string[] $types */
    public function setTypes(array $types): void
    {
        $orig = (string) $this->getTypesContent();
        $glue = $this->getTypeExpression()?->getTypesGlue() ?? '|';
        $new = \implode($glue, $types);
        if ($orig === $new) {
            return;
        }
        $origLines = Preg::split('/([^\n\r]+\R*)/', $orig, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);
        $newLines = Preg::split('/([^\n\r]+\R*)/', $new, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE);
        foreach ($newLines as $index => $line) {
            $pattern = '/' . \preg_quote($origLines[$index], '/') . '/';
            $this->lines[$index]->content = Preg::replace($pattern, \str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $this->lines[$index]->content, 1);
        }
        $this->clearCache();
    }

    /** @return string[] */
    public function getNormalizedTypes(): array
    {
        $t = $this->getTypeExpression();
        if ($t === null) {
            return [];
        }
        $n = $t->mapTypes(static fn (TypeExpression $v): TypeExpression => new TypeExpression(\strtolower($v->toString()), null))
            ->sortTypes(static fn (TypeExpression $a, TypeExpression $b): int => $a->toString() <=> $b->toString());
        return $n->getTypes();
    }

    public function getVariableName(): ?string
    {
        $type = \preg_quote($this->getTypesContent() ?? '', '/');
        $regex = '/@' . $this->getTag()->getName() . '\s+(' . $type . '\s*)?(&\s*)?(\.{3}\s*)?(?<variable>\$' . TypeExpression::REGEX_IDENTIFIER . ')(?:.*|$)/';
        if (Preg::match($regex, $this->getContent(), $m)) {
            return (string) $m['variable'];
        }
        return null;
    }

    public function remove(): void
    {
        foreach ($this->lines as $line) {
            if ($line->isTheStart() && $line->isTheEnd()) {
                $line->remove();
            } elseif ($line->isTheStart()) {
                $line->content = Preg::replace('#(\s*/\*\*).*#', '$1', $line->content);
            } elseif ($line->isTheEnd()) {
                $line->content = Preg::replace('#(\s*)\S.*(\*/.*)#', '$1$2', $line->content);
            } else {
                $line->remove();
            }
        }
        $this->clearCache();
    }

    public function clearCache(): void
    {
        $this->types = null;
        $this->typesContent = null;
        $this->typesDone = false;
    }
}
