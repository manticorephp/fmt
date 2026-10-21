<?php

declare(strict_types=1);

// usage: php bench/mutate_phpunit.php <phpunit-tests-dir> <out-dir>
// PHPUnit dirt for the php_unit_* rules: drops `@internal` / `@covers*` annotation lines and Covers* attributes, empties or removes some class
// docblocks, joins others to one line. (A copy of upstream's own tests moved with placement=before is the other input: `bench/rule.sh` on it with
// the default placement exercises php_unit_data_provider_method_order the other way.)

require __DIR__.'/lib.php';

[$self, $in, $out] = $argv + [null, null, null];
if (null === $in || null === $out || false === realpath($in)) {
    fwrite(STDERR, "usage: php bench/mutate_phpunit.php <phpunit-tests-dir> <out-dir>\n");
    exit(2);
}
rmTree($out);
$n = 0;
foreach (phpFiles(realpath($in)) as $rel) {
    $c = file_get_contents($in.'/'.$rel);
    ++$n;
    $c = preg_replace('/^[ \t]*\*[ \t]*@(?:internal|covers\w*)\b[^\n]*\n/m', '', $c) ?? $c;
    $c = preg_replace('/^[ \t]*#\[(?:\\\\?PHPUnit\\\\Framework\\\\Attributes\\\\)?Covers\w*\([^\n]*\)\]\n/m', '', $c) ?? $c;
    if (0 === $n % 3) {
        // drop the docblock of every class
        $c = preg_replace('/\n\/\*\*(?:(?!\*\/).)*?\*\/\n((?:final |abstract |readonly )*class )/s', "\n$1", $c) ?? $c;
    } elseif (1 === $n % 3) {
        // a one-line docblock of every class
        $c = preg_replace('/\n\/\*\*\n(?: \*[^\n]*\n)*? \*\/\n((?:final |abstract |readonly )*class )/', "\n/** Summary. */\n$1", $c) ?? $c;
    }
    @mkdir(dirname($out.'/'.$rel), 0777, true);
    file_put_contents($out.'/'.$rel, $c);
}
echo "$n files\n";
