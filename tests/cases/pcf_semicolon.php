<?php
function foo() {
    return 1 + 2
        ;
}
$a = 1
;
$object->method1()
    ->method2()
    ->method(3);
$object->method1()
    ->method2()
    ->method(3)
;
$object->method1()
    ->method2()
    ->method(3)
    ;
$object->method1()->method2();
$object->method1()
    ->method2();
Foo::bar()
    ::baz();
Foo::bar()->baz()
    ->qux() // trailing
    ;
$x = $o->a()
    ->b(function () {
        return 1;
    })
    ->c([1, 2,
        3]);
if ($a) {
    $result = $this->repo
        ->find(1)
        ->name;
    $result = $this->repo->find(1)
        ->name ;
}
$a = foo()
    // comment
    ;
$a = foo()   /* c */
    ;
$a = foo() /* c */;
$a = foo() // c
;
const X = 1
;
class A {
    const B = 2
    ;
    public $p = [1,
        2]
        ;
    public function f() {
        return $this
            ->a()
            ->b()
        ;
    }
}
echo 1
;
echo $a->b()
    ->c()
;
switch ($x) {
    case 1:
        $a->b()
            ->c();
        break
        ;
}
$a = <<<EOT
x
EOT
;
$a->b(<<<EOT
x
EOT
)->c()
    ->d();
?>
<?php foo()
    ->bar()
    ->baz();
$a = [
    'x' => $o->a()
        ->b(),
];
$f = fn() => $o->a()
    ->b();
foreach ($a as $b) $c->d()
    ->e();
$i = $a?->b()
    ?->c();
$z = $a
    ->b
    ->c;
