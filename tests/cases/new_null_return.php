<?php
function a() { return null; }
function b() { return NULL; }
function c(): ?int { return null; }
function d(): int|null { return null; }
function e(): void { return; }
function f(): mixed { return null; }
function h() { return (null); }
function i() { return ((null)); }
function j() { return /* c */ null; }
function k() { return null /* c */; }
function l() { return
    null; }
function m() { return null ?>x<?php }
$fn = function () { return null; };
$fn = function (): array { return null; };
$fn = function (): ?array { return null; };
$fn = fn() => null;
class K {
    public function a() { return null; }
    public function b(): iterable { return null; }
    public function c(): self { return null; }
    public function d(): NotVoid { return null; }
    public function e(): callable { return null; }
    public function f(): array { return null; }
    public function g() {
        $x = function (): int { return 1; };
        return null;
    }
    public function h(): int {
        $x = function () { return null; };
        return 1;
    }
    public ?int $p { get { return null; } set { $f = function () { return null; }; } }
    public int $q { get { $f = function () { return null; }; return 1; } }
}
return null;
