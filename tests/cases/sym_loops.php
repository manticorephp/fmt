<?php
for (;;) { foo(); }
for ( ; ; ) { foo(); }
for (;;)
{
    foo();
}
for (/* c */;;) {}
for (;; /* c */) {}
for (;;) foo();
for ($i = 0;;) {}
for (;$i < 2;) {}
for (;;$i++) {}
while (true) { foo(); }
while ( true ) { foo(); }
while (TRUE) {}
while (true) foo();
do { foo(); } while (true);
do { foo(); } while ( true ) ;
do { foo(); } while (/* c */ true);
do {
    foo();
} while (true);
if ($a) { for (;;) { break; } }
function f() {
    for (;;) {
        while (true) {
            break;
        }
    }
    do {
        for (;;) {}
    } while (true);
}
$a = 1; for (;;) {}
