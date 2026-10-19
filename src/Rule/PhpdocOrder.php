<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Doc\DocBlock;
use Fmt\DocRule;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Phpdoc\PhpdocOrderFixer (priority -2): annotations in the configured order (each tag also
 * covers its phpstan- / psalm- twin). Upstream's moves are kept: an annotation is appended to / prepended to a LINE of the
 * annotation it must follow / precede, and the docblock is re-parsed between the steps.
 */
final class PhpdocOrder extends DocRule
{
    /** @var string[] */
    private array $order = [];

    /** @param string[] $order */
    public function __construct(array $order)
    {
        foreach ($order as $t) {
            $this->order[] = $t;
            if (!\in_array('phpstan-' . $t, $order, true)) {
                $this->order[] = 'phpstan-' . $t;
            }
            if (!\in_array('psalm-' . $t, $order, true)) {
                $this->order[] = 'psalm-' . $t;
            }
        }
    }

    public function name(): string
    {
        return 'phpdoc_order';
    }

    public function priority(): int
    {
        return -2;
    }

    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        // a move needs annotations of two different configured tags
        $seen = 0;
        foreach ($this->order as $t) {
            if (\str_contains($doc, '@' . $t) && ++$seen >= 2) {
                break;
            }
        }
        if ($seen < 2) {
            return $doc;
        }
        $content = $doc;
        $succ = $this->order;
        while (\count($succ) >= 3) {
            $pred = \array_shift($succ);
            $content = self::moveBefore($pred, $succ, $content);
        }
        $preds = $this->order;
        $last = \array_pop($preds);
        return self::moveAfter((string) $last, $preds, $content);
    }

    /** @param string[] $before */
    private static function moveBefore(string $move, array $before, string $content): string
    {
        $d = new DocBlock($content);
        $moved = $d->getAnnotationsOfType([$move]);
        if ($moved === []) {
            return $content;
        }
        $others = $d->getAnnotationsOfType($before);
        if ($others === []) {
            return $content;
        }
        $end = $moved[\count($moved) - 1]->end;
        $line = $d->lines[$end];
        foreach ($others as $o) {
            if ($o->start < $end) {
                $line->set($line->get() . $o->getContent());
                $o->remove();
            }
        }
        return $d->getContent();
    }

    /** @param string[] $after */
    private static function moveAfter(string $move, array $after, string $content): string
    {
        $d = new DocBlock($content);
        $moved = $d->getAnnotationsOfType([$move]);
        if ($moved === []) {
            return $content;
        }
        $others = $d->getAnnotationsOfType($after);
        if ($others === []) {
            return $content;
        }
        $start = $moved[0]->start;
        $line = $d->lines[$start];
        foreach (\array_reverse($others) as $o) {
            if ($o->end > $start) {
                $line->set($o->getContent() . $line->get());
                $o->remove();
            }
        }
        return $d->getContent();
    }
}
