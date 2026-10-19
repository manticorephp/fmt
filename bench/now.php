<?php

declare(strict_types=1);

// usage: php bench/now.php <@PSR12|@PER-CS2.0|@PER-CS3.0|@Symfony> [extra-json]
// Prints the --rules JSON for the part of the preset that bin/manticore-fmt implements (upstream's resolved options), so the
// oracle can run the whole implemented slice fused. `extra-json` entries are merged over it.

require __DIR__.'/lib.php';
require FORK.'/bench/work/upstream/vendor/autoload.php';

[$self, $set, $extra] = $argv + [null, '@PSR12', '{}'];
$have = array_filter(explode("\n", shell_exec(__DIR__.'/../bin/manticore-fmt list-rules')));
$rules = (new PhpCsFixer\RuleSet\RuleSet([$set => true]))->getRules();
$out = [];
foreach ($rules as $name => $opt) {
    if (in_array($name, $have, true)) {
        $out[$name] = true === $opt ? true : $opt;
    }
}
foreach (json_decode($extra, true) as $k => $v) {
    $out[$k] = $v;
}
echo json_encode($out, JSON_UNESCAPED_SLASHES);
