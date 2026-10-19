<?php
/** @package Foo */
/**
 * @package Foo
 * @subpackage Bar
 */
/**
 * Summary.
 *
 * @access private
 * @ACCESS public
 * @access
 * @accessor keep
 * @param int $a the
 *               long description
 *
 * @package  Foo \Bar
 */
class A
{
    /** @access public */ public $x;
    /**   @access public
     */
    public $y; /** @access private */
    /**
     * @access protected */
    public function f() { /**
     @access */ }
}
/**
 * {@inheritdoc}
 * @inheritDocs
 * {@inheritDocs} @link http://x y
 * @{inheritdoc} { @link x }
 * {{@internal some text }}
 * @property-read int $p "@type" '@link'
 * @type int @var
 */
function g() {}
/** @const X */
/**@link x*/
/**	@see x */
