<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Whitespace\HeredocIndentationFixer (priority -26). The closing marker and the body lines of every heredoc / nowdoc are re-indented to the
 * indentation of the line of the opening `<<<` (+ one indent level for start_plus_one). Whole-file FlatTokens port, last heredoc first.
 */
final class HeredocIndentation extends FlatRule
{
    public function __construct(private bool $plusOne, private string $indentUnit)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'heredoc_indentation';
    }

    public function priority(): int
    {
        return -26;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_START_HEREDOC) {
                return true;
            }
        }
        return false;
    }

    private ?Source $src = null;

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        for ($index = $t->count() - 1; $index >= 0; --$index) {
            if (!self::is($t, $index, \__McTokId::T_END_HEREDOC)) {
                continue;
            }
            $end = $index;
            $index = self::prevOf($t, $index, \__McTokId::T_START_HEREDOC);
            if ($index < 0) {
                return;
            }
            $this->fix($t, $index, $end);
        }
    }

    private function fix(FlatTokens $t, int $start, int $end): void
    {
        $indent = self::detectIndent($t, $start);
        if ($this->plusOne) {
            $indent .= $this->indentUnit;
        }
        if (\str_contains($indent, "\t") && \str_contains($indent, ' ') && $this->src !== null) {
            $this->src->abort = true; // the closing marker would be indented with tabs and spaces: "Invalid indentation - tabs and spaces cannot be mixed", upstream's result does not lint
        }
        $endContent = $t->content($end);
        $currentIndent = '';
        if (Preg::match('/^\h*/', $endContent, $matches)) {
            $currentIndent = $matches[0];
        }
        $currentLen = \strlen($currentIndent);
        self::setCode($t, $end, \__McTokId::T_END_HEREDOC, $indent . \substr($endContent, $currentLen));
        if ($end === $start + 1) {
            return;
        }
        $index = $end - 1;
        for ($last = true; $index > $start; --$index, $last = false) {
            $isBody = $t->i[$index] !== -1 && $t->k[$index] === \__McTokId::T_ENCAPSED_AND_WHITESPACE;
            $isWs = $t->k[$index] === FlatTokens::WS;
            if (!$isBody && !$isWs) {
                continue;
            }
            $content = $t->content($index);
            if ($currentIndent !== '') {
                $content = Preg::replace('/(?<=\v)(?!' . $currentIndent . ')\h+/', '', $content);
            }
            $regexEnd = $last && $currentIndent === '' ? '(?!\v|$)' : '(?!\v)';
            $content = Preg::replace('/(?<=\v)' . $currentIndent . $regexEnd . '/', $indent, $content);
            if ($content === '') {
                $this->emptyToken();
                return;
            }
            if ($isBody) {
                self::setCode($t, $index, \__McTokId::T_ENCAPSED_AND_WHITESPACE, $content);
            } else {
                $t->c[$index] = $content;
            }
        }
        ++$index;
        if (!self::is($t, $index, \__McTokId::T_ENCAPSED_AND_WHITESPACE)) {
            if ($indent === '') {
                $this->emptyToken();
                return;
            }
            $t->insertAt($index, [FlatTokens::code(\__McTokId::T_ENCAPSED_AND_WHITESPACE, $indent)]);
            return;
        }
        $content = $t->content($index);
        if (($content[0] !== "\r" && $content[0] !== "\n") && ($currentIndent === '' || \str_starts_with($content, $currentIndent))) {
            $content = $indent . \substr($content, $currentLen);
        } elseif ($currentIndent !== '') {
            $content = Preg::replace('/^(?!' . $currentIndent . ')\h+/', '', $content);
        }
        if ($content === '') {
            $this->emptyToken();
            return;
        }
        self::setCode($t, $index, \__McTokId::T_ENCAPSED_AND_WHITESPACE, $content);
    }

    /** `new Token([id, ''])` throws "Cannot set empty content for id-based Token": the file stays as it is */
    private function emptyToken(): void
    {
        if ($this->src !== null) {
            $this->src->abort = true;
        }
    }
}
