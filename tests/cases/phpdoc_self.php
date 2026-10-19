<?php
class A
{
    /** @return this */
    public function a() {}
    /**
     * @return @this|null
     */
    final public static function b() {}
    /**
     * @return $SELF
     * @return this
     */
    abstract protected function c();
    /** @return @static */
    #[Attr]
    public function d() {}
    /** @return $static[] */
    private function e() {}
    /** @return static|$self|@Self */
    function f() {}
}
trait T
{
    /** @return this */
    public function g() {}
}
/** @return this */
function h() {}
$o = new class {
    /** @return @this */
    public function i() {}
};
