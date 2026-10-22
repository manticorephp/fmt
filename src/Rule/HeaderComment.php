<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\Comment\HeaderCommentFixer (priority -30). The header comment (`/* .. *\/` or `/** .. *\/`, built from the `header` text) is inserted, replaced
 * or removed at the configured location (after the open tag, or after `declare(strict_types=1);`), and blank lines around it follow `separate`. An existing header matching
 * `validator` is kept as it is. Whole-file FlatTokens port; only a monolithic PHP file without `<?=` is a candidate.
 */
final class HeaderComment extends FlatRule
{
    public function __construct(private ?string $header, private ?string $validator, private bool $phpdoc, private bool $afterOpen, private string $separate, private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'header_comment';
    }

    public function priority(): int
    {
        return -30;
    }

    protected function candidate(Source $s): bool
    {
        $tags = 0;
        $html = 0;
        for ($i = 0; $i < $s->n; $i++) {
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_OPEN_TAG_WITH_ECHO) {
                return false;
            }
            if ($k === \__McTokId::T_OPEN_TAG) {
                $tags++;
            }
            if ($k === \__McTokId::T_INLINE_HTML) {
                $html++;
            }
        }
        if ($tags !== 1) {
            return false;
        }
        return $html === 0 || ($html === 1 && $s->kind[0] === \__McTokId::T_INLINE_HTML && Preg::match('/^#!.+$/', $s->text(0)));
    }

    private function headerAsComment(): string
    {
        $comment = ($this->phpdoc ? '/**' : '/*') . $this->lineEnding;
        foreach (\explode("\n", \str_replace("\r", '', (string) $this->header)) as $line) {
            $comment .= \rtrim(' * ' . $line) . $this->lineEnding;
        }
        return $comment . ' */';
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        if ($this->header === null) {
            $s->unsupported = 'header_comment: the required option "header" is missing'; // upstream: every candidate file fails (exit 64)
            return;
        }
        $headerAsComment = $this->headerAsComment();
        $location = $this->afterOpen ? 'after_open' : 'after_declare_strict';
        /** @var int[] $idx insertion index => location, in first-seen order */
        $idx = [];
        /** @var string[] $loc */
        $loc = [];
        foreach (['after_open', 'after_declare_strict'] as $possible) {
            $at = $this->insertionIndex($t, $possible);
            $found = \array_search($at, $idx, true);
            if ($found === false) {
                $idx[] = $at;
                $loc[] = $possible;
            } elseif ($possible === $location) {
                $loc[(int) $found] = $possible;
            }
        }
        if ($this->validator !== null) {
            foreach ($loc as $possible) {
                $new = $this->insertionIndex($t, $possible);
                $cur = $this->currentIndex($t, $headerAsComment, $new - 1);
                if ($cur < 0) {
                    continue;
                }
                if ($this->fulfills($t->c[$cur])) {
                    $headerAsComment = $t->c[$cur];
                }
            }
        }
        foreach ($loc as $possible) {
            $new = $this->insertionIndex($t, $possible);
            $cur = $this->currentIndex($t, $headerAsComment, $new - 1);
            if ($cur < 0) {
                if ($this->header === '' || $possible !== $location) {
                    continue;
                }
                $this->insertHeader($t, $headerAsComment, $new);
                continue;
            }
            $currentComment = $t->c[$cur];
            $same = $headerAsComment === $currentComment;
            $expected = $possible === $location;
            if (!$same || !$expected) {
                if ($expected xor $same) {
                    $this->removeHeader($t, $cur);
                }
                if ($this->header === '') {
                    continue;
                }
                if ($possible === $location) {
                    $this->insertHeader($t, $headerAsComment, $new);
                }
                continue;
            }
            $this->fixWhitespaceAround($t, $cur);
        }
    }

    private function fulfills(string $comment): bool
    {
        $lines = [];
        $all = \explode("\n", $comment);
        for ($x = 1; $x < \count($all) - 1; $x++) {
            $line = $all[$x];
            $lines[] = $line === ' *' ? '' : (\str_starts_with($line, ' * ') ? \substr($line, 3) : $line);
        }
        return Preg::match((string) $this->validator, \implode("\n", $lines));
    }

    private function currentIndex(FlatTokens $t, string $headerAsComment, int $newIndex): int
    {
        $index = $t->nonWhitespace($newIndex, 1);
        if ($index < 0 || $t->k[$index] !== FlatTokens::COMMENT) {
            return -1;
        }
        $next = $index + 1;
        if ($next >= $t->count() || $this->separate === 'top' || $this->separate === 'none' || !Trivia::isDoc($t->c[$index])) {
            return $index;
        }
        if ($t->k[$next] === FlatTokens::WS) {
            if (!Preg::match('/^\h*\R\h*$/D', $t->c[$next])) {
                return $index;
            }
            ++$next;
        }
        if ($next >= $t->count()) {
            return $index;
        }
        $k = $t->k[$next];
        $classy = $t->i[$next] !== -1 && ($k === \__McTokId::T_CLASS || $k === \__McTokId::T_INTERFACE || $k === \__McTokId::T_TRAIT || $k === \__McTokId::T_ENUM);
        if (!$classy && !($t->i[$next] !== -1 && $k === \__McTokId::T_FUNCTION)) {
            return $index;
        }
        if ($headerAsComment === $t->c[$index] || ($this->validator !== null && $this->fulfills($t->c[$index]))) {
            return $index;
        }
        return -1;
    }

    private function insertionIndex(FlatTokens $t, string $location): int
    {
        $open = $t->k[0] === \__McTokId::T_INLINE_HTML ? 1 : 0;
        if ($location === 'after_open') {
            return $open + 1;
        }
        $index = $t->meaningful($open, 1);
        if ($index < 0 || !self::is($t, $index, \__McTokId::T_DECLARE)) {
            return $open + 1;
        }
        $next = $t->meaningful($index, 1);
        if ($next < 0 || !self::is($t, $next, 40)) {
            return $open + 1;
        }
        $next = $t->meaningful($next, 1);
        if ($next < 0 || !self::is($t, $next, \__McTokId::T_STRING) || \strtolower($t->content($next)) !== 'strict_types') {
            return $open + 1;
        }
        $next = $t->meaningful($next, 1);
        if ($next < 0 || !self::is($t, $next, 61)) {
            return $open + 1;
        }
        $next = $t->meaningful($next, 1);
        if ($next < 0 || !self::is($t, $next, \__McTokId::T_LNUMBER)) {
            return $open + 1;
        }
        $next = $t->meaningful($next, 1);
        if ($next < 0 || !self::is($t, $next, 41)) {
            return $open + 1;
        }
        $next = $t->meaningful($next, 1);
        if ($next < 0 || !self::is($t, $next, 59)) {
            return $open + 1;
        }
        return $next + 1;
    }

    private function insertHeader(FlatTokens $t, string $comment, int $index): void
    {
        $t->insertAt($index, [[FlatTokens::COMMENT, $comment, -1]]);
        $this->fixWhitespaceAround($t, $index);
    }

    private function lineBreaks(FlatTokens $t, int $index, int $dir): int
    {
        $ws = '';
        $m = $t->count();
        for ($index += $dir; $index >= 0 && $index < $m; $index += $dir) {
            if ($t->k[$index] === FlatTokens::WS) {
                $ws .= $t->c[$index];
                continue;
            }
            if ($dir === -1 && self::is($t, $index, \__McTokId::T_OPEN_TAG)) {
                $ws .= $t->content($index);
            }
            if ($t->content($index) !== '') {
                break;
            }
        }
        return \substr_count($ws, "\n");
    }

    private function fixWhitespaceAround(FlatTokens $t, int $header): void
    {
        $both = $this->separate === 'both';
        $expected = (($both || $this->separate === 'bottom') && $t->meaningful($header, 1) >= 0) ? 2 : 1;
        if ($header === $t->count() - 1) {
            $t->insertAt($header + 1, [FlatTokens::ws(\str_repeat($this->lineEnding, $expected))]);
        } else {
            $count = $this->lineBreaks($t, $header, 1);
            if ($count < $expected) {
                $missing = \str_repeat($this->lineEnding, $expected - $count);
                if ($t->k[$header + 1] === FlatTokens::WS) {
                    $t->c[$header + 1] = $missing . $t->c[$header + 1];
                } else {
                    $t->insertAt($header + 1, [FlatTokens::ws($missing)]);
                }
            } elseif ($count > $expected && $t->k[$header + 1] === FlatTokens::WS) {
                $remove = $count - $expected;
                $t->c[$header + 1] = Preg::replace('/^\R{' . $remove . '}/', '', $t->c[$header + 1]);
            }
        }
        $expected = ($both || $this->separate === 'top') ? 2 : 1;
        $prev = $t->nonWhitespace($header, -1);
        $regex = '/\h$/';
        if ($prev >= 0 && self::is($t, $prev, \__McTokId::T_OPEN_TAG) && Preg::match($regex, $t->content($prev))) {
            self::setCode($t, $prev, \__McTokId::T_OPEN_TAG, Preg::replace($regex, $this->lineEnding, $t->content($prev)));
        }
        $count = $this->lineBreaks($t, $header, -1);
        if ($count < $expected) {
            $t->insertAt($header, [FlatTokens::ws(\str_repeat($this->lineEnding, $expected - $count))]);
        }
    }

    private function removeHeader(FlatTokens $t, int $index): void
    {
        $prev = $index - 1;
        $removed = false;
        if ($prev >= 0 && $t->k[$prev] === FlatTokens::WS) {
            $content = $t->c[$prev];
            if (Preg::match('/\R/', $content)) {
                $removed = true;
            }
            $content = Preg::replace('/\R?\h*$/', '', $content);
            self::ensureWs($t, $prev, 0, $content);
        }
        $next = $index + 1;
        if (!$removed && $next < $t->count() && $t->k[$next] === FlatTokens::WS) {
            self::ensureWs($t, $next, 0, Preg::replace('/^\R/', '', $t->c[$next]));
        }
        self::clearMerge($t, $index);
    }
}
