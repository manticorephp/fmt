<?php

declare(strict_types=1);

// usage: php -d xdebug.mode=off bench/oracle.php <corpus-dir> <rules>
// Runs upstream php-cs-fixer under Zend and bin/manticore-fmt, each on its own COPY of the corpus (real `fix`, not
// dry-run), then compares every resulting file byte for byte and the set of changed files.
// Prints `IDENTICAL: N changed files` and exits 0, or the first differing file and exits 1.

require __DIR__.'/lib.php';

[$self, $corpus, $rules] = $argv + [null, null, null];
if (null === $corpus || null === $rules || false === realpath($corpus)) {
    fwrite(STDERR, "usage: php bench/oracle.php <corpus-dir> <rules>\n");
    exit(2);
}
$corpus = realpath($corpus);
if (!is_file(ZEND_FIXER)) {
    fwrite(STDERR, "upstream worktree missing: ".ZEND_FIXER."\n");
    exit(3);
}
$native = getenv('FMT_BIN') ?: __DIR__.'/../bin/manticore-fmt';

$tmp = sys_get_temp_dir().'/mfmt-oracle-'.getmypid();
$zendDir = $tmp.'/zend';
$natDir = $tmp.'/native';
$same = false !== getenv('ORACLE_SAMEDIR'); // both fixers run in the SAME directory, one after the other (path-dependent rules: psr_autoloading with `dir`)
if ($same) {
    $work = $tmp.'/w';
    copyTree($corpus, $work);
} else {
    copyTree($corpus, $zendDir);
    copyTree($corpus, $natDir);
}

function run(array $cmd): int
{
    // cwd = temp dir so no .php-cs-fixer.dist.php is auto-loaded
    $p = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, sys_get_temp_dir());

    return proc_close($p);
}

// ORACLE_CONFIG=<json file> (native format: rules, indent, lineEnding, risky): native reads it as it is, upstream gets an equivalent
// PHP config written to the temp dir; rules `-` means "the config's own"
$cfg = getenv('ORACLE_CONFIG') ?: null;
$cfgNative = [];
$cfgZend = [];
if (null !== $cfg) {
    $j = json_decode(file_get_contents($cfg), true, 512, JSON_THROW_ON_ERROR);
    @mkdir($tmp, 0777, true);
    $php = "<?php\nreturn (new PhpCsFixer\\Config())->setRules(".var_export($j['rules'] ?? ['@PSR12' => true], true).')'
        .'->setIndent('.var_export($j['indent'] ?? '    ', true).')->setLineEnding('.var_export($j['lineEnding'] ?? "\n", true).')'
        .'->setRiskyAllowed('.var_export($j['risky'] ?? false, true).")->setUsingCache(false);\n";
    file_put_contents($tmp.'/zend-config.php', $php);
    $cfgNative = ['--config='.realpath($cfg)];
    $cfgZend = ['--config='.$tmp.'/zend-config.php'];
}
if ($same) {
    $rules = str_replace('@WORK@', $work, $rules); // ORACLE_SAMEDIR: `@WORK@` in the rules is the directory both fixers run in
}
$rulesArg = '-' === $rules ? [] : ['--rules='.$rules];
$zc = run(array_merge(['php', '-d', 'xdebug.mode=off', '-d', 'memory_limit=-1', ZEND_FIXER, 'fix', '--using-cache=no', '--sequential', '--allow-risky=yes'], $cfgZend, $rulesArg, [$same ? $work : $zendDir]));
if ($same) {
    rename($work, $zendDir);
    copyTree($corpus, $work);
}
$nc = run(array_merge([$native, 'fix', '--no-cache', '--allow-risky=yes'], $cfgNative, $rulesArg, [$same ? $work : $natDir]));
if ($same) {
    rename($work, $natDir);
}
if (!in_array($zc, [0, 8, 64, 72], true) || !in_array($nc, [0, 64], true)) { // 64 (+8): upstream left a file whose result did not lint; the per-file comparison still applies
    fwrite(STDERR, "unexpected exit: zend=$zc native=$nc\n");
    rmTree($tmp);
    exit(3);
}

$changed = 0;
$bad = null;
foreach (phpFiles($corpus) as $rel) {
    $orig = file_get_contents($corpus.'/'.$rel);
    $z = file_get_contents($zendDir.'/'.$rel);
    $n = file_get_contents($natDir.'/'.$rel);
    if ($z !== $orig) {
        ++$changed;
    }
    if ($z !== $n && null === $bad) {
        $bad = [$rel, $orig, $z, $n];
    }
}
if (null === $bad) {
    echo "IDENTICAL: $changed changed files\n";
    rmTree($tmp);
    exit(0);
}
[$rel, $orig, $z, $n] = $bad;
echo "DIFF at $rel (zend ".($z === $orig ? 'unchanged' : 'changed').', native '.($n === $orig ? 'unchanged' : 'changed').")\n";
$at = 0;
$max = min(strlen($z), strlen($n));
while ($at < $max && $z[$at] === $n[$at]) {
    ++$at;
}
$from = max(0, $at - 40);
echo "first differing byte $at (of zend ".strlen($z).', native '.strlen($n).")\n  zend  : ".json_encode(substr($z, $from, 90))."\n  native: ".json_encode(substr($n, $from, 90))."\n";
echo "kept: $tmp\n";
exit(1);
