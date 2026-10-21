<?php
use Bar\DateTime;
use Other\Thing as DateTimeImmutable;
$a = DateTime::createFromFormat("Y", $s) + new DateTime();
