<?php

declare(strict_types=1);

namespace Fmt\Doc;

use Fmt\Preg;

/** Port of PhpCsFixer\DocBlock\Tag: the name of the tag on an annotation's first line ('other' when none). */
final class Tag
{
    public const PSR_STANDARD_TAGS = ['api', 'author', 'category', 'copyright', 'deprecated', 'example', 'global', 'internal', 'license', 'link',
        'method', 'package', 'param', 'property', 'property-read', 'property-write', 'return', 'see', 'since', 'subpackage', 'throws', 'todo',
        'uses', 'var', 'version'];

    private ?string $name = null;

    public function __construct(public Line $line)
    {
    }

    public function getName(): string
    {
        if ($this->name === null) {
            if (Preg::match('/@[a-zA-Z0-9_-]+(?=\s|$)/', $this->line->get(), $m)) {
                $this->name = \ltrim((string) $m[0], '@');
            } else {
                $this->name = 'other';
            }
        }
        return $this->name;
    }

    public function setName(string $name): void
    {
        $current = $this->getName();
        $this->line->set(Preg::replace('/@' . $current . '/', '@' . $name, $this->line->get(), 1));
        $this->name = $name;
    }

    public function valid(): bool
    {
        return \in_array($this->getName(), self::PSR_STANDARD_TAGS, true);
    }
}
