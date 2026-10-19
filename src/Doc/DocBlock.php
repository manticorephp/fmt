<?php

declare(strict_types=1);

namespace Fmt\Doc;

use Fmt\Preg;

/** Port of PhpCsFixer\DocBlock\DocBlock: a docblock as lines; annotations are built on demand and share the Line objects. */
final class DocBlock
{
    /** @var Line[] */
    public array $lines = [];
    /** @var Annotation[]|null */
    private ?array $annotations = null;

    public function __construct(string $content, public ?NsContext $ns = null)
    {
        foreach (Preg::split('/([^\n\r]+\R*)/', $content, -1, \PREG_SPLIT_NO_EMPTY | \PREG_SPLIT_DELIM_CAPTURE) as $l) {
            $this->lines[] = new Line($l);
        }
    }

    public function getContent(): string
    {
        $out = '';
        foreach ($this->lines as $l) {
            $out .= $l->content;
        }
        return $out;
    }

    public function getLine(int $pos): ?Line
    {
        return $this->lines[$pos] ?? null;
    }

    /** @return Annotation[] */
    public function getAnnotations(): array
    {
        if ($this->annotations !== null) {
            return $this->annotations;
        }
        $out = [];
        $total = \count($this->lines);
        for ($index = 0; $index < $total; $index++) {
            if ($this->lines[$index]->containsATag()) {
                $len = $this->findAnnotationLength($index);
                $out[] = new Annotation(\array_slice($this->lines, $index, $len), $index, $index + $len - 1, $this->ns);
                $index += $len - 1;
            }
        }
        $this->annotations = $out;
        return $out;
    }

    /** drops the annotation cache (after lines were inserted / removed) */
    public function resetAnnotations(): void
    {
        $this->annotations = null;
    }

    public function getAnnotation(int $pos): ?Annotation
    {
        return $this->getAnnotations()[$pos] ?? null;
    }

    /**
     * @param string[] $types
     * @return Annotation[]
     */
    public function getAnnotationsOfType(array $types): array
    {
        $out = [];
        foreach ($this->getAnnotations() as $a) {
            if (\in_array($a->getTag()->getName(), $types, true)) {
                $out[] = $a;
            }
        }
        return $out;
    }

    public function isMultiLine(): bool
    {
        return \count($this->lines) !== 1;
    }

    public function makeMultiLine(string $indent, string $lineEnd): void
    {
        if ($this->isMultiLine()) {
            return;
        }
        $c = self::singleLineEntry($this->lines[0]);
        if ($c === '') {
            $this->lines = [new Line('/**' . $lineEnd), new Line($indent . ' *' . $lineEnd), new Line($indent . ' */')];
            return;
        }
        $this->lines = [new Line('/**' . $lineEnd), new Line($indent . ' * ' . $c . $lineEnd), new Line($indent . ' */')];
    }

    public function makeSingleLine(): void
    {
        if (!$this->isMultiLine()) {
            return;
        }
        $useful = [];
        foreach ($this->lines as $l) {
            if ($l->containsUsefulContent()) {
                $useful[] = $l;
            }
        }
        if (\count($useful) > 1) {
            return;
        }
        $c = \count($useful) > 0 ? self::singleLineEntry($useful[0]) : '';
        $this->lines = [new Line('/** ' . $c . ' */')];
    }

    private function findAnnotationLength(int $start): int
    {
        $index = $start;
        while (($line = $this->getLine(++$index)) !== null) {
            if ($line->containsATag()) {
                break;
            }
            if (!$line->containsUsefulContent()) {
                $next = $this->getLine($index + 1);
                if ($next === null || !$next->containsUsefulContent() || $next->containsATag()) {
                    break;
                }
            }
        }
        return $index - $start;
    }

    private static function singleLineEntry(Line $line): string
    {
        $s = $line->content;
        if ($s === '') {
            return $s;
        }
        $s = \trim(\str_replace('*/', '', $s));
        if (\str_starts_with($s, '/**')) {
            $s = \substr($s, 3);
        } elseif (\str_starts_with($s, '*')) {
            $s = \substr($s, 1);
        }
        return \trim($s);
    }
}
