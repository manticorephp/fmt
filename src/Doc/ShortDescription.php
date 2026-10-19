<?php

declare(strict_types=1);

namespace Fmt\Doc;

/** Port of PhpCsFixer\DocBlock\ShortDescription. */
final class ShortDescription
{
    /** index of the last line of the summary, null when there is none */
    public static function end(DocBlock $doc): ?int
    {
        $reached = false;
        foreach ($doc->lines as $index => $line) {
            if ($reached && ($line->containsATag() || !$line->containsUsefulContent())) {
                return $index - 1;
            }
            if ($line->containsATag()) {
                return null;
            }
            if ($line->containsUsefulContent()) {
                $reached = true;
            }
        }
        return null;
    }
}
