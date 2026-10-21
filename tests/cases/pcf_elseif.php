<?php
if ($a) {
    return 1;
} elseif ($b) {
    return 2;
} else {
    return 3;
}
if ($a) {
    return 1;
} else if ($b) {
    return 2;
}
if ($a) {
    echo 1;
} elseif ($b) {
    return 2;
}
if ($a) {
    throw new E();
} elseif ($b) {
    exit;
} elseif ($c) {
    exit(1);
} elseif ($d) {
    goto x;
} else {
}
x:
if ($a) return 1; elseif ($b) return 2;
if ($a) return 1; else if ($b) return 2;
if ($a) {
    return 1;
}
elseif ($b) {
    return 2;
}
if ($a) {
    return 1;
} /* c */ elseif ($b) {
    return 2;
}
if ($a) {
    return 1;
} // c
else if ($b) {
    return 2;
}
if ($a) {
    return 1;
} else  if ($b) {
    return 2;
} else
if ($c) {
    return 3;
}
if ($a) {
    if ($x) { return 1; }
} elseif ($b) {
    return 2;
}
if ($a) {
    return 1;
} elseif ($b) {
    echo 1;
} elseif ($c) {
    return 3;
}
function f() {
    if ($a) {
        return 1;
    } elseif ($b) {
        return 2;
    } else if ($c) {
        return 3;
    }
}
if ($a):
    return 1;
elseif ($b):
    return 2;
endif;
if ($a) { return 1; } elseif ($b) { return 2; } elseif ($c) { return 3; } elseif ($d) { return 4; }
if ($a) { if ($b) { return; } } elseif ($c) { return; }
if ($a) { foreach ($x as $y) return 1; } elseif ($c) { return 2; }
if ($a) { return 1;} else if ($b) {return 2;}
   if ($a) {
       return 1;
   } else if ($b) {
       return 2;
   }
if ($a) {
    return 1;
} else {
    if ($b) { return 2; }
}
if ($a) {
    return 1;
} elseif ($b) { return 2; } else if ($c) { return 3; }
foreach ($l as $m) {
    if ($a) {
        continue;
    } elseif ($b) {
        break;
    } else if ($c) {
        continue 1;
    }
}
