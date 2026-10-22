<?php
function a(): iterable { return [1, 2]; }
function b(): iterable { return array(1, 2); }
function c(): iterable { return [1, 2];; }
function d(): ITERABLE { return [1, 2]; }
function e(): array { return [1, 2]; }
function f(): iterable { $a = 1; return [$a]; }
function g(): iterable {
    if ($x) {
        return [1];
    }
    return [2];
}
function h(): ?iterable { return [1]; }
function i(): iterable { return [1] + [2]; }
function j() { return [1, 2]; }
function k(): iterable { return foo(); }
function l(): iterable { return [1]; /* c */ }
$f = function (): iterable { return [1, 2]; };
$f = fn(): iterable => [1];
class A { public function m(): iterable { return [new B()]; } }
function n(): iterable { return [[1], [2]]; }
function o(): iterable { return []; }
