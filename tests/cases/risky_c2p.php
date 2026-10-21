<?php

// header comment @var
declare(strict_types=1);

// @var int
$a = 1;

/* @var string $b */
$b = foo();

// @var $c
function f() {}

# @param int $x
# @return void
function g($x) {}

    // @var Foo
    // and more
    // @see x
    class A
    {
        // @var int
        public $p;

        // not an annotation
        public $q;

        // @ignore-me
        public $r;

        // @phpstan-ignore foo
        public $s;

        // @var
        const C = 1;

        /* @return int */
        public function m() {
            // @var Foo $x
            foreach ($items as $x) {}
            // @var Foo $y
            if ($y) {}
            // @var Foo $z
            $z = 1;
            // @var Foo $w
            echo 1;
            // @var Foo
            return 1;
        }
    }
// @var int
// @var float
$d = 1;
//@var int
$e = 1; //@var trailing
/** @var int */
$f = 1;
/* multi
 * line @var x */
$g = 1;
$h = 1; // @var after
// @custom ignored
function h() {}
#[Attr]
// @var int
function i() {}
// @var x
