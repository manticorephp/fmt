<?php
if ($a):
  if ($b) {
   x();
  } else {
     y();
  }
    elseif ($c):
 z();
else:
      w();
  endif;
switch ($x):
  case 1:
      a();
      break;
   default:
  b();
endswitch;
  if ($a) foo();
  else bar();
if ($a)
foo();
elseif ($b)
    bar();
else
        baz();
while ($x)
  $x--;
for (;;)
    // comment
    break;
do
  $i++;
while ($i < 3);
if ($a) {
    a();
        // comment for else
} else {
    b();
}
if ($a) {
    a();
        // comment for elseif
} elseif ($b) {
    b();
}
$f = function () {
      return [
     'a' => 1,
          'b' => [
        2,
       ],
    ];
};
$g = array(
  1,
      2
);
class Q
{
      #[A]
  #[B(
    1
  )]
    public function x(
  int $a,
            int $b
    ): void {
        $this->y(
        $a,
              $b
        );
    }
      public static int $p = 1;
  public
     static
   int $q = 2;
    const A = 1,
      B = 2;
}
$r = match ($x) {
  1 => 'a',
      2, 3 => 'b',
  default => 'c',
};
$s = $a
  ? $b
      : $c;
foo(function () {
  return 1;
}, [
  1,
]);
use A\{
  B,
      C
};
[
   $a,
     $b
] = $arr;
$t = <<<EOT
  heredoc
    stays
  EOT;
    goto end;
  end:
echo 1;
