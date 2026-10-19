<?php
switch ($a) {
    case 1:
        foo();
    case 2:
        bar();
        // no break
    case 3:
        baz();
        break;
    case 4:
        qux();
    // NO BREAK
    case 5:
        return;
    case 6:
        throw new E();
    case 7:
    case 8:
        foo();
    /* no break */
    case 9:
        foo();
    /** no break */
    case 10:
        foo();
    # no break
    default:
        foo();
}
switch ($b) {
    case 1:
        if ($x) {
            break;
        }
    case 2:
        foreach ($y as $z) {
            continue 2;
        }
    case 3:
        while (true) { break; }
    case 4:
        $f = function () { return 1; };
    case 5:
        $m = match ($x) { default => 1, };
    case 6:
        foo();
        // no break because of
    case 7:
        foo();
        // no break

    case 8:
        foo(); // no break
    case 9:
        foo(); /* no break */
    case 10:
        foo();
        /*
         * no break
         */
    case 11:
        do { foo(); } while ($x);
    case 12:
        if ($a) { foo(); } else { return; }
    case 13:
        if ($a) { foo(); } else if ($b) { return; }
    default:
        break;
}
switch ($c) {
    case 1:
        foo();
    default:
        // no break
        bar();
}
switch ($d) {
    case 1: foo();
    case 2: bar();
    default: baz();
}
switch ($e) {
    case 1:
        foo();

    case 2:
        bar();


    default:
        baz();
}
switch ($f) {
    case 1:
        foo();   // trailing
    case 2:
        bar();
        /* a */ /* no break */
    case 3:
        baz();
}
enum E { case A; case B; }
switch ($g) {
    case $a ? 1 : 2:
        foo();
    case 3;
        foo();
    case 4:
        throw new X();
    case 5:
        foo(throw new X());
    case 6:
        exit(1);
    case 7:
        goto end;
    case 8:
        foo();
        // no break
}
end:
if ($x): switch ($y): case 1: foo(); case 2: bar(); endswitch; endif;
switch ($z) {
    case 1:
        foo();
    case 2:
        switch ($w) { case 1: foo(); case 2: bar(); }
    default:
        baz();
}
