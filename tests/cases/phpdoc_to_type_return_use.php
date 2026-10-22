<?php

$a = 1;

/** @return int */
function one()
{
}

$b = /** @return int */ function () use ($a) {
};
