<?php

call_user_func("var_dump", 1, 2);
call_user_func("Bar\\Baz::d", 1, 2);
call_user_func_array($callback, [1, 2]);
call_user_func(function ($a, $b) { var_dump($a, $b); }, 1, 2);
call_user_func(static function ($a, $b) { var_dump($a, $b); }, 1, 2);
call_user_func($f);
call_user_func($f, $f);
call_user_func($a['x'], 1);
call_user_func($a->b, 1);
call_user_func($a->b(), 1);
call_user_func([$a, 'b'], 1);
call_user_func('foo' . 'bar', 1);
call_user_func(' foo', 1);
call_user_func(b'foo', 1);
call_user_func('x');
\call_user_func_array('foo', $a);
call_user_func_array("A::b", ['x' => 1],);
call_user_func( /* c */ $f /* d */ , /* e */ 1 );
call_user_func($$f, 1);
call_user_func($f[0][1], 1);
CALL_USER_FUNC('a', 1);
$x->call_user_func($f, 1);
call_user_func();
call_user_func($f, 1);
