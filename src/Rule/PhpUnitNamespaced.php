<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Invocation;
use Fmt\Preg;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\PhpUnit\PhpUnitNamespacedFixer (priority 2, risky). `PHPUnit_Framework_TestCase` -> `PHPUnit\Framework\TestCase`: a T_STRING that is a classy invocation or an
 * import and matches the target's pattern (4.8: TestCase; 5.7: + Assert, BaseTestListener, TestListener; 6.0 / newest: every `PHPUnit_*` class, with upstream's class map) is replaced by the
 * namespaced name; after an import the later uses get the last part only. Whole-file FlatTokens port.
 */
final class PhpUnitNamespaced extends FlatRule
{
    private string $regex = '';
    /** @var array<string, string> lower-cased old name => new name */
    private array $map = [];

    public function __construct(string $target)
    {
        parent::__construct();
        if ($target === 'newest' || $target === '6.0') {
            $this->regex = '/^PHPUnit_\w+$/i';
            $classMap = [
                    'PHPUnit_Extensions_PhptTestCase' => 'PHPUnit\Runner\PhptTestCase',
                    'PHPUnit_Framework_Constraint' => 'PHPUnit\Framework\Constraint\Constraint',
                    'PHPUnit_Framework_Constraint_StringMatches' => 'PHPUnit\Framework\Constraint\StringMatchesFormatDescription',
                    'PHPUnit_Framework_Constraint_JsonMatches_ErrorMessageProvider' => 'PHPUnit\Framework\Constraint\JsonMatchesErrorMessageProvider',
                    'PHPUnit_Framework_Constraint_PCREMatch' => 'PHPUnit\Framework\Constraint\RegularExpression',
                    'PHPUnit_Framework_Constraint_ExceptionMessageRegExp' => 'PHPUnit\Framework\Constraint\ExceptionMessageRegularExpression',
                    'PHPUnit_Framework_Constraint_And' => 'PHPUnit\Framework\Constraint\LogicalAnd',
                    'PHPUnit_Framework_Constraint_Or' => 'PHPUnit\Framework\Constraint\LogicalOr',
                    'PHPUnit_Framework_Constraint_Not' => 'PHPUnit\Framework\Constraint\LogicalNot',
                    'PHPUnit_Framework_Constraint_Xor' => 'PHPUnit\Framework\Constraint\LogicalXor',
                    'PHPUnit_Framework_Error' => 'PHPUnit\Framework\Error\Error',
                    'PHPUnit_Framework_TestSuite_DataProvider' => 'PHPUnit\Framework\DataProviderTestSuite',
                    'PHPUnit_Framework_MockObject_Invocation_Static' => 'PHPUnit\Framework\MockObject\Invocation\StaticInvocation',
                    'PHPUnit_Framework_MockObject_Invocation_Object' => 'PHPUnit\Framework\MockObject\Invocation\ObjectInvocation',
                    'PHPUnit_Framework_MockObject_Stub_Return' => 'PHPUnit\Framework\MockObject\Stub\ReturnStub',
                    'PHPUnit_Runner_Filter_Group_Exclude' => 'PHPUnit\Runner\Filter\ExcludeGroupFilterIterator',
                    'PHPUnit_Runner_Filter_Group_Include' => 'PHPUnit\Runner\Filter\IncludeGroupFilterIterator',
                    'PHPUnit_Runner_Filter_Test' => 'PHPUnit\Runner\Filter\NameFilterIterator',
                    'PHPUnit_Util_PHP' => 'PHPUnit\Util\PHP\AbstractPhpProcess',
                    'PHPUnit_Util_PHP_Default' => 'PHPUnit\Util\PHP\DefaultPhpProcess',
                    'PHPUnit_Util_PHP_Windows' => 'PHPUnit\Util\PHP\WindowsPhpProcess',
                    'PHPUnit_Util_Regex' => 'PHPUnit\Util\RegularExpression',
                    'PHPUnit_Util_TestDox_ResultPrinter_XML' => 'PHPUnit\Util\TestDox\XmlResultPrinter',
                    'PHPUnit_Util_TestDox_ResultPrinter_HTML' => 'PHPUnit\Util\TestDox\HtmlResultPrinter',
                    'PHPUnit_Util_TestDox_ResultPrinter_Text' => 'PHPUnit\Util\TestDox\TextResultPrinter',
                    'PHPUnit_Util_TestSuiteIterator' => 'PHPUnit\Framework\TestSuiteIterator',
                    'PHPUnit_Util_XML' => 'PHPUnit\Util\Xml',
            ];
            foreach ($classMap as $from => $to) {
                $this->map[\strtolower($from)] = $to;
            }
        } elseif ($target === '5.7') {
            $this->regex = '/^PHPUnit_Framework_(TestCase|Assert|BaseTestListener|TestListener)+$/i';
        } else {
            $this->regex = '/^PHPUnit_Framework_TestCase$/i';
        }
    }

