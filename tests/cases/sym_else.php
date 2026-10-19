<?php
function f($a, $b) {
    if ($a) {
        return 1;
    } else {
        foo();
    }
    if ($a) {
        throw new E();
    } elseif ($b) {
        return 2;
    } else {
        bar();
    }
    if ($a) return 1; else foo();
    if ($a) return 1; else { foo(); }
    if ($a) { return 1; } else return foo();
    if ($a) { foo(); } else { bar(); }
    if ($a) { foo(); } else { }
    if ($a) { foo(); } else {}
    if ($a) { foo(); } else { /* c */ }
    if ($a) { foo(); } else ;
    if ($a) { return; } else if ($b) { foo(); }
    if ($a) { return; } else { function q() {} }
    if ($a) { return; } else { class Q {} }
    if ($a) { return; } else { $f = function () {}; $o = new class {}; }
    if ($a) { return; } else { if ($b) { foo(); } }
    if ($a) { if ($b) { return; } } else { foo(); }
    if ($a) { if ($b) { return; } else { return; } } else { foo(); }
    if ($a) { foo(); return; } else { bar(); }
    if ($a) { return; foo(); } else { bar(); }
    if ($a) { exit; } else { bar(); }
    if ($a) { die(); } else { bar(); }
    if ($a) { goto x; } else { bar(); }
    if ($a) { throw $e; } else { bar(); }
    if ($a) { $x = fn() => throw $e; } else { bar(); }
    if ($a) { foo() or throw $e; } else { bar(); }
    if ($a) { while ($x) { break; } } else { bar(); }
    foreach ($l as $m) { if ($a) { continue; } else { bar(); } }
    foreach ($l as $m) { if ($a) { break; } elseif ($b) { continue 2; } else { bar(); } }
    if ($a): return; else: bar(); endif;
    if ($a) { return; } // c
    else { bar(); }
    if ($a) { return; } else /* c */ { bar(); }
    if ($a) { return; }
    else
    {
        bar();
    }
    if ($a) {
        return;
    } else {
        // c
    }
    if ($a) return; elseif ($b) return; else foo();
    if ($a) { return; } elseif ($b) { foo(); } else { bar(); }
    if ($a) { return; } else if ($b) { return; } else { bar(); }
    switch ($a) { case 1: if ($b) { return; } else { foo(); } }
    for (;;) if ($a) { return; } else { foo(); }
    for (;;) { if ($a) { return; } else { foo(); } }
    do { if ($a) { return; } else { foo(); } } while ($x);
    while ($x) if ($a) return; else foo();
}
?>
<?php if ($a) { return; } else { bar(); } ?>
