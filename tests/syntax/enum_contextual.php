<?php
enum/* c */ Foo {}
enum /* c */ Bar: int {}
enum // c
Baz {}
class enum extends B {}
class enum implements C {}
class enum {}
$x = enum_exists(Foo::class);
