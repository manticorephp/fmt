<?php
$a = (unset) $b;
$a = (unset)$b;
$a =(unset) $b;
$a =(unset)$b;
$a = (unset) $b ;
$a = (unset)
  $b;
$a = /* c */ (unset) $b;
$a = (unset) /* c */ $b;
$a = (unset) $b /* c */;
$a =
  (unset) $b;
$a = (unset) $b ?>
$a = (unset) $b->c;
$a = (unset) $b['x'];
foo((unset) $b);
$a = (unset) foo();
$c = $a = (unset) $b;
$a[1] = (unset) $b;
$a .= (unset) $b;
(unset) $b;
