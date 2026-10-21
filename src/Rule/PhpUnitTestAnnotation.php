<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\Doc\DocBlock;
use Fmt\Doc\Line;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\PhpUnitClassRule;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitTestAnnotationFixer (priority 10, risky). style `prefix` (default): `@test` annotations go and the method gets a `test` prefix; style
 * `annotation`: the prefix goes and a `@test` annotation is added (a single-line docblock is split up); `@depends` names follow. Whole-file FlatTokens port over PhpUnitClassRule.
 */
final class PhpUnitTestAnnotation extends PhpUnitClassRule
{
    public function __construct(private bool $annotation, private string $lineEnding)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_test_annotation';
    }

    public function priority(): int
    {
        return 10;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        if ($this->annotation) {
            $this->applyAnnotation($t, $start, $end);
        } else {
            $this->applyPrefix($t, $start, $end);
        }
    }

    private function applyAnnotation(FlatTokens $t, int $start, int $end): void
    {
        for ($i = $end - 1; $i > $start; $i--) {
            if (!$this->isTestMethod($t, $i)) {
                continue;
            }
            $nameIdx = $t->meaningful($i, 1);
            $name = $t->content($nameIdx);
            if (self::hasTestPrefix($name) && !$this->hasProperTestAnnotation($t, $i)) {
                self::setCode($t, $nameIdx, \__McTokId::T_STRING, $this->removeTestPrefix($name));
            }
            $di = $this->docBlockIndex($t, $i);
            if ($di >= 0 && $t->isComment($di) && Trivia::isDoc($t->c[$di])) {
                $lines = $this->updateDocBlock($t, $di);
                $lines = $this->addTestAnnotation($lines, $t, $di);
                $out = '';
                foreach ($lines as $l) {
                    $out .= $l->get();
                }
                $t->c[$di] = $out;
            } else {
                $this->createDocBlock($t, $di, 'test', $this->lineEnding);
            }
        }
    }

    private function applyPrefix(FlatTokens $t, int $start, int $end): void
    {
        for ($i = $end - 1; $i > $start; $i--) {
            if (!$this->isTestMethod($t, $i)) {
                continue;
            }
            $di = $this->docBlockIndex($t, $i);
            if ($di < 0 || !$t->isComment($di) || !Trivia::isDoc($t->c[$di])) {
                continue;
            }
            $lines = $this->updateDocBlock($t, $di);
            $out = '';
            foreach ($lines as $l) {
                $out .= $l->get();
            }
            $t->c[$di] = $out;
            $nameIdx = $t->meaningful($i, 1);
            $name = $t->content($nameIdx);
            if (self::hasTestPrefix($name)) {
                continue;
            }
            self::setCode($t, $nameIdx, \__McTokId::T_STRING, 'test' . \ucfirst($name));
        }
    }

    private function isTestMethod(FlatTokens $t, int $index): bool
    {
        if (!self::is($t, $index, \__McTokId::T_FUNCTION) || ($t->i[$index] >= 0 && Analyzer::isLambda($this->src, $t->i[$index]))) {
            return false;
        }
        $nameIdx = $t->meaningful($index, 1);
        if (self::hasTestPrefix($t->content($nameIdx))) {
            return true;
        }
        $di = $this->docBlockIndex($t, $index);
        return $di >= 0 && $t->isComment($di) && Trivia::isDoc($t->c[$di]) && \str_contains($t->c[$di], '@test');
    }

    private static function hasTestPrefix(string $name): bool
    {
        return \str_starts_with($name, 'test');
    }

    private function hasProperTestAnnotation(FlatTokens $t, int $index): bool
    {
        $di = $this->docBlockIndex($t, $index);
        if ($di < 0) {
            return false;
        }
        return Preg::match('/\*\s+@test\b/', $t->content($di));
    }

    private function removeTestPrefix(string $name): string
    {
        $rest = Preg::replace('/^test(?=[A-Z_])_?/', '', $name);
        if ($rest === '') {
            return $name;
        }
        return \lcfirst($rest);
    }

    /** @return Line[] */
    private function updateDocBlock(FlatTokens $t, int $di): array
    {
        $doc = new DocBlock($t->c[$di]);
        return $this->updateLines($doc->lines, $t, $di);
    }

    /**
     * @param Line[] $lines
     * @return Line[]
     */
    private function updateLines(array $lines, FlatTokens $t, int $di): array
    {
        $needs = $this->annotation;
        $doc = new DocBlock($t->c[$di]);
        foreach ($lines as $i => $line) {
            if ($needs && $line->isTheStart() && $line->isTheEnd()) {
                if (!$this->containsTest($doc)) {
                    $lines = $this->splitUp($lines, $t, $di);
                    return $this->updateLines($lines, $t, $di);
                }
            }
            if (!$needs && \str_contains($line->get(), ' @test') && !\str_contains($line->get(), '@testWith') && !\str_contains($line->get(), '@testdox')) {
                $line = new Line(\str_replace(' @test', '', $line->get()));
                $lines[$i] = $line;
            }
            if (!\str_contains($line->get(), '@depends')) {
                continue;
            }
            $lines[$i] = $this->updateDepends($line);
        }
        return $lines;
    }

    /**
     * @param Line[] $lines
     * @return Line[]
     */
    private function splitUp(array $lines, FlatTokens $t, int $di): array
    {
        $entry = $this->singleLineEntry($lines);
        $le = $this->lineEnding;
        $nx = $t->nonWhitespace($di, 1);
        $indent = $nx < 0 ? '' : self::detectIndent($t, $nx);
        return [new Line('/**' . $le), new Line($indent . ' * ' . $entry . $le), new Line($indent . ' */')];
    }

    /** @param Line[] $lines */
    private function singleLineEntry(array $lines): string
    {
        $line = \trim(\str_replace('*/', '', $lines[0]->get()));
        $chars = \str_split($line);
        $i = \count($chars);
        do {
            $i--;
            $a = $chars[$i] ?? null;
            $b = $chars[$i - 1] ?? null;
            $c = $chars[$i - 2] ?? null;
        } while ($a !== '*' && $b !== '*' && $c !== '/');
        if (($chars[$i] ?? null) === ' ') {
            $i++;
        }
        return \implode('', \array_slice($chars, $i));
    }

    private function updateDepends(Line $line): Line
    {
        $chars = \str_split($line->get());
        $idx = (int) \stripos(\implode('', $chars), '@depends') + 8;
        while (($chars[$idx] ?? null) === ' ') {
            $idx++;
        }
        $name = \implode('', \array_slice($chars, $idx));
        if ($this->annotation) {
            if (self::hasTestPrefix($name)) {
                $name = $this->removeTestPrefix($name);
            }
        } else {
            if (!self::hasTestPrefix($name)) {
                $name = 'test' . \ucfirst($name);
            }
        }
        \array_splice($chars, $idx);
        return new Line(\implode('', $chars) . $name);
    }

    /**
     * @param Line[] $lines
     * @return Line[]
     */
    private function addTestAnnotation(array $lines, FlatTokens $t, int $di): array
    {
        $doc = new DocBlock($t->c[$di]);
        if (!$this->containsTest($doc)) {
            $indent = self::detectIndent($t, $di);
            $le = $this->lineEnding;
            \array_splice($lines, -1, 0, [new Line($indent . ' *' . $le . $indent . ' * @test' . $le)]);
        }
        return $lines;
    }

    private function containsTest(DocBlock $doc): bool
    {
        return \count($doc->getAnnotationsOfType(['test'])) !== 0;
    }
}
