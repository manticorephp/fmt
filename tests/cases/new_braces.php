<?php
namespace A;

abstract class A
{
    public function f($a,
        $b) {
        if ($a) echo 1;
        else {
            echo 2;
        }
        foreach ($b as $x)
        {
            echo $x;
        }
        $f = function($q) use ($a,
              $b) { return 1; };
        $g = function () { return 2; };
        try { x(); } catch (\E $e) { y(); }
        finally { z(); }
        while ($a) $a--;
        do { $a++; } while ($a < 3);
        for ($i = 0; $i < 3; $i++) echo $i;
        switch ($a) { case 1: echo 1; break; default: echo 2; }
    }
    // comment
    /* block
       comment */
    function g(
            $x,
        $y = [1,
              2]
    ) {}

    public function h()
    {   // after brace
        return 1;
    }

    abstract function i();
    function j() { }
}
function h() {
    if ($a) { echo 1; } elseif ($b) { echo 2; } else { echo 3; }
    // trailing
        // odd
    while (1):
    endwhile;
    $x = new class { public $a; };
    $y = new class
    {
    };
    declare(ticks=1) { echo 1; }
}
interface I { function a(); }
trait T { function b() { return 1; } }
enum E: int { case A = 1; public function c() { return 2; } }
