<?php
/**
 * @method static int  foo(int $a) Foo it.
 * @method string bar() Bar.
 *    continued here
 * @method static baz()
 * @property-read int $p the p
 * @property string $q
 */
class A
{
    /**
     * @param int|string $a   first
     *   continued
     * @param array<int, string> ...$b second
     * @param   &$c no hint
     * @param ünïcödé $d multibyte hint
     * @return  static   self
     * @throws \RuntimeException   when
     *                             it fails
     * @custom value
     *
     * @var int
     * @type string desc
     */
    public function f($a, ...$b) {}
	/**
	 * @param int $a tab indent
	 * @param string $bb
	 */
	public function g() {}
}
