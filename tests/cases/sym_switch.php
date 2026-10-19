<?php
switch ($a) {
    case 1:
        continue;
    case 2:
        continue 1;
    case 3:
        continue 0;
    case 4:
        foreach ($b as $c) {
            continue;
            continue 1;
            continue 2;
            switch ($d) { case 1: continue 2; case 2: continue 3; case 3: continue; }
        }
        continue 2;
    default:
        while ($x) { continue 2; }
}
foreach ($a as $b) {
    switch ($b) {
        case 1: continue 2;
        case 2: continue;
        case 3: continue 1;
        case 4: if ($x) { continue 2; } break;
    }
    do { switch ($a) { case 1: continue 3; } } while ($c);
    continue;
}
for ($i = 0; $i < 2; $i++) switch ($i) { case 1: continue 2; }
switch ($a) { case 1: continue 0x1; case 2: continue 0b1; case 3: continue 01; case 4: continue 1_0; case 5: continue 0x2; }
