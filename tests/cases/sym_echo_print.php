<?php
print 'a';
print('b');
$x = print 'c';
if ($a) print 'd';
if ($a) { print 'e'; } else print 'f';
foo() and print 'g';
echo 'a';
echo 'a', 'b';
echo foo(1, 2);
echo [1, 2][0];
echo (1), 2;
echo 'x' ?>
<?= $a ?>
<?=$a?>
<?= 'x' ?>
<?= /* c */ 'x' ?>
<?=
$a
?>
text
<?php print 'z' ?>
<?php if ($a): ?>
x<?php echo 1 ?>
<?php endif ?>
<?php foo() ?>
<?php foo(); ?>
<?php foo() ?>
<?php { foo(); } ?>
<?php foo() /* c */ ?>
