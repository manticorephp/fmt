<?php
if ($a):
    foo();
elseif ($b):
    bar();
else:
    baz();
endif;
foreach ($l as $x):
    echo $x;
endforeach;
while ($c):
    $c--;
endwhile;
for ($i = 0; $i < 2; $i++):
    foo();
endfor;
switch ($a):
    case 1:
        foo();
        break;
endswitch;
declare(ticks=1):
    foo();
enddeclare;
if($a):foo();else:bar();endif;
if ($a) : foo(); elseif($b): bar(); else : baz(); endif ;
if ($a): if ($b): foo(); endif; endif;
if ($a): elseif (b()): else: endif;
