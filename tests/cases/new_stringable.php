<?php
namespace A;

class One { public function __toString() { return ''; } }
class Two implements \Stringable { public function __toString() { return ''; } }
class Three implements \Countable { public function __toString() { return ''; } public function count(): int { return 0; } }
class Four extends One { public function __TOSTRING() { return ''; } }
class Five extends One implements \Countable, \Foo { public function __toString(): string { return ''; } }
abstract class Six { abstract public function __toString(); }
interface Seven { public function __toString(); }
trait Eight { public function __toString() { return ''; } }
class Nine { public function foo() { return new class { public function __toString() { return 'x'; } }; } }
class Ten { public function __toString() { $f = function () { return 1; }; return ''; } }
class Eleven implements Stringable { public function __toString() { return ''; } }
class Twelve
{
    public function __toString() { return ''; }
}
class Thirteen /* c */ { public function __toString() { return ''; } }
class Fourteen implements
    \Countable
{
    public function __toString() { return ''; }
    public function count(): int { return 0; }
}
class Fifteen{ public function __toString() { return ''; } }
enum E { }
final class Sixteen { function __toString() { return ''; } }
class Seventeen { public function foo() { return $this; } }
