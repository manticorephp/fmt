<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\Doc\ShortDescription;
use Fmt\DocRule;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocSummaryFixer (priority 0): the summary ends with a full stop (or `!`, `?`, `:` ...). */
final class PhpdocSummary extends DocRule
{
    public function __construct(private string $lineEnding) {}

    public function name(): string
    {
        return 'phpdoc_summary';
    }

    public function priority(): int
    {
        return 0;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if (\strpbrk($doc, "\r\n") === false) {
            return $doc; // one line: a summary needs a line after it
        }
        $d = new DocBlock($doc);
        $end = ShortDescription::end($d);
        if ($end === null) {
            return $doc;
        }
        $line = $d->lines[$end];
        $content = \rtrim($line->get());
        if (self::formatted($content)) {
            return $doc;
        }
        if ($end === 1 || ($d->isMultiLine() && \substr(\rtrim($d->lines[1]->get()), -1) !== ':')) {
            $line->set($content . '.' . $this->lineEnding);
            return $d->getContent();
        }
        return $doc;
    }

    private static function formatted(string $c): bool
    {
        if (\str_contains(\strtolower($c), '{@inheritdoc}')) {
            return true;
        }
        return $c !== \rtrim($c, ".:\xe3\x80\x82!?\xc2\xa1\xc2\xbf\xef\xbc\x81\xef\xbc\x9f");
    }
}
