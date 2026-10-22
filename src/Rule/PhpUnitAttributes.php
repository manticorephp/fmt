<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\Doc\Annotation;
use Fmt\Doc\DocBlock;
use Fmt\FlatAttributes;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\PhpUnitClassRule;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitAttributesFixer (priority 8). The PHPUnit annotations of a test class and of the docblocks inside it (`@test`, `@dataProvider`,
 * `@depends`, `@covers`, `@group`, `@requires`, `@testWith`, ...) become `#[\PHPUnit\Framework\Attributes\...]` attributes inserted right behind the docblock (an attribute
 * of that class already there is not added twice); the annotation is removed unless keep_annotations. Whole-file FlatTokens port; the walk is last docblock first.
 */
final class PhpUnitAttributes extends PhpUnitClassRule
{
    public function __construct(private bool $keep)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'php_unit_attributes';
    }

    public function priority(): int
    {
        return 8;
    }

    private static function strategy(string $name): string
    {
        if (\in_array($name, ['after', 'afterClass', 'before', 'beforeClass', 'coversNothing', 'doesNotPerformAssertions', 'large', 'medium', 'runInSeparateProcess',
            'runTestsInSeparateProcesses', 'small', 'test', 'preCondition', 'postCondition'], true)) {
            return 'none';
        }
        if (\in_array($name, ['group', 'testDox', 'ticket'], true)) {
            return 'single';
        }
        if (\in_array($name, ['backupGlobals', 'backupStaticAttributes', 'preserveGlobalState'], true)) {
            return 'enabled';
        }
        if (\in_array($name, ['covers', 'dataProvider', 'depends', 'requires', 'testWith', 'uses'], true)) {
            return $name;
        }
        return '';
    }

    private static function shouldBeFixed(string $name, string $scope): bool
    {
        if ($scope === 'method' && \in_array($name, ['covers', 'large', 'medium', 'runTestsInSeparateProcesses', 'small', 'uses'], true)) {
            return false;
        }
        if ($scope === 'class' && \in_array($name, ['after', 'afterClass', 'before', 'beforeClass', 'dataProvider', 'depends', 'postCondition', 'preCondition',
            'runInSeparateProcess', 'test', 'testWith'], true)) {
            return false;
        }
        return true;
    }

    protected function applyClass(FlatTokens $t, int $startIndex, int $endIndex): void
    {
        $classIndex = self::prevOf($t, $startIndex, \__McTokId::T_CLASS);
        if ($classIndex < 0) {
            return;
        }
        $docBlockIndex = $this->docBlockIndex($t, $classIndex);
        if ($docBlockIndex >= 0 && $t->k[$docBlockIndex] === FlatTokens::COMMENT && Trivia::isDoc($t->c[$docBlockIndex])) {
            $startIndex = $docBlockIndex;
        }
        for ($index = $endIndex; $index >= $startIndex; --$index) {
            if ($t->k[$index] !== FlatTokens::COMMENT || !Trivia::isDoc($t->c[$index])) {
                continue;
            }
            $target = $this->targetIndex($t, $index);
            $scope = $target >= 0 && self::is($t, $target, \__McTokId::T_CLASS) ? 'class' : 'method';
            $doc = new DocBlock($t->c[$index]);
            /** @var array<string, bool> $present */
            $present = [];
            $annotations = $doc->getAnnotations();
            for ($x = \count($annotations) - 1; $x >= 0; --$x) {
                $annotation = $annotations[$x];
                $name = $annotation->getTag()->getName();
                $how = self::strategy($name);
                if ($how === '' || !self::shouldBeFixed($name, $scope)) {
                    continue;
                }
                $items = $this->items($t, $index, $annotation, $how);
                if (!isset($present[$name])) {
                    $present[$name] = $this->alreadyPresent($t, $index, $items);
                }
                if ($items === []) {
                    continue;
                }
                if (!$present[$name]) {
                    $t->insertSlices([$index + 1 => $items]);
                }
                if (!$this->keep) {
                    $annotation->remove();
                }
            }
            if ($doc->getContent() === '') {
                self::clearMerge($t, $index);
            } else {
                $t->c[$index] = $doc->getContent();
            }
        }
    }

    /** Tokens::getTokenNotOfKindSibling($index, 1, [abstract, comment, final, function, private, protected, public, static, whitespace]) */
    private function targetIndex(FlatTokens $t, int $index): int
    {
        $m = $t->count();
        for ($j = $index + 1; $j < $m; $j++) {
            if ($t->k[$j] === FlatTokens::WS) {
                continue;
            }
            if ($t->k[$j] === FlatTokens::COMMENT) {
                if (!Trivia::isDoc($t->c[$j])) {
                    continue;
                }
                return $j;
            }
            $k = $t->k[$j];
            if ($k === \__McTokId::T_ABSTRACT || $k === \__McTokId::T_FINAL || $k === \__McTokId::T_FUNCTION || $k === \__McTokId::T_PRIVATE || $k === \__McTokId::T_PROTECTED
                || $k === \__McTokId::T_PUBLIC || $k === \__McTokId::T_STATIC) {
                continue;
            }
            return $j;
        }
        return -1;
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}> $items
     */
    private function alreadyPresent(FlatTokens $t, int $index, array $items): bool
    {
        $attr = $t->meaningful($index, 1);
        if ($attr < 0 || !self::is($t, $attr, \__McTokId::T_ATTRIBUTE)) {
            return false;
        }
        $inserted = '';
        for ($x = 3; $x < \count($items); $x++) {
            if ($items[$x][0] === 40 || $items[$x][0] === CT::ATTRIBUTE_CLOSE) {
                break;
            }
            $inserted .= $items[$x][1];
        }
        foreach (FlatAttributes::collect($t, $attr) as $group) {
            foreach ($group->attributes as $attribute) {
                $class = \ltrim(FlatAttributes::fullyQualifiedName($this->src, $t, $attribute->name, $attribute->start), '\\');
                if ($inserted === $class) {
                    return true;
                }
                if ($inserted === 'PHPUnit\Framework\Attributes\TestWithJson' && $class === 'PHPUnit\Framework\Attributes\TestWith') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return array<int, array{0:int,1:string,2:int}>
     */
    private function items(FlatTokens $t, int $index, Annotation $annotation, string $how): array
    {
        $name = $annotation->getTag()->getName();
        $attrName = $name === 'backupStaticAttributes' ? 'BackupStaticProperties' : \ucfirst($name);
        if ($how === 'none') {
            return $this->attribute($t, $index, $attrName, []);
        }
        if ($how === 'single') {
            if (!Preg::match(\sprintf('/@%s\s+(.*\S)(?:\R|\s*\*+\/$)/', $name), $annotation->getContent(), $m) || !isset($m[1])) {
                return [];
            }
            return $this->attribute($t, $index, $attrName, [self::str((string) $m[1])]);
        }
        $m = $this->matches($annotation);
        if ($how === 'enabled') {
            if (!isset($m[1])) {
                return [];
            }
            return $this->attribute($t, $index, $attrName, [FlatTokens::code(\__McTokId::T_STRING, $m[1] === 'enabled' ? 'true' : 'false')]);
        }
        if ($how === 'covers') {
            if (!isset($m[1])) {
                $this->src->abort = true; // $matches[1] is undefined: upstream fails the file
                return [];
            }
            $parts = \explode('::', $m[1]);
            if (\count($parts) === 1) {
                return $this->attribute($t, $index, \str_ends_with($parts[0], 'Trait') ? 'CoversTrait' : 'CoversClass', self::classConstant($parts[0]));
            }
            if (\count($parts) === 2) {
                if ($parts[0] === '') {
                    return $this->attribute($t, $index, 'CoversFunction', [self::str($parts[1])]);
                }
                if ($parts[1] !== '') {
                    return $this->attribute($t, $index, 'CoversMethod', \array_merge(self::classConstant($parts[0]),
                        [FlatTokens::code(44, ','), FlatTokens::ws(' '), self::str(\rtrim($parts[1], " ()\n\r\t\v\x00"))]));
                }
            }
            return [];
        }
        if ($how === 'dataProvider') {
            if (!isset($m[1])) {
                return [];
            }
            if (\str_contains($m[1], '::')) {
                [$class, $method] = \explode('::', $m[1]);
                return $this->attribute($t, $index, 'DataProviderExternal', \array_merge(self::classConstant($class),
                    [FlatTokens::code(44, ','), FlatTokens::ws(' '), self::str(\rtrim($method, " ()\n\r\t\v\x00"))]));
            }
            return $this->attribute($t, $index, 'DataProvider', [self::str(\rtrim($m[1], " ()\n\r\t\v\x00"))]);
        }
        if ($how === 'depends') {
            return $this->depends($t, $index, $m);
        }
        if ($how === 'requires') {
            return $this->requires($t, $index, $m);
        }
        if ($how === 'testWith') {
            $content = Preg::replace('/@testWith\s+/', '', $annotation->getContent());
            $content = Preg::replace('/(^|\R)\s+\**\s*/', "\n", $content);
            $content = \trim($content);
            if ($content === '') {
                return [];
            }
            $out = [];
            foreach (\explode("\n", $content) as $json) {
                $out = \array_merge($out, $this->attribute($t, $index, 'TestWithJson', [self::str($json)]));
            }
            return $out;
        }
        // uses
        if (!isset($m[1])) {
            return [];
        }
        if (\str_starts_with($m[1], '::')) {
            return $this->attribute($t, $index, 'UsesFunction', [self::str(\substr($m[1], 2))]);
        }
        if (Preg::match('/^[a-zA-Z\d\\\]+$/', $m[1])) {
            return $this->attribute($t, $index, 'UsesClass', self::classConstant($m[1]));
        }
        return [];
    }

    /**
     * @param string[] $m
     * @return array<int, array{0:int,1:string,2:int}>
     */
    private function depends(FlatTokens $t, int $index, array $m): array
    {
        if (!isset($m[1])) {
            return [];
        }
        $suffix = '';
        $depended = $m[1];
        if (isset($m[2])) {
            if ($m[1] === 'clone') {
                $suffix = 'UsingDeepClone';
                $depended = $m[2];
            } elseif ($m[1] === 'shallowClone') {
                $suffix = 'UsingShallowClone';
                $depended = $m[2];
            }
        }
        $class = null;
        $method = $depended;
        if (\str_contains($depended, '::')) {
            [$class, $method] = \explode('::', $depended);
            if ($method === 'class') {
                $method = null;
                $suffix = $suffix === '' ? 'OnClass' : 'OnClass' . $suffix;
            } else {
                $suffix = 'External' . $suffix;
            }
        }
        $args = [];
        if ($class !== null) {
            $args = self::classConstant($class);
        }
        if ($method !== null) {
            if ($args !== []) {
                $args[] = FlatTokens::code(44, ',');
                $args[] = FlatTokens::ws(' ');
            }
            $args[] = self::str($method);
        }
        return $this->attribute($t, $index, 'Depends' . $suffix, $args);
    }

    /**
     * @param string[] $m
     * @return array<int, array{0:int,1:string,2:int}>
     */
    private function requires(FlatTokens $t, int $index, array $m): array
    {
        if (!isset($m[1])) {
            return [];
        }
        $map = ['extension' => 'RequiresPhpExtension', 'function' => 'RequiresFunction', 'PHP' => 'RequiresPhp', 'PHPUnit' => 'RequiresPhpunit', 'OS' => 'RequiresOperatingSystem',
            'OSFAMILY' => 'RequiresOperatingSystemFamily', 'setting' => 'RequiresSetting'];
        if (!isset($m[2]) || !isset($map[$m[1]])) {
            return [];
        }
        $name = $map[$m[1]];
        if ($name === 'RequiresFunction' && \str_contains($m[2], '::')) {
            [$class, $method] = \explode('::', $m[2]);
            $name = 'RequiresMethod';
            $args = \array_merge(self::classConstant($class), [FlatTokens::code(44, ','), FlatTokens::ws(' '), self::str($method)]);
        } elseif (($name === 'RequiresPhp' || $name === 'RequiresPhpunit') && isset($m[3])) {
            $args = [self::str($m[2] . ' ' . $m[3])];
        } else {
            $args = [self::str(self::version($m[2]))];
        }
        if (isset($m[3]) && $name !== 'RequiresPhp' && $name !== 'RequiresPhpunit') {
            $args[] = FlatTokens::code(44, ',');
            $args[] = FlatTokens::ws(' ');
            $args[] = self::str(self::version($m[3]));
        }
        return $this->attribute($t, $index, $name, $args);
    }

    private static function version(string $v): string
    {
        return Preg::match('/^[\d\.-]+(dev|(RC|alpha|beta)[\d\.])?$/', $v) ? '>= ' . $v : $v;
    }

    /** @return string[] */
    private function matches(Annotation $annotation): array
    {
        $m = [];
        Preg::match(\sprintf('/@%s\s+(\S+)(?:\s+(\S+))?(?:\s+(.+\S))?\s*(?:\R|\*+\/$)/', $annotation->getTag()->getName()), $annotation->getContent(), $m);
        $out = [];
        foreach ($m as $k => $v) {
            $out[(int) $k] = (string) $v;
        }
        return $out;
    }

    /** @return array{0:int,1:string,2:int} */
    private static function str(string $value): array
    {
        return FlatTokens::code(\__McTokId::T_CONSTANT_ENCAPSED_STRING, "'" . \str_replace("'", "\\'", $value) . "'");
    }

    /**
     * ImportProcessor::tokenizeName + `::class`
     * @return array<int, array{0:int,1:string,2:int}>
     */
    private static function classConstant(string $name): array
    {
        $out = [];
        if ($name[0] === '\\') {
            $out[] = FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\');
            $name = \substr($name, 1);
        }
        foreach (\explode('\\', $name) as $i => $part) {
            if ($i > 0) {
                $out[] = FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\');
            }
            $out[] = FlatTokens::code(\__McTokId::T_STRING, $part);
        }
        $out[] = FlatTokens::code(\__McTokId::T_DOUBLE_COLON, '::');
        $out[] = FlatTokens::code(CT::CLASS_CONSTANT, 'class');
        return $out;
    }

    /**
     * @param array<int, array{0:int,1:string,2:int}> $args
     * @return array<int, array{0:int,1:string,2:int}>
     */
    private function attribute(FlatTokens $t, int $index, string $className, array $args): array
    {
        $out = [$t->item($index + 1), FlatTokens::code(\__McTokId::T_ATTRIBUTE, '#['), FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\'),
            FlatTokens::code(\__McTokId::T_STRING, 'PHPUnit'), FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\'), FlatTokens::code(\__McTokId::T_STRING, 'Framework'),
            FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\'), FlatTokens::code(\__McTokId::T_STRING, 'Attributes'), FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\'),
            FlatTokens::code(\__McTokId::T_STRING, $className)];
        if ($args !== []) {
            $out[] = FlatTokens::code(40, '(');
            foreach ($args as $a) {
                $out[] = $a;
            }
            $out[] = FlatTokens::code(41, ')');
        }
        $out[] = FlatTokens::code(CT::ATTRIBUTE_CLOSE, ']');
        return $out;
    }
}
