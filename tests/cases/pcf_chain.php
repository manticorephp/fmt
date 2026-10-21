<?php
$user->setEmail('voff.web@gmail.com')
         ->setPassword('233434')
     ->setEmailConfirmed(false)
        ->setEmailConfirmationCode('expected-code')
    ->setHashsalt('salt')
->setPhoneNumber(1234567890);
$object->method1()
    ->method2()
        ->method3(function ($x) {
              return $x
                    ->foo()
                ->bar();
        })
     ->method4(
                 1,
        2
          );
if ($a) {
        $b = $c
          ->d()
                  ->e($f,
            $g)
         ->h();
    $x = foo(
        $a
          ->b()
              ->c()
    );
}
$a = $b->c()   // comment
    ->d();
$a = $b->c()
  /* comment */ ->d();
$a = $b->c()
  // comment
  ->d();
$a = $b->c()
      /** doc */ ->d()
 ->e();
Foo::bar()
    ->baz()
        ->qux();
$a = $b?->c()
      ?->d()
    ->e();
$this->foo(
        $this->bar()
    ->baz()
)
          ->qux();
$x = $a->b()->c()
->d()->e()
         ->f();
function f() {
    return $this->a()
->b()
            ->c(1, 2,
             3, 4);
}
$arr = [
    'k' => $a->b()
              ->c(),
      'l' => $d
->e(),
];
$x = $a
    ->b
      ->c
 ->d;
$x = (new Foo())
  ->bar()
        ->baz();
$x = $a->b(fn($z) => $z
    ->y()
          ->x())
   ->w();
?>
<?= $a->b()
  ->c() ?>
<?php
class A {
    function f() {
        return $this
                ->a()
            ->b();
    }
}
$o = $a->b(<<<EOT
text
EOT
)
      ->c();
$long = $foo->bar(
    $baz->qux()
          ->quux(),
         $z
)->end()
   ->more();
echo $a
	->b()
      ->c();
