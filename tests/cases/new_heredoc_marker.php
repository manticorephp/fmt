<?php
$a = <<<EOD
text
EOD;
$b = <<<HTML
<p>x</p>
HTML;
$c = <<<"SQL"
select 1
SQL;
$d = <<<'JSON'
{}
JSON;
$e = <<<FOO
text
FOO;
$f = <<<html
x
html;
$g = <<<EOT
has EOD inside
 EOD
EOT;
$h = <<<EOT
fine
EOT;
$i = <<<HTML_
x
HTML_;
$j = <<<  A
x
A;
$k = <<<'MARK'
 EOD marker
MARK;
$l = <<<JS
EOD_
JS;
$m = <<<Ü
x
Ü;
$n = <<<php
x
php;
