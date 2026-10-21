<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\FlatTokens;
use Fmt\PhpUnitClassRule;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitTestCaseStaticMethodCallsFixer (priority 0, risky). Calls of PHPUnit's static methods (assertions, `createStub`, ..) on `$this` / `self` /
 * `static` inside a test class are written with the configured call type (`call_type`, overridden per method by `methods`); with target 11.0+ the methods that are dynamic there keep
 * `$this`. A nested class, a closure and (call type `this`) a static method are skipped. Whole-file FlatTokens port; the method table is upstream's.
 */
final class PhpUnitTestCaseStaticMethodCalls extends PhpUnitClassRule
{
    private const METHODS = [
        // Assert methods
        'anything' => true,
        'arrayHasKey' => true,
        'assertArrayHasKey' => true,
        'assertArrayIsEqualToArrayIgnoringListOfKeys' => true,
        'assertArrayIsEqualToArrayOnlyConsideringListOfKeys' => true,
        'assertArrayIsIdenticalToArrayIgnoringListOfKeys' => true,
        'assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys' => true,
        'assertArrayNotHasKey' => true,
        'assertArraySubset' => true,
        'assertArraysAreIdentical' => true,
        'assertArraysAreIdenticalIgnoringOrder' => true,
        'assertArraysHaveIdenticalValues' => true,
        'assertArraysHaveIdenticalValuesIgnoringOrder' => true,
        'assertArraysAreEqual' => true,
        'assertArraysAreEqualIgnoringOrder' => true,
        'assertArraysHaveEqualValues' => true,
        'assertArraysHaveEqualValuesIgnoringOrder' => true,
        'assertAttributeContains' => true,
        'assertAttributeContainsOnly' => true,
        'assertAttributeCount' => true,
        'assertAttributeEmpty' => true,
        'assertAttributeEquals' => true,
        'assertAttributeGreaterThan' => true,
        'assertAttributeGreaterThanOrEqual' => true,
        'assertAttributeInstanceOf' => true,
        'assertAttributeInternalType' => true,
        'assertAttributeLessThan' => true,
        'assertAttributeLessThanOrEqual' => true,
        'assertAttributeNotContains' => true,
        'assertAttributeNotContainsOnly' => true,
        'assertAttributeNotCount' => true,
        'assertAttributeNotEmpty' => true,
        'assertAttributeNotEquals' => true,
        'assertAttributeNotInstanceOf' => true,
        'assertAttributeNotInternalType' => true,
        'assertAttributeNotSame' => true,
        'assertAttributeSame' => true,
        'assertClassHasAttribute' => true,
        'assertClassHasStaticAttribute' => true,
        'assertClassNotHasAttribute' => true,
        'assertClassNotHasStaticAttribute' => true,
        'assertContains' => true,
        'assertContainsEquals' => true,
        'assertContainsNotOnlyArray' => true,
        'assertContainsNotOnlyBool' => true,
        'assertContainsNotOnlyCallable' => true,
        'assertContainsNotOnlyClosedResource' => true,
        'assertContainsNotOnlyFloat' => true,
        'assertContainsNotOnlyInstancesOf' => true,
        'assertContainsNotOnlyInt' => true,
        'assertContainsNotOnlyIterable' => true,
        'assertContainsNotOnlyNull' => true,
        'assertContainsNotOnlyNumeric' => true,
        'assertContainsNotOnlyObject' => true,
        'assertContainsNotOnlyResource' => true,
        'assertContainsNotOnlyScalar' => true,
        'assertContainsNotOnlyString' => true,
        'assertContainsOnly' => true,
        'assertContainsOnlyArray' => true,
        'assertContainsOnlyBool' => true,
        'assertContainsOnlyCallable' => true,
        'assertContainsOnlyClosedResource' => true,
        'assertContainsOnlyFloat' => true,
        'assertContainsOnlyInstancesOf' => true,
        'assertContainsOnlyInt' => true,
        'assertContainsOnlyIterable' => true,
        'assertContainsOnlyNull' => true,
        'assertContainsOnlyNumeric' => true,
        'assertContainsOnlyObject' => true,
        'assertContainsOnlyResource' => true,
        'assertContainsOnlyScalar' => true,
        'assertContainsOnlyString' => true,
        'assertCount' => true,
        'assertDirectoryDoesNotExist' => true,
        'assertDirectoryExists' => true,
        'assertDirectoryIsNotReadable' => true,
        'assertDirectoryIsNotWritable' => true,
        'assertDirectoryIsReadable' => true,
        'assertDirectoryIsWritable' => true,
        'assertDirectoryNotExists' => true,
        'assertDirectoryNotIsReadable' => true,
        'assertDirectoryNotIsWritable' => true,
        'assertDoesNotMatchRegularExpression' => true,
        'assertEmpty' => true,
        'assertEquals' => true,
        'assertEqualsCanonicalizing' => true,
        'assertEqualsIgnoringCase' => true,
        'assertEqualsWithDelta' => true,
        'assertEqualXMLStructure' => true,
        'assertFalse' => true,
        'assertFileDoesNotExist' => true,
        'assertFileEquals' => true,
        'assertFileEqualsCanonicalizing' => true,
        'assertFileEqualsFileIgnoringWhitespace' => true,
        'assertFileEqualsIgnoringCase' => true,
        'assertFileExists' => true,
        'assertFileIsNotReadable' => true,
        'assertFileIsNotWritable' => true,
        'assertFileIsReadable' => true,
        'assertFileIsWritable' => true,
        'assertFileMatchesFormat' => true,
        'assertFileMatchesFormatFile' => true,
        'assertFileNotEquals' => true,
        'assertFileNotEqualsCanonicalizing' => true,
        'assertFileNotEqualsFileIgnoringWhitespace' => true,
        'assertFileNotEqualsIgnoringCase' => true,
        'assertFileNotExists' => true,
        'assertFileNotIsReadable' => true,
        'assertFileNotIsWritable' => true,
        'assertFinite' => true,
        'assertGreaterThan' => true,
        'assertGreaterThanOrEqual' => true,
        'assertInfinite' => true,
        'assertInstanceOf' => true,
        'assertInternalType' => true,
        'assertIsArray' => true,
        'assertIsBool' => true,
        'assertIsCallable' => true,
        'assertIsClosedResource' => true,
        'assertIsFloat' => true,
        'assertIsInt' => true,
        'assertIsIterable' => true,
        'assertIsList' => true,
        'assertIsNotArray' => true,
        'assertIsNotBool' => true,
        'assertIsNotCallable' => true,
        'assertIsNotClosedResource' => true,
        'assertIsNotFloat' => true,
        'assertIsNotInt' => true,
        'assertIsNotIterable' => true,
        'assertIsNotNumeric' => true,
        'assertIsNotObject' => true,
        'assertIsNotReadable' => true,
        'assertIsNotResource' => true,
        'assertIsNotScalar' => true,
        'assertIsNotString' => true,
        'assertIsNotWritable' => true,
        'assertIsNumeric' => true,
        'assertIsObject' => true,
        'assertIsReadable' => true,
        'assertIsResource' => true,
        'assertIsScalar' => true,
        'assertIsString' => true,
        'assertIsWritable' => true,
        'assertJson' => true,
        'assertJsonFileEqualsJsonFile' => true,
        'assertJsonFileNotEqualsJsonFile' => true,
        'assertJsonStringEqualsJsonFile' => true,
        'assertJsonStringEqualsJsonString' => true,
        'assertJsonStringNotEqualsJsonFile' => true,
        'assertJsonStringNotEqualsJsonString' => true,
        'assertLessThan' => true,
        'assertLessThanOrEqual' => true,
        'assertMatchesRegularExpression' => true,
        'assertNan' => true,
        'assertNotContains' => true,
        'assertNotContainsEquals' => true,
        'assertNotContainsOnly' => true,
        'assertNotCount' => true,
        'assertNotEmpty' => true,
        'assertNotEquals' => true,
        'assertNotEqualsCanonicalizing' => true,
        'assertNotEqualsIgnoringCase' => true,
        'assertNotEqualsWithDelta' => true,
        'assertNotFalse' => true,
        'assertNotInstanceOf' => true,
        'assertNotInternalType' => true,
        'assertNotIsReadable' => true,
        'assertNotIsWritable' => true,
        'assertNotNull' => true,
        'assertNotRegExp' => true,
        'assertNotSame' => true,
        'assertNotSameSize' => true,
        'assertNotTrue' => true,
        'assertNull' => true,
        'assertObjectEquals' => true,
        'assertObjectHasAttribute' => true,
        'assertObjectHasProperty' => true,
        'assertObjectNotEquals' => true,
        'assertObjectNotHasAttribute' => true,
        'assertObjectNotHasProperty' => true,
        'assertRegExp' => true,
        'assertSame' => true,
        'assertSameSize' => true,
        'assertStringContainsString' => true,
        'assertStringContainsStringIgnoringCase' => true,
        'assertStringContainsStringIgnoringLineEndings' => true,
        'assertStringEndsNotWith' => true,
        'assertStringEndsWith' => true,
        'assertStringEqualsFile' => true,
        'assertStringEqualsFileCanonicalizing' => true,
        'assertStringEqualsFileIgnoringCase' => true,
        'assertStringEqualsFileIgnoringWhitespace' => true,
        'assertStringEqualsStringIgnoringLineEndings' => true,
        'assertStringEqualsStringIgnoringWhitespace' => true,
        'assertStringMatchesFormat' => true,
        'assertStringMatchesFormatFile' => true,
        'assertStringNotContainsString' => true,
        'assertStringNotContainsStringIgnoringCase' => true,
        'assertStringNotEqualsFile' => true,
        'assertStringNotEqualsFileCanonicalizing' => true,
        'assertStringNotEqualsFileIgnoringCase' => true,
        'assertStringNotEqualsFileIgnoringWhitespace' => true,
        'assertStringNotEqualsStringIgnoringWhitespace' => true,
        'assertStringNotMatchesFormat' => true,
        'assertStringNotMatchesFormatFile' => true,
        'assertStringStartsNotWith' => true,
        'assertStringStartsWith' => true,
        'assertThat' => true,
        'assertTrue' => true,
        'assertXmlFileEqualsXmlFile' => true,
        'assertXmlFileEqualsXmlFileConsideringComments' => true,
        'assertXmlFileNotEqualsXmlFile' => true,
        'assertXmlFileNotEqualsXmlFileConsideringComments' => true,
        'assertXmlStringEqualsXmlFile' => true,
        'assertXmlStringEqualsXmlFileConsideringComments' => true,
        'assertXmlStringEqualsXmlString' => true,
        'assertXmlStringEqualsXmlStringConsideringComments' => true,
        'assertXmlStringNotEqualsXmlFile' => true,
        'assertXmlStringNotEqualsXmlFileConsideringComments' => true,
        'assertXmlStringNotEqualsXmlString' => true,
        'assertXmlStringNotEqualsXmlStringConsideringComments' => true,
        'attribute' => true,
        'attributeEqualTo' => true,
        'callback' => true,
        'classHasAttribute' => true,
        'classHasStaticAttribute' => true,
        'contains' => true,
        'containsEqual' => true,
        'containsIdentical' => true,
        'containsOnly' => true,
        'containsOnlyArray' => true,
        'containsOnlyBool' => true,
        'containsOnlyCallable' => true,
        'containsOnlyClosedResource' => true,
        'containsOnlyFloat' => true,
        'containsOnlyInstancesOf' => true,
        'containsOnlyInt' => true,
        'containsOnlyIterable' => true,
        'containsOnlyNull' => true,
        'containsOnlyNumeric' => true,
        'containsOnlyObject' => true,
        'containsOnlyResource' => true,
        'containsOnlyScalar' => true,
        'containsOnlyString' => true,
        'countOf' => true,
        'directoryExists' => true,
        'equalTo' => true,
        'equalToCanonicalizing' => true,
        'equalToIgnoringCase' => true,
        'equalToWithDelta' => true,
        'fail' => true,
        'fileExists' => true,
        'getCount' => true,
        'getObjectAttribute' => true,
        'getStaticAttribute' => true,
        'greaterThan' => true,
        'greaterThanOrEqual' => true,
        'identicalTo' => true,
        'isArray' => true,
        'isBool' => true,
        'isCallable' => true,
        'isClosedResource' => true,
        'isEmpty' => true,
        'isFalse' => true,
        'isFinite' => true,
        'isFloat' => true,
        'isInfinite' => true,
        'isInstanceOf' => true,
        'isInt' => true,
        'isIterable' => true,
        'isJson' => true,
        'isList' => true,
        'isNan' => true,
        'isNull' => true,
        'isNumeric' => true,
        'isObject' => true,
        'isReadable' => true,
        'isResource' => true,
        'isScalar' => true,
        'isString' => true,
        'isTrue' => true,
        'isType' => true,
        'isWritable' => true,
        'lessThan' => true,
        'lessThanOrEqual' => true,
        'logicalAnd' => true,
        'logicalNot' => true,
        'logicalOr' => true,
        'logicalXor' => true,
        'markTestIncomplete' => true,
        'markTestSkipped' => true,
        'matches' => true,
        'matchesRegularExpression' => true,
        'objectEquals' => true,
        'objectHasAttribute' => true,
        'readAttribute' => true,
        'resetCount' => true,
        'stringContains' => true,
        'stringEndsWith' => true,
        'stringEqualsStringIgnoringLineEndings' => true,
        'stringEqualsStringIgnoringWhitespace' => true,
        'stringStartsWith' => true,

        // TestCase methods
        'any' => true,
        'at' => true,
        'atLeast' => true,
        'atLeastOnce' => true,
        'atMost' => true,
        'createStub' => true,
        'createConfiguredStub' => true,
        'createStubForIntersectionOfInterfaces' => true,
        'exactly' => true,
        'getStubBuilder' => true,
        'never' => true,
        'once' => true,
        'onConsecutiveCalls' => true,
        'returnArgument' => true,
        'returnCallback' => true,
        'returnSelf' => true,
        'returnValue' => true,
        'returnValueMap' => true,
        'setUpBeforeClass' => true,
        'tearDownAfterClass' => true,
        'throwException' => true,
    ];

