<?php
class A
{
    public function f($x)
    {
        $a = <<<EOT
        hello
          indented
        $x here
        EOT;
        $b = <<<'EOT'
            nowdoc
              more
            EOT;
        $c = <<<EOT
    flush
  EOT;
        $d = [<<<X
 a
 X, <<<Y
        b
        Y];
        $e = <<<EOT
        {$x}
          {$x
        }
        EOT . 'x';
        $f = <<<EOT
        EOT;
        $g = <<<EOT

        blank first

        EOT;
        return <<<TXT
$x start
	tab
TXT;
    }
}
$z = <<<EOT
at top
  nested
EOT;
foo(<<<EOT
    arg
    EOT, 1);
$t = <<<EOT
		tabs
		EOT;
$u = <<<EOT
a
b
EOT . <<<EOT
c
EOT;
