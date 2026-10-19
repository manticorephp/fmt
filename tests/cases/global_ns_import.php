<?php
namespace App\Model;

use DateTime;
use Exception;
use ArrayObject as AO;
use Foo\Bar;
use function strlen;
use function Other\fn2;
use const PHP_EOL;
use const E_ALL, E_STRICT;

/**
 * @param DateTime|null $d
 * @return Exception[]|array<int, DateTime>
 * @throws Exception
 * @var \DateTime $x
 * @see DateTime::format()
 */
final class Thing extends DateTime implements \Countable
{
    use SomeTrait;

    public function f(DateTime $d, ?Exception $e = null, DateTime|Bar $u = null): DateTime
    {
        $x = new DateTime();
        $y = DateTime::createFromFormat('Y', '2020');
        if ($x instanceof DateTime) {
            echo strlen('a') . PHP_EOL . \strlen('b') . E_ALL;
        }
        try {
        } catch (Exception $e) {
        } catch (\Exception|Exception $e) {
        }
        $f = fn (DateTime $q): Exception => new Exception();
        $c = DateTime::class;
        strlen(...);
        $o->strlen(1);
        Foo::strlen();
        return new class extends DateTime {};
    }

    public function count(): int { return PHP_EOL; }
}
function strlen2() { return \PHP_EOL; }
#[DateTime]
function g(AO $a) {}
