<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\Doc\Line;
use Fmt\Doc\ShortDescription;
use Fmt\DocRule;
use Fmt\Source;

/** Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocTrimConsecutiveBlankLineSeparationFixer (priority -41): one blank line at most between sections. */
final class PhpdocTrimConsecutiveBlankLineSeparation extends DocRule
{
    public function name(): string
    {
        return 'phpdoc_trim_consecutive_blank_line_separation';
    }

    public function priority(): int
    {
        return -41;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        // a removal needs two consecutive lines without useful content, so at least three line breaks
        if (\substr_count($doc, "\n") + \substr_count($doc, "\r") < 3) {
            return $doc;
        }
        $d = new DocBlock($doc);
        $summaryEnd = ShortDescription::end($d);
        if ($summaryEnd !== null) {
            $after = self::findNonBlankLine($d, $summaryEnd);
            if ($after !== null) {
                self::removeBetween($d, $summaryEnd, $after);
            }
            $annotationStart = self::firstAnnotationOrEnd($d);
            $descriptionEnd = self::lastUseful($d, $annotationStart);
            if ($descriptionEnd !== null && $summaryEnd !== $descriptionEnd && $annotationStart !== \count($d->lines) - 1) {
                self::removeBetween($d, $descriptionEnd, $annotationStart);
            }
        }
        $annotationStart = self::firstAnnotationOrEnd($d);
        $last = self::lastUseful($d, \count($d->lines) - 1);
        if ($last !== null && $annotationStart !== $last) {
            self::removeBetween($d, $annotationStart, $last);
        }
        return $d->getContent();
    }

    private static function removeBetween(DocBlock $d, int $from, int $to): void
    {
        for ($index = $from + 1; $index < $to; $index++) {
            $line = $d->lines[$index];
            $next = $d->lines[$index + 1];
            if (!$line->isTheEnd() && !$line->containsUsefulContent() && !$next->isTheEnd() && !$next->containsUsefulContent()) {
                $line->remove();
            }
        }
    }

    private static function findNonBlankLine(DocBlock $d, int $after): ?int
    {
        foreach ($d->lines as $index => $line) {
            if ($index <= $after) {
                continue;
            }
            if ($line->containsATag() || $line->containsUsefulContent() || $line->isTheEnd()) {
                return $index;
            }
        }
        return null;
    }

    private static function firstAnnotationOrEnd(DocBlock $d): int
    {
        $index = 0;
        foreach ($d->lines as $index => $line) {
            if ($line->containsATag()) {
                return $index;
            }
        }
        return $index;
    }

    private static function lastUseful(DocBlock $d, int $from): ?int
    {
        for ($index = $from - 1; $index >= 0; $index--) {
            if ($d->lines[$index]->containsUsefulContent()) {
                return $index;
            }
        }
        return null;
    }
}
