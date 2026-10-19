<?php
throw new Exception('a');
throw new Exception(
    'a'
);
throw new Exception('a',
    1,
    2
);
throw
    new Exception('a');
throw new
    Exception('a');
throw new Exception('a') // c
;
throw new Exception(// c
    'a'
);
throw new Exception(# c
    'a'
);
throw new Exception(/* c
d */ 'a');
throw new Exception(/** doc
d */ 'a');
throw
    $e;
throw $this
    ->foo()
    ->bar();
throw Foo
    ::create();
throw Foo::
    create();
throw $e ?->
    f();
throw new Foo(
    [
        1,
        2,
    ],
    function () {
        return 1;
    },
);
throw new class(1) {
    public function f() {}
};
function f() { throw new E(
    'x'
); }
$x = $a ?? throw new E(
    'y'
);
$f = fn() => throw new E(
    'z'
);
match ($a) { 1 => throw new E(
    'q'
), };
throw ($a ?
    $b : $c);
throw new Exception("a
b");
throw new Exception(<<<EOT
  x
  EOT
);
if ($a) throw
  new E;
throw function
 () {};
throw (
 $e
 );
throw $a[
  1
];
throw $a [ 1 ] ->
 b;
