<?php
namespace A;

/** Class doc. */
class C
{
    use T; /** trait import */
    /** @var int */
    public int $a;

    /**
     * Multi.
     */
    protected $b;

    /** const doc */
    const X = 1;

    /**
     * Const multi.
     */
    public const Y = 2;

    /** Method */
    public function m() {}

    /**
     * Method multi.
     */
    private function n() {}

    /** @param int $x */
    #[Attr]
    final public static function o(int $x, int $p = 1) {}

    /** promoted */
    public function __construct(
        /** @var int */
        public readonly int $q,
        /**
         * multi promoted
         */
        private $r = 1,
    ) {}

    /** @var string */
    // comment between
    public ?string $s;

    /** doc */
    public function typed(): ?static {}
}

/**
 * Interface multi.
 */
interface I {}

/** Trait */
trait T {}

/** Enum */
enum E {
    /** case */
    case A;
    /**
     * case multi
     */
    case B;
}

/**
 * Function.
 */
function f() {}

/** closure doc */
$f = function () {};

/** lone */

/**
 * other multi
 */
$x = 1;

/** x */ $y = 2;

/** @var int $z */
$z = 3;

/** */
class Empty1 {}

/**
 */
class Empty2 {}

/** @return void */
abstract class Abs {
    /** abstract */
    abstract protected function g();
}
