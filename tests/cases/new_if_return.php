<?php
function a($x) {
    if ($x) {
        return true;
    }
    return false;
}
function b($x) {
    if ($x) return false;
    return true;
}
function c($x) {
    if ($x) {
        return true;
    } else {
        return false;
    }
}
function d($x) {
    if ($x > 1) { return true; } return false;
}
function e($x) {
    if ($x) {
        return true;
    }
    return true;
}
function f($x) {
    if ($x) {
        return TRUE;
    }
    return false;
}
function g($x) {
    if ($x) {
        // c
        return true;
    }
    // d
    return false;
}
function h($x) {
    if ($x) { return true; } elseif ($y) { return false; }
    return true;
}
function i($x) {
    while ($x) if ($y) { return true; }
    return false;
}
function j($x) {
    if ($x) return true;
    return false;
}
function k($x) {
    if (foo($x)) {
        return false;
    }

    return true;
}
function l($x) {
    if ($x) {
        return true;
    }
    return false ?>
<?php }
function m($x) {
    if ($x) :
        return true;
    endif;
    return false;
}
function n($x) {
    if ($a) {
        if ($b) {
            return true;
        }
        return false;
    }
    return false;
}
function o($x) {
    if ($x) { return true; }
    return false; // trailing
}