    public function name(): string
    {
        return 'php_unit_namespaced';
    }

    public function priority(): int
    {
        return 2;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_STRING && $s->end[$i] - $s->start[$i] >= 15 && \Fmt\Preg::match($this->regex, $s->text($i))) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        $hasAttr = false;
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_ATTRIBUTE) {
                $hasAttr = true;
                break;
            }
        }
        /** @var array<string, bool> $imported */
        $imported = [];
        $cur = 0;
        $m = $t->count();
        while (true) {
            $found = -1;
            for ($j = $cur + 1; $j < $m; $j++) {
                if ($t->i[$j] !== -1 && $t->k[$j] === \__McTokId::T_STRING) {
                    $found = $j;
                    break;
                }
            }
            if ($found < 0) {
                break;
            }
            $cur = $found;
            $prev = $t->meaningful($cur, -1);
            if ($prev >= 0 && $t->i[$prev] !== -1 && ($t->k[$prev] === \__McTokId::T_CONST || $t->k[$prev] === \__McTokId::T_DOUBLE_COLON)) {
                continue;
            }
            $orig = $t->content($cur);
            $allowed = $t->i[$cur] >= 0 && (Invocation::isClassyInvocation($s, $t->i[$cur], $hasAttr) || $this->isImport($t, $cur));
            if (!$allowed || !Preg::match($this->regex, $orig)) {
                continue;
            }
            $delimiter = '_';
            $string = $orig;
            if (isset($this->map[\strtolower($orig)])) {
                $delimiter = '\\';
                $string = $this->map[\strtolower($orig)];
            }
            $parts = \explode($delimiter, $string);
            $items = [];
            foreach ($parts as $x => $part) {
                $items[] = FlatTokens::code(\__McTokId::T_STRING, $part);
                if ($x < \count($parts) - 1) {
                    $items[] = FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\');
                }
            }
            $t->clearAt($cur);
            if (isset($imported[$orig])) {
                $items = [$items[\count($items) - 1]];
            }
            $t->insertAt($cur, $items);
            $m = $t->count();
            $prev = $t->meaningful($cur, -1);
            if ($prev >= 0 && $t->i[$prev] !== -1 && $t->k[$prev] === \__McTokId::T_USE) {
                $imported[$orig] = true;
            } elseif ($prev >= 0 && $t->i[$prev] !== -1 && $t->k[$prev] === \__McTokId::T_NS_SEPARATOR) {
                $prev = $t->meaningful($prev, -1);
                if ($prev >= 0 && $t->i[$prev] !== -1 && $t->k[$prev] === \__McTokId::T_USE) {
                    $imported[$orig] = true;
                }
            }
        }
    }

    private function isImport(FlatTokens $t, int $cur): bool
    {
        $prev = $t->meaningful($cur, -1);
        if ($prev >= 0 && $t->i[$prev] !== -1 && $t->k[$prev] === \__McTokId::T_NS_SEPARATOR) {
            $prev = $t->meaningful($prev, -1);
        }
        return $prev >= 0 && $t->i[$prev] !== -1 && $t->k[$prev] === \__McTokId::T_USE;
    }
}
