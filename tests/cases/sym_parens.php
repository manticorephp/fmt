<?php
return ($a);
return ($a + $b);
return ($a) + 1;
return (1) + (2);
return ($a)->b;
return ($a)[1];
return !($a instanceof B);
return ($a instanceof B);
return ($a && $b);
return ((($a)));
return (clone $a);
echo ($a);
echo ($a), ($b);
echo ($a . $b);
print ($a);
throw ($e);
break (1);
continue (2);
clone ($a);
clone ($a->b);
clone (new A);
clone (new A());
clone (new A(1));
clone ($a ?: $b);
$x = clone ($a);
$x = ($a);
$x = ($a) . 'b';
$x = ($a)->b;
$x = ($a) ? 1 : 2;
$x = ($a instanceof B);
$x = !($a instanceof B);
$x = (int) ($a);
$x = -($a);
$x = ($a) - ($b);
$x = [($a), ($b)];
$x = [($a) + 1];
$x = [1 + ($a)];
$x = foo(($a));
$x = foo(($a), ($b));
$x = $a[($b)];
$x = new Foo(($a));
$x = (new Foo)->bar();
$x = (new Foo())->bar;
$x = ($a)();
$x = ($a)::B;
$x = (A::class);
$x = ('a');
$x = (1);
$x = (static::$a);
$x = (parent::A);
$x = ($a++);
$x = (++$a);
$x = (!$a);
switch (true) { case ($a): break; case ($a + 1): break; case (1): foo(); }
for (($a); ($b); ($c)) {}
for ($i = ($a); ($b);) {}
if (($a)) {}
while (($a)) {}
foreach (($a) as $b) {}
$f = fn() => ($a);
$f = fn($a): int => ($a);
$f = fn() => ($a) + 1;
exit(($a));
die();
exit();
exit ();
$x = ($a) ?? ($b);
$x = ($a) . ($b) . ($c);
$x = ($a) + ($b) * ($c);
$x = ($a) && ($b) || ($c);
$x = $a . ($b) . $c;
$x = (clone $a)->b;
$x = ((clone $a));
$a[($b)] = ($c);
list($a, ($b)) = $c;
$x = '' . ($a) . '';
$x = ($a)
    . 'b';
$x = /* c */ ($a) /* d */;
return /* c */ ($a) /* d */;
return($a);
echo($a);
echo($a).'b';
echo ($a) ?>
<?php return ($a) ?>
<?= ($a) ?>
$x = ($a) // c
;
$x = $a ? ($b) : ($c);
$x = (function () {})();
$x = (fn() => 1)();
if ($a) { ($b); }
{ ($a) + 1; }
; ($a) + 1;
($a) + 1;
(1) + 2;
$x = ($y = ($z));
$x = (($a) and ($b));
$x = !($a);
$x = @($a);
$x = ~($a);
$x = ($a) <=> ($b);
$x = $a ?: ($b);
$x = ($a) ?: $b;
$x = ($a) | ($b);
fn&($a) => ($b);
$x = (int)($a);
$x = (bool) (!$a);
