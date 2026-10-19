<?php
function g() {
    yield ($a);
    yield from ($a);
    yield ($a) => ($b);
    $x = yield ($a);
    return (yield ($a));
    print ($a);
    return ($a);
    echo ($a);
}
function h($a) {
    return ((int) $a);
    return (int) ($a);
    return ($a)
        ? 1 : 2;
    switch ($a) {
        case ($a): return ($b);
        case ($a + 1):
        case (1): break (1);
        default: continue (1);
    }
}
