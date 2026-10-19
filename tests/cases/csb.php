<?php
if ($a) echo 1;
if ($a)
    echo 1;
else
    echo 2;
if ($a) echo 1; elseif ($b) echo 2; else echo 3;
while ($a) $a--;
for ($i = 0; $i < 3; $i++) foo($i);
foreach ($a as $b) if ($b) bar(); else baz();
do $a++; while ($a < 3);
if ($a) if ($b) x(); else y();
if ($a) foreach ($b as $c): echo $c; endforeach;
if ($a) { b(); }
if ($a): b(); endif;
if ($a) $f = function () { return 1; };
if ($a) /* c */ echo 1; // d
if ($a) try { x(); } catch (E $e) { y(); } finally { z(); }
if ($a) switch ($b) { case 1: break; }
declare(ticks=1) x();
if ($a) while ($b) $b--;
if ($a) match ($b) { 1 => 2 };
