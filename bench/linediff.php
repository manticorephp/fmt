<?php
// usage: REAL_BIN=bin/manticore-fmt php bench/linediff.php <case.php> <rules> ; one file per line after the header (lines 1..N before the first statement are kept as header), reports lines where zend and native differ
[$self, $case, $rules] = $argv;
putenv('REAL_BIN='.(getenv('REAL_BIN') ?: dirname(__DIR__).'/bin/manticore-fmt'));
$lines = file($case, FILE_IGNORE_NEW_LINES);
$dir = '/tmp/ld/z'; $dirn = '/tmp/ld/n';
foreach ([$dir, $dirn] as $d) { exec('rm -rf '.$d); mkdir($d, 0777, true); }
$hdr = "<?php\n\nnamespace Foo;\n\n";
foreach ($lines as $i => $l) {
    if ($i < 4 || trim($l) === '') continue;
    $f = sprintf('%03d.php', $i + 1);
    file_put_contents("$dir/$f", $hdr.$l."\n");
    file_put_contents("$dirn/$f", $hdr.$l."\n");
}
exec('php -d xdebug.mode=off -d memory_limit=-1 '.escapeshellarg(UPSTREAM.'/php-cs-fixer').' fix --using-cache=no --sequential --allow-risky=yes --rules='.escapeshellarg($rules).' '.$dir.' >/dev/null 2>&1');
exec(escapeshellarg(__DIR__.'/gfmt.sh').' fix --no-cache --allow-risky=yes --rules='.escapeshellarg($rules).' '.$dirn.' >/dev/null 2>&1');
foreach (glob("$dir/*.php") as $f) {
    $b = basename($f);
    $z = file_get_contents($f); $n = file_get_contents("$dirn/$b");
    if ($z !== $n) { echo "$b\n  line : ".$lines[(int) $b - 1]."\n  zend : ".str_replace("\n", '\n', substr($z, strlen($hdr)))."\n  nat  : ".str_replace("\n", '\n', substr($n, strlen($hdr)))."\n"; }
}
exec('rm -rf /tmp/ld');
