<?php
namespace App;

/**
 * @ORM\Entity(repositoryClass = "Repo" , readOnly=true)
 * @Foo
 * @Bar()
 * @Baz( )
 * @Route("/x", name = "y",methods={"GET" , "POST"})
 * @Assert\Choice(choices={"a" = "b", "c":"d" , 'e'= 1}, strict = true)
 * @var int
 * @param string $x
 * @Nested(a=@Inner(b=1,c=@Deep()), d={@X, @Y(1)})
 * @Multi(
 *     a = 1,
 *   b= {
 *         "x" = 2,
 *     "y": 3
 *   },
 *       c = @Sub(
 *   d=4
 * )
 * )
 * @Trailing(a=1,
 *  b=2)
 * @Broken(a=1
 */
class Entity
{
    /**
     * @ORM\Column(type="string",length=255,nullable = true)
     * @ORM\JoinColumn( name="id" , referencedColumnName ="id" )
     * @Mixed(a=1,
     *   b=2) tail text
     * @Inline(1,2 , 3)@Adjacent("x")
     */
    private $a;

    /**
     * @Assert\NotBlank
     * @Assert\Length(max=10)
     */
    public const C = 1;

    /**
     * @Annot( "str with ""quote""" , 12 , 1.5e3, -4, Foo\Bar::BAZ, true )
     */
    public function m() {}

    /** @Foo(a = 1) */
    public static $static;

    /** @Foo(a = 1) */
    public readonly int $ro;

    /** @Foo(a = 1) */
    final public ?string $nullable = null;
}

/** @Foo(a = 1) */
interface I { /** @Foo(a = 1) */ public function m(); }

/** @Foo(a = 1) */
trait T { /** @Foo(a = 1) */ public $p; }

/** @Foo(a = 1) */
enum E { /** @Foo(a = 1) */ case A; }

/** @Foo(a = 1) */
function f() {}

/** @Foo(a = 1) */
abstract class Abs { /** @Foo(a = 1) */ abstract protected function g(); }

/** @Foo(a = 1) */
final class Fin {}

/** @Foo(a = 1) */
final readonly class Ro {}

/**
 * @inheritdoc
 * @SuppressWarnings(PHPMD)
 * @Custom( x = 1 )
 */
class Ign {}

/**
 * Text @NotAnnotation and email a@b.c and @Spaced(a=1) end.
 * @@Double
 */
class Txt {}