    public static function knownMethod(string $m): bool
    {
        return isset(self::METHODS[$m]);
    }

    /** @var array<string, string> method => call type */
    private array $methodTypes = [];

    /** @param array<string, string> $methods */
    public function __construct(private string $callType, array $methods, string $target)
    {
        parent::__construct();
        $dynamic = [];
        if ($target === 'newest' || $target === '11.0') {
            $dynamic = ['any', 'atLeast', 'atLeastOnce', 'atMost', 'exactly', 'never', 'once', 'throwException'];
        }
        if ($target === '11.0') {
            foreach (['onConsecutiveCalls', 'returnArgument', 'returnCallback', 'returnSelf', 'returnValue', 'returnValueMap'] as $d) {
                $dynamic[] = $d;
            }
        }
        foreach ($dynamic as $m) {
            if (isset($methods[$m])) {
                throw new \InvalidArgumentException("php_unit_test_case_static_method_calls: configuration cannot contain method \"$m\" and target \"$target\", it is dynamic in that PHPUnit version");
            }
            $methods[$m] = 'this';
        }
        $this->methodTypes = $methods;
    }

    public function name(): string
    {
        return 'php_unit_test_case_static_method_calls';
    }

    public function priority(): int
    {
        return 0;
    }

    /** @return array{0:int,1:string,2:int,3:string} [operator id, operator, reference id, reference] */
    private static function conversion(string $type): array
    {
        if ($type === 'this') {
            return [\__McTokId::T_OBJECT_OPERATOR, '->', \__McTokId::T_VARIABLE, '$this'];
        }
        if ($type === 'self') {
            return [\__McTokId::T_DOUBLE_COLON, '::', \__McTokId::T_STRING, 'self'];
        }
        return [\__McTokId::T_DOUBLE_COLON, '::', \__McTokId::T_STATIC, 'static'];
    }

