<?php

declare(strict_types=1);

// usage: php -d xdebug.mode=off bench/dump_config.php <.php-cs-fixer.dist.php> > .manticore-fmt.json
// Dev helper (Zend, upstream's vendor): evaluates a PHP cs-fixer config and prints the .manticore-fmt.json equivalent: rules, risky,
// indent, lineEnding, cache file and, when the config's Finder is a Symfony Finder, its in() / exclude() / name() / notName() /
// path() / notPath() lists. Things JSON cannot say (a callback filter, a custom fixer, a rule customisation policy, ->append()) are
// listed on stderr: translate those by hand, see docs/config.md.

require __DIR__.'/lib.php';
require FORK.'/bench/work/upstream/vendor/autoload.php';

$file = $argv[1] ?? null;
if (null === $file || !is_file($file)) {
    fwrite(STDERR, "usage: php bench/dump_config.php <config.php>\n");
    exit(2);
}
chdir(dirname(realpath($file)));
$config = (static fn () => require realpath($file))();
if (!$config instanceof PhpCsFixer\ConfigInterface) {
    fwrite(STDERR, "the file does not return a PhpCsFixer\\ConfigInterface\n");
    exit(2);
}

$out = ['rules' => $config->getRules() ?: new stdClass()];
if ($config->getRiskyAllowed()) {
    $out['risky'] = true;
}
if ("    " !== $config->getIndent()) {
    $out['indent'] = $config->getIndent();
}
if ("\n" !== $config->getLineEnding()) {
    $out['lineEnding'] = $config->getLineEnding();
}
if (!$config->getUsingCache()) {
    $out['cache'] = ['enabled' => false];
}
$todo = [];
if (count($config->getCustomFixers()) > 0) {
    $todo[] = 'custom fixers: '.implode(', ', array_map(static fn ($f) => $f->getName(), $config->getCustomFixers()));
}

$finder = $config->getFinder();
if ($finder instanceof Symfony\Component\Finder\Finder) {
    $get = static function (string $prop) use ($finder) {
        return Closure::bind(static fn (Symfony\Component\Finder\Finder $f) => $f->$prop, null, Symfony\Component\Finder\Finder::class)($finder);
    };
    $dirs = array_map(static fn (string $d) => rtrim(str_replace(getcwd().'/', '', $d), '/'), $get('dirs'));
    if ([] !== $dirs) {
        $out['paths'] = array_values($dirs);
    }
    if ([] !== $get('exclude')) {
        $out['exclude'] = array_values($get('exclude'));
    } else {
        $out['exclude'] = [];
    }
    foreach (['names' => 'name', 'notNames' => 'notName', 'paths' => 'path', 'notPaths' => 'notPath'] as $prop => $key) {
        $v = array_values(array_filter($get($prop), static fn (string $p) => '/\.php$/' !== $p)); // the default Finder's own name filter
        if ([] !== $v) {
            $out[$key] = $v;
        }
    }
    if ([] !== $get('filters')) {
        $todo[] = 'the Finder has a ->filter() callback';
    }
    if ([] !== $get('iterators')) {
        $todo[] = 'the Finder has ->append()ed files or iterators: list them under "paths"';
    }
    if (false === $get('ignore') & Symfony\Component\Finder\Finder::IGNORE_DOT_FILES) {
        $out['ignoreDotFiles'] = false;
    }
} else {
    $todo[] = 'the config uses a non-Finder file iterator: write "paths" by hand';
}
foreach ($todo as $t) {
    fwrite(STDERR, "TODO: $t\n");
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
