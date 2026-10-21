<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;
use Fmt\Preg;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitNoExpectationAnnotationFixer (priority 10, risky). `@expectedException` / `@expectedExceptionCode` / `@expectedExceptionMessage` /
 * `@expectedExceptionMessageRegExp` annotations of a test method's docblock are removed and become `$this->setExpectedException(Foo::class, 'msg', 7);` (setExpectedExceptionRegExp for the
 * RegExp one, needs target 4.3+) at the start of the body; `use_class_const` picks `\Foo::class` or `'Foo'`. Whole-file FlatTokens port.
 */
final class PhpUnitNoExpectationAnnotation extends PhpUnitClassRule
{
    private bool $regExp;

    public function __construct(string $target, private bool $classConst, private string $lineEnding, private string $indentCfg)
    {
        parent::__construct();
        $this->regExp = $target !== '3.2';
    }

    public function name(): string
    {
        return 'php_unit_no_expectation_annotation';
    }

    public function priority(): int
    {
        return 10;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        for ($i = $end - 1; $i > $start; $i--) {
            if (!self::is($t, $i, \__McTokId::T_FUNCTION) || ($t->i[$i] >= 0 && Analyzer::isLambda($this->src, $t->i[$i]))) {
                continue;
            }
            $m = $t->count();
            $brace = -1;
            for ($j = $i + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && ($t->k[$j] === 59 || $t->k[$j] === 123)) {
                    $brace = $j;
                    break;
                }
            }
            if ($brace < 0 || $t->k[$brace] !== 123) {
                continue;
            }
            $di = $i;
            do {
                $di = $t->nonWhitespace($di, -1);
                $k = $di >= 0 ? $t->k[$di] : -1;
                $code = $di >= 0 && $t->i[$di] !== -1;
                $isPlainComment = $di >= 0 && $k === FlatTokens::COMMENT && !Trivia::isDoc($t->c[$di]);
            } while ($di >= 0 && (($code && ($k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_PROTECTED || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_FINAL
                || $k === \__McTokId::T_ABSTRACT)) || $isPlainComment));
            if ($di < 0 || $t->k[$di] !== FlatTokens::COMMENT || !Trivia::isDoc($t->c[$di])) {
                continue;
            }
            $doc = new DocBlock($t->c[$di]);
            /** @var array<string, string> $annotations */
            $annotations = [];
            foreach ($doc->getAnnotationsOfType(['expectedException', 'expectedExceptionCode', 'expectedExceptionMessage', 'expectedExceptionMessageRegExp']) as $a) {
                $tag = $a->getTag()->getName();
                $annotations[$tag] = $this->extract($a);
                $a->remove();
            }
            if (!isset($annotations['expectedException'])) {
                continue;
            }
            if (!$this->regExp && isset($annotations['expectedExceptionMessageRegExp'])) {
                continue;
            }
            $originalIndent = self::detectIndent($t, $di);
            $params = $this->paramList($annotations);
            $php = '<?php $this->' . (isset($annotations['expectedExceptionMessageRegExp']) ? 'setExpectedExceptionRegExp' : 'setExpectedException') . '(' . \implode(', ', $params) . ');';
            $items = $this->itemsOf($php, $this->lineEnding . $originalIndent . $this->indentCfg);
            $docContent = $doc->getContent();
            if ($docContent === '') {
                $docContent = '/** */';
            }
            $t->c[$di] = $docContent;
            $size = \count($items);
            $t->insertAt($brace + 1, $items);
            $wi = $brace + $size + 1;
            $old = $t->i[$wi] === -1 ? $t->c[$wi] : $t->content($wi);
            $t->k[$wi] = FlatTokens::WS;
            $t->c[$wi] = $this->lineEnding . $old;
            $t->i[$wi] = -1;
            $i = $di;
        }
    }

    /** @return array<int, array{0:int,1:string,2:int}> the tokens of `$code` (a `<?php ` first token becomes the whitespace $first) */
    private function itemsOf(string $code, string $first): array
    {
        $p = Source::parse($code);
        $items = [FlatTokens::ws($first)];
        for ($j = 1; $j < $p->n; $j++) {
            if ($p->gapBefore[$j] !== '') {
                $items[] = FlatTokens::ws($p->gapBefore[$j]);
            }
            $items[] = FlatTokens::code($p->ct[$j] !== 0 ? $p->ct[$j] : $p->kind[$j], $p->text($j));
        }
        return $items;
    }

    private function extract(Annotation $a): string
    {
        $tag = $a->getTag()->getName();
        if (!Preg::match('/@' . $tag . '\s+(.+)$/s', $a->getContent(), $m)) {
            return '';
        }
        $content = Preg::replace('/\*+\/$/', '', $m[1]);
        if (Preg::match('/\R/u', $content)) {
            $content = Preg::replace('/\s*\R+\s*\*\s*/u', ' ', $content);
        }
        return \rtrim($content);
    }

    /**
     * @param array<string, string> $a
     * @return string[]
     */
    private function paramList(array $a): array
    {
        $params = [];
        $class = \ltrim($a['expectedException'], '\\');
        if (\str_contains($class, '*')) {
            $class = \substr($class, 0, (int) \strpos($class, '*'));
        }
        $class = \trim($class);
        $params[] = $this->classConst ? "\\{$class}::class" : "'{$class}'";
        if (isset($a['expectedExceptionMessage'])) {
            $params[] = self::export($a['expectedExceptionMessage']);
        } elseif (isset($a['expectedExceptionMessageRegExp'])) {
            $params[] = self::export($a['expectedExceptionMessageRegExp']);
        } elseif (isset($a['expectedExceptionCode'])) {
            $params[] = 'null';
        }
        if (isset($a['expectedExceptionCode'])) {
            $params[] = $a['expectedExceptionCode'];
        }
        return $params;
    }

    /** var_export of a string */
    private static function export(string $s): string
    {
        if (\str_contains($s, "\0")) {
            return \var_export($s, true);
        }
        return "'" . \str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'";
    }
}
