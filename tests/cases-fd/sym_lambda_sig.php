<?php
$a = function () use ($x,) { return 1; };
$a = function () use ($x, $y,) { return 1; };
