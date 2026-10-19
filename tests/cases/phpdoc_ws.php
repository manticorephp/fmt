<?php /** after open tag
 * x
       */
class A
{
/**
   * Bad indent.
     *
  * @var int
*/
    public $a;

        /**
         * Over-indented.
         */

    public $b;
    public function f() {
        $x = 1; /** same line
         */
        foo(/** arg */ 1);
        /* c *//**
          * after a comment
            */
        $y = 2;
	/**
	 * tab
	 */
        return $x;
    }
    /**
     * Doc before closing brace.
     */
}
/** a */

namespace B;
/**
 * @see x
 */

use C;
/**
 * blank after
 */


function g() {}
/** b */


// comment
function h() {}
