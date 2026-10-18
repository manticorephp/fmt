<?php
$f = strlen(...);
$g = $obj->method(...);
$h = Foo::bar(...);
$i = new Foo(...);
$j = $a?->b?->c();
$k = $a?->{$name}?->{'x' . $y};
$l = $a->{'prop'};
$m = $a->{$b . $c}();
${'var' . $n} = 1;
$$name = 2;
${$name}[0] = 3;
$s = "text {$a->b} and ${var} and {$a['k']} and $a[0] and $a->b";
$s2 = "{$a?->b->{$c}}";
$e = <<<EOT
  heredoc {$a->b} ${c} $d[1] \$e
  EOT;
$n = <<<'EOT'
  nowdoc {$x}
  EOT;
$cc = Foo::{$name}();
$dc = Foo::{'CONST'};
echo Foo::class, $obj::class, static::class, \Foo\Bar::class;
echo namespace\foo(), \Foo\bar(), Foo\Bar::baz();
$arr = [1, [2, 3], 'k' => [4]];
[$a, [$b, $c]] = [1, [2, 3]];
['x' => $x, 'y' => [$y1, $y2]] = $arr;
foreach ($arr as [$p, $q]) {}
foreach ($arr as $k => [$p, [$r]]) {}
list($a, list($b)) = $arr;
[, $b] = $arr;
[$a, $b] = [$b, $a];
$v = $arr[0][1];
$v = $obj->arr['k'][1];
$v = foo()[0];
$v = (function () { return [1]; })()[0];
$v = (new Foo)->bar;
$v = (new Foo())->bar;
$v = (new Foo(1))::X;
$v = [...$a, ...$b];
$v = array(1, 2);
$v = array(array(1));
function typed(array $a, callable $c, iterable $i, ?array $n = null): array|bool { return []; }