    private static function endOfNextBlock(FlatTokens $t, int $index): int
    {
        $m = $t->count();
        for ($j = $index + 1; $j < $m; $j++) {
            if ($t->i[$j] !== -1 && ($t->k[$j] === 59 || $t->k[$j] === 123)) {
                return $t->k[$j] === 123 ? $t->blockEnd($j, 125) : $j;
            }
        }
        return $m - 1;
    }

    protected function applyClass(FlatTokens $t, int $start, int $end): void
    {
        for ($index = $start; $index < $end; $index++) {
            if ($t->i[$index] === -1) {
                continue;
            }
            $k = $t->k[$index];
            if ($k === \__McTokId::T_CLASS) {
                $index = self::endOfNextBlock($t, $index);
                continue;
            }
            $callType = $this->callType;
            if ($k === \__McTokId::T_FUNCTION) {
                if ($t->i[$index] >= 0 && Analyzer::isLambda($this->src, $t->i[$index])) {
                    $index = self::endOfNextBlock($t, $index);
                    continue;
                }
                if ($callType === 'this') {
                    $static = false;
                    for ($i = $index; $i >= 0; $i--) {
                        $i = $t->meaningful($i, -1);
                        if ($i < 0) {
                            break;
                        }
                        $pk = $t->i[$i] === -1 ? -1 : $t->k[$i];
                        if ($pk === \__McTokId::T_STATIC) {
                            $static = true;
                            continue;
                        }
                        if ($pk === \__McTokId::T_FINAL || $pk === \__McTokId::T_ABSTRACT || $pk === \__McTokId::T_PRIVATE || $pk === \__McTokId::T_PROTECTED || $pk === \__McTokId::T_PUBLIC) {
                            continue;
                        }
                        break;
                    }
                    if ($static) {
                        $index = self::endOfNextBlock($t, $index);
                        continue;
                    }
                }
            }
            if ($k !== \__McTokId::T_STRING || !isset(self::METHODS[$t->content($index)])) {
                continue;
            }
            $next = $t->meaningful($index, 1);
            if (!self::is($t, $next, 40)) {
                $index = $next;
                continue;
            }
            $after = $t->meaningful($next, 1);
            if ($after >= 0 && $t->i[$after] !== -1 && $t->k[$after] === CT::FIRST_CLASS_CALLABLE) {
                continue;
            }
            $method = $t->content($index);
            if (isset($this->methodTypes[$method])) {
                $callType = $this->methodTypes[$method];
            }
            $op = $t->meaningful($index, -1);
            $ref = $op >= 0 ? $t->meaningful($op, -1) : -1;
            if ($ref < 0 || !PhpUnitConstruct::sameClassCall($t, $index)) {
                continue;
            }
            $conv = self::conversion($callType);
            $cmp = $t->i[$ref] !== -1 && \strcasecmp($t->content($ref), $conv[3]) === 0 && $t->k[$ref] === $conv[2];
            if ($cmp) {
                continue;
            }
            self::setCode($t, $op, $conv[0], $conv[1]);
            self::setCode($t, $ref, $conv[2], $conv[3]);
        }
    }
}
