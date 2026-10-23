<?php

declare(strict_types=1);

// usage: php -d xdebug.mode=off bench/presets.php [bin/manticore-fmt]
// Every built-in upstream set: RuleSet::getRules() under Zend vs `manticore-fmt resolve --rules=<set>`. Prints `OK n sets` or the first differences.

require __DIR__.'/lib.php';
require UPSTREAM.'/vendor/autoload.php';

use PhpCsFixer\RuleSet\RuleSet;
use PhpCsFixer\RuleSet\RuleSets;

$bin = $argv[1] ?? __DIR__.'/../bin/manticore-fmt';
error_reporting(E_ALL & ~E_DEPRECATED);
set_error_handler(static fn () => true);

function norm(mixed $v): mixed
{
    if (is_array($v)) {
        ksort($v);

        return array_map('norm', $v);
    }

    return $v;
}

$ok = 0;
$bad = 0;
foreach (RuleSets::getSetDefinitions() as $name => $def) {
    if ($def instanceof PhpCsFixer\RuleSet\AutomaticRuleSetDefinitionInterface) {
        continue;
    }
    $up = (new RuleSet([$name => true]))->getRules();
    ksort($up);
    $up = norm($up);
    $out = shell_exec(escapeshellarg($bin).' resolve --config=- --rules='.escapeshellarg($name).' 2>/dev/null');
    $nat = json_decode((string) $out, true);
    if (!is_array($nat)) {
        echo "FAIL $name: native printed no JSON\n";
        ++$bad;
        continue;
    }
    $nat = norm($nat);
    if ($nat === $up) {
        ++$ok;
        continue;
    }
    ++$bad;
    $d = array_diff_key($up, $nat) + array_diff_key($nat, $up);
    foreach ($up as $k => $v) {
        if (isset($nat[$k]) && $nat[$k] !== $v) {
            $d[$k] = ['upstream' => $v, 'native' => $nat[$k]];
        }
    }
    echo "FAIL $name: ".substr(json_encode($d), 0, 300)."\n";
}
echo $bad ? "$bad FAILED, $ok ok\n" : "OK $ok sets\n";
exit($bad ? 1 : 0);
