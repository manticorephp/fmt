<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Source;
use Fmt\Structure;

/**
 * Parity target: PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer (priority -19). The `{` of a function / class / interface / trait /
 * enum whose next non-whitespace token is `}` (no comment inside): the whitespace between them goes and the whitespace before `{`
 * becomes one space (a line break included) unless a comment precedes it. Found in prepare(): comments and bracket kinds never
 * change, so the decision is independent of the other rules.
 */
final class SingleLineEmptyBody extends GapRule
{
    /** @var array<int, bool> */
    private array $body = [];

    public function name(): string
    {
        return 'single_line_empty_body';
    }

    public function priority(): int
    {
        return -19;
    }

    public function needsMask(): int
    {
        return 0;
    }

    public function prepare(Source $s): bool
    {
        $this->body = [];
        $any = false;
        $n = $s->n - 1;
        for ($j = 0; $j < $n; $j++) {
            if ($s->kind[$j] === 123 && $s->kind[$j + 1] === 125 && $s->ct[$j] === 0 && $s->ct[$j + 1] === 0 && !Gap::hasComment($s->gapBefore[$j + 1])) {
                $o = Structure::braceOwner($s, $j);
                if ($o === Structure::OW_CLASS_BODY || $o === Structure::OW_FUNC_BODY || $o === Structure::OW_CLOSURE_BODY) {
                    $this->body[$j] = true;
                    $any = true;
                }
            }
        }
        return $any;
    }

    public function afterKinds(): array
    {
        return [123];
    }

    public function beforeKinds(): array
    {
        return [123];
    }

    public function reads(): int
    {
        return self::F_GAP;
    }

    public function writes(): int
    {
        return self::F_GAP;
    }

    public function fixGap(string $gap, Source $s, int $i): string
    {
        if ($i < $s->n && isset($this->body[$i]) && !Gap::hasComment($gap)) {
            $gap = ' ';
        }
        if ($i > 0 && isset($this->body[$i - 1])) {
            return '';
        }
        return $gap;
    }
}
