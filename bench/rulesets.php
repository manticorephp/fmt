<?php

declare(strict_types=1);

// usage: php bench/rulesets.php  -- one --rules spec per line: every implemented rule alone (default), plus every distinct
// option set any upstream preset resolves for it (as {"rule": options}). check.sh runs them all, so no rule or preset option goes unchecked.

require __DIR__.'/lib.php';
require FORK.'/bench/work/upstream/vendor/autoload.php';

$have = array_filter(explode("\n", shell_exec(__DIR__.'/../bin/manticore-fmt list-rules --implemented')));
$seen = [];
foreach ($have as $r) {
    if ('global_namespace_import' === $r) {
        continue; // its default imports (import_* = true): not implemented, the file is reported unsupported; the presets' false sets run below
    }
    $seen[$r] = true;
    echo $r, "\n";
}
foreach ((new PhpCsFixer\RuleSet\RuleSets())::getSetDefinitions() as $name => $def) {
    if (str_starts_with($name, "@auto")) {
        continue;
    }
    foreach ((new PhpCsFixer\RuleSet\RuleSet([$name => true]))->getRules() as $rule => $opt) {
        if (!in_array($rule, $have, true) || true === $opt) {
            continue;
        }
        $j = json_encode([$rule => $opt], JSON_UNESCAPED_SLASHES);
        if (!isset($seen[$j])) {
            $seen[$j] = true;
            echo $j, "\n";
        }
    }
}
