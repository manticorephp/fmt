<?php
$a = isset($a) && isset($b);
$a = isset($a) && isset($b) && isset($c);
$a = isset($a)&&isset($b);
$a = isset( $a ) && isset( $b , $c ) && isset($d['x']);
$a = isset($a) && isset($b) || isset($c);
$a = isset($a) && $x && isset($b);
$a = isset($a) && isset($b) && $x;
$a = isset($a) && isset($b) && isset($c) && $d;
$a = isset($a) && isset($b->c[1]);
if (isset($a) && isset($b)) { return; }
if (isset($a) /* c */ && /* d */ isset($b)) { return; }
if (isset($a)
    && isset($b)
    && isset($c)) {
    echo 1;
}
if (isset($a) &&
    isset($b)) {
}
$x = foo(isset($a) && isset($b), isset($c) && isset($d));
$x = [isset($a) && isset($b)];
$x = isset($a) && isset($b) ? 1 : 2;
$x = !isset($a) && isset($b);
$x = true && isset($a) && isset($b);
$x = isset($a) and isset($b);
$x = isset($a, $b) && isset($c, $d);
$x = isset($a) && (isset($b) && isset($c));
$x = (isset($a) && isset($b)) && isset($c);
$x = isset($a[isset($b) && isset($c) ? 1 : 2]) && isset($d);
$x = isset($a) && isset($b)
;
$x = isset($a) && isset($b) ?>
<?php
$y = isset($a)
    && isset($b) // trailing
    && isset($c);
$y = isset($a) /* x */ && isset($b)/* y */ && isset($c);
$y = isset($a) && isset($b) && isset($c) && isset($d) && isset($e);
