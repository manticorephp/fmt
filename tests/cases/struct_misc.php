<?php
/**
	 * Doc with tabs
	 */
function foo(
    int $a,
    string $b,
): string {
    $s = <<<EOT
    heredoc {$a}
      body
    EOT;
    $t = <<<'NOW'
	nowdoc
	NOW;
    return $s
        . $t
        . 'x';
}
	/* block
	   comment */
function bar() { $x = 1;
    return $x; }
function baz()
{
}
function qux() {}
$a = 1; $b = 2;
$c = $a
    + $b
       - 3;
$d = $obj
    ->method()
        ->other();
?>
<div><?php if ($x) { ?>
  <p>a</p>
<?php } ?></div>
<?php
  $e = 1;
	$f = 2;
class P {
    public int $hooked {
        get => $this->hooked;
        set { $this->hooked = $value; }
    }
}
list($a, $b) = [1, 2];
[$c, [$d, $e]] = $arr;
$x = ${'a'};
$y = $o->{'b'};
echo "{$a} ${b}";
