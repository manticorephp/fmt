<?php
use Foo\Bar, Foo\Baz;
use Foo\{
    One,
    Two,
};
use Foo\Three; /* keep */
use Foo\Four;

new Bar(); new Two();
label: goto label;
