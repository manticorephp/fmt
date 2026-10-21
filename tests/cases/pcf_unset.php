<?php
unset($a); unset($b);
unset($a);
unset($b);
unset($a, $b); unset($c, $d);
unset($a); unset($b); unset($c);
unset($a);

unset($b);
unset( $a ); unset( $b );
unset($a); // c
unset($b);
unset($a); /* c */ unset($b);
unset($a)  ;  unset($b);
unset($a[1]); unset($b->c, $d['x']);
if ($x) { unset($a); unset($b); }
if ($x) { unset($a); } unset($b);
unset($a); echo 1; unset($b);
function f() {
    unset($a);
    unset($b);
    unset($c);
}
unset(
    $a
);
unset(
    $b,
    $c
);
unset($a); unset(
    $b
);
unset($a, $b,
  $c); unset($d);
unset($a); unset($b) ?>
x
<?php unset($a); unset($b);
