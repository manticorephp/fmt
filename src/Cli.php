<?php

declare(strict_types=1);

namespace Fmt;

/**
 * manticore-fmt fix|check [options] [paths...]   see `manticore-fmt help`.
 * Exit codes are upstream's FixCommand bitmask: 4 invalid files (dry run), 8 files would change (dry run), 16 invalid
 * configuration or usage, 32 invalid rule configuration, 64 a file could not be fixed.
 */
final class Cli
{
    public const ABOUT = 'Manticore Fmt 0.2.0 (rules of PHP CS Fixer 3.95.27)';

    /** @param string[] $argv */
    public static function run(array $argv): int
    {
        $cmd = $argv[1] ?? '';
        $rest = \array_slice($argv, 2);
        try {
            if ($cmd === 'fix' || $cmd === 'check') {
                return self::fix($argv[0], $cmd === 'check', $rest);
            }
            if ($cmd === 'resolve') {
                return self::resolve($rest);
            }
            if ($cmd === 'dump-tokens') {
                return self::dumpTokens($rest);
            }
            if ($cmd === 'list-rules') {
                return Describe::listRules($rest);
            }
            if ($cmd === 'list-sets') {
                return Describe::listSets();
            }
            if ($cmd === 'describe') {
                return Describe::describe($rest);
            }
            if ($cmd === 'version' || $cmd === '--version') {
                \fwrite(\STDOUT, self::ABOUT . "\n");
                return 0;
            }
        } catch (ConfigException $e) {
            \fwrite(\STDERR, $e->getMessage() . "\n");
            return $e->getCode();
        }
        \fwrite($cmd === '' || $cmd === 'help' || $cmd === '--help' ? \STDOUT : \STDERR, self::usage());
        return $cmd === '' || $cmd === 'help' || $cmd === '--help' ? 0 : 16;
    }

    private static function usage(): string
    {
        return self::ABOUT . "\n\n"
            . "usage: manticore-fmt <command> [options] [paths...]\n\n"
            . "commands:\n"
            . "  fix [paths...]      format files in place (a path of `-` reads stdin and only reports)\n"
            . "  check [paths...]    like fix --dry-run: exit 8 when a file would change\n"
            . "  list-rules          every upstream rule with its status here (--implemented / --unimplemented print bare names)\n"
            . "  list-sets           every upstream set with its rule counts here\n"
            . "  describe <name>     a rule (options, defaults, allowed values) or a set\n"
            . "  dump-tokens <paths> token dump for parity checks\n"
            . "  resolve [--rules=..] [--config=..]  the effective rules (sets expanded, aliases translated) as JSON\n"
            . "  version\n\n"
            . "options of fix / check:\n"
            . "  --config=FILE           .manticore-fmt.{json,yaml,yml}[.dist] (default: found in the path's directory or the cwd; `-` = none)\n"
            . "  --rules=RULES           csv (`@PSR12,-yoda_style`) or JSON, replaces the config's rules\n"
            . "  --dry-run, -n           do not write (check implies it)\n"
            . "  --diff                  show a diff of every changed file\n"
            . "  --format=FMT            txt (default), json, checkstyle, junit, gitlab\n"
            . "  --allow-risky[=yes|no]  allow risky rules\n"
            . "  --allow-unimplemented   skip rules this build has not implemented (default: fail naming them)\n"
            . "  --using-cache=yes|no, --no-cache, --cache-file=FILE\n"
            . "  --path-mode=override|intersection\n"
            . "  --workers=N             0 = one per core (default), 1 = sequential;  --sequential\n"
            . "  --show-progress=none|dots   --stop-on-violation   -v/--verbose (applied rules)   -q/--quiet\n";
    }

    /**
     * @param string[] $args
     * @throws ConfigException
     */
    private static function fix(string $argv0, bool $check, array $args): int
    {
        $t0 = \microtime(true);
        $o = Options::parse($args);
        $dry = $check || $o->dry;
        $stdin = $o->paths === ['-'];
        if ($stdin) {
            $dry = true;
        }
        $cwd = (string) \getcwd();

        // configuration
        $cfgFile = '';
        if (!$o->noConfig) {
            if ($o->config !== '') {
                if (!\is_file($o->config)) {
                    throw new ConfigException('Cannot read config file "' . $o->config . '".');
                }
                $cfgFile = $o->config;
            } else {
                $dir = $cwd;
                if (\count($o->paths) === 1 && !$stdin) {
                    $p = $o->paths[0];
                    $dir = \is_dir($p) ? $p : \dirname($p);
                }
                $cfgFile = ConfigLoader::discover($dir);
                if ($cfgFile === '' && $dir !== $cwd) {
                    $cfgFile = ConfigLoader::discover($cwd);
                }
            }
        }
        $cfg = $cfgFile !== '' ? ConfigLoader::load($cfgFile) : new Config();
        if ($cfgFile !== '' && $o->verbose) {
            \fwrite(\STDERR, 'Loaded config from "' . $cfgFile . "\".\n");
        }
        if ($o->format !== '') {
            $cfg->format = $o->format;
        }
        if (!\in_array($cfg->format, Reporter::FORMATS, true)) {
            throw new ConfigException('The format "' . $cfg->format . '" is not defined, supported are ' . RuleSets::joinNames(Reporter::FORMATS) . '.');
        }
        if ($o->pathMode !== '') {
            $cfg->pathMode = $o->pathMode;
        }

        // rules
        if ($o->rules !== '') {
            $raw = self::parseRules($o->rules);
        } elseif ($cfg->rulesGiven) {
            $raw = $cfg->rules;
        } else {
            $raw = ['@PSR12' => true];
        }
        $risky = $o->allowRisky !== '' ? $o->allowRisky === 'yes' : $cfg->risky;
        $plan = RulePlan::build($raw, $cfg->indent, $cfg->lineEnding, $risky, $o->allowUnimplemented || $cfg->allowUnimplemented);
        if ($plan->missing !== [] && !$o->quiet) {
            \fwrite(\STDERR, 'Skipped ' . \count($plan->missing) . ' rule(s) not implemented: ' . \implode(', ', $plan->missing) . "\n");
        }

        $withFixers = $o->verbose;
        $needApplied = $o->verbose || \in_array($cfg->format, ['checkstyle', 'gitlab', 'junit'], true);
        $needDiff = $o->diff;

        if ($stdin) {
            $code = (string) \stream_get_contents(\STDIN);
            $proc = new Processor($plan->rules, true, $o->diff, $needApplied);
            $out = $proc->code('php://stdin', $code);
            return self::finish($o, $cfg, [$out], 1, $dry, $withFixers, $t0, null);
        }

        $files = FileFinder::find($cfg, $o->paths);

        // cache
        $cache = null;
        // like upstream the cache is on by default, but only a config file (or --using-cache=yes) turns it on here: no stray cache file
        $useCache = $o->useCache !== '' ? $o->useCache === 'yes' : ($cfg->cache && $cfgFile !== '');
        if ($useCache) {
            $cf = $o->cacheFile !== '' ? $o->cacheFile : ($cfg->cacheFile !== '' ? $cfg->cacheFile : '.manticore-fmt.cache');
            $sig = \md5(Cache::stamp($argv0) . '|' . \json_encode($plan->resolved) . '|' . \json_encode($cfg->indent) . '|' . \json_encode($cfg->lineEnding));
            $cache = new Cache($cf, $sig);
        }

        $proc = new Processor($plan->rules, $dry, $needDiff, $needApplied, $cache);
        $workers = $cfg->workers;
        if ($o->workers >= 0) {
            $workers = $o->workers;
        }
        if ($o->sequential || $o->stopOnViolation) {
            $workers = 1;
        }
        if ($workers === 0) {
            $workers = Pool::cores();
        }
        if ($o->stopOnViolation) {
            $results = [];
            foreach ($files as $f) {
                $r = $proc->file($f);
                $results[] = $r;
                if ($r->status === Outcome::CHANGED || $r->status === Outcome::FAILED || $r->status === Outcome::INVALID) {
                    break;
                }
            }
        } else {
            $results = Pool::run($files, $workers, $proc);
        }
        if ($cache !== null) {
            foreach ($results as $r) {
                $cache->note($r);
            }
            $cache->save();
        }
        return self::finish($o, $cfg, $results, \count($files), $dry, $withFixers, $t0, $cache);
    }

    /**
     * @param Outcome[] $results
     */
    private static function finish(Options $o, Config $cfg, array $results, int $total, bool $dry, bool $withFixers, float $t0, ?Cache $cache): int
    {
        $changed = [];
        $invalid = [];
        $failed = [];
        $dots = '';
        foreach ($results as $r) {
            if ($r->status === Outcome::CHANGED) {
                $changed[] = $r;
                $dots .= 'F';
            } elseif ($r->status === Outcome::INVALID) {
                $invalid[] = $r;
                $dots .= 'I';
            } elseif ($r->status === Outcome::FAILED) {
                $failed[] = $r;
                $dots .= 'E';
            } elseif ($r->status === Outcome::CACHED) {
                $dots .= 'S';
            } else {
                $dots .= '.';
            }
        }
        if ($o->progress !== 'none' && !$o->quiet) {
            \fwrite(\STDERR, $dots . "\n");
        }
        if (!$o->quiet) {
            $secs = \microtime(true) - $t0;
            $mb = \memory_get_peak_usage(true) / 1048576;
            \fwrite(\STDOUT, Reporter::render($cfg->format, $changed, $dry, $withFixers, $total, $secs, $mb));
            $bad = \array_merge($invalid, $failed);
            if ($bad !== []) {
                \usort($bad, static fn (Outcome $a, Outcome $b): int => \strcmp($a->path, $b->path));
                \fwrite(\STDERR, "\nFiles that were not fixed due to errors reported during " . ($invalid !== [] ? 'linting' : 'fixing') . ":\n");
                $i = 0;
                foreach ($bad as $b) {
                    $i++;
                    \fwrite(\STDERR, \sprintf("%4d) %s: %s\n", $i, $b->path, $b->msg));
                }
            }
            if ($o->verbose && $cache !== null) {
                $skipped = 0;
                foreach ($results as $r) {
                    if ($r->status === Outcome::CACHED) {
                        $skipped++;
                    }
                }
                \fwrite(\STDERR, 'Cache: ' . $skipped . ' of ' . $total . " files unchanged since the last run.\n");
            }
        }
        Notices::flush();
        $code = 0;
        if ($dry && $changed !== []) {
            $code |= 8;
        }
        if ($dry && $invalid !== []) {
            $code |= 4;
        }
        if ($failed !== []) {
            $code |= 64;
        }
        return $code;
    }

    /**
     * The effective rule set as JSON, sorted by name: what `fix` would run, before the implemented / risky checks.
     * @param string[] $args
     * @throws ConfigException
     */
    private static function resolve(array $args): int
    {
        $o = Options::parse($args);
        $cfg = new Config();
        if (!$o->noConfig) {
            $file = $o->config !== '' ? $o->config : ConfigLoader::discover((string) \getcwd());
            if ($file !== '') {
                $cfg = ConfigLoader::load($file);
            }
        }
        $raw = $o->rules !== '' ? self::parseRules($o->rules) : ($cfg->rulesGiven ? $cfg->rules : ['@PSR12' => true]);
        $plan = RulePlan::build($raw, $cfg->indent, $cfg->lineEnding, true, true);
        $r = $plan->resolved;
        \ksort($r);
        \fwrite(\STDOUT, \json_encode($r, \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT) . "\n");
        Notices::flush();
        return 0;
    }

    /**
     * `--rules`: a JSON object, or a csv where `-name` switches a rule or set off
     * @return array<string, mixed>
     * @throws ConfigException
     */
    public static function parseRules(string $arg): array
    {
        $arg = \trim($arg);
        if ($arg === '') {
            throw new ConfigException('Empty rules value is not allowed.');
        }
        if ($arg[0] === '{') {
            $j = \json_decode($arg, true);
            if (!\is_array($j)) {
                throw new ConfigException('Invalid JSON rules input: "' . \json_last_error_msg() . '".');
            }
            /** @var array<string, mixed> $out */
            $out = [];
            foreach ($j as $k => $v) {
                $out[(string) $k] = $v;
            }
            return $out;
        }
        /** @var array<string, mixed> $out */
        $out = [];
        foreach (\explode(',', $arg) as $item) {
            $item = \trim($item);
            if ($item === '') {
                throw new ConfigException('Empty rule name is not allowed.');
            }
            if ($item[0] === '-') {
                $out[\substr($item, 1)] = false;
            } else {
                $out[$item] = true;
            }
        }
        return $out;
    }

    /**
     * Token parity dump (the native side of bench/dump_tokens.php): per file `== path`, then `id<TAB>escaped text` per token,
     * id = the CT::* id when the token has a custom kind, else the lexer kind (the character code for one-char tokens).
     * @param string[] $paths
     */
    private static function dumpTokens(array $paths): int
    {
        foreach (Files::collect($paths) as $file) {
            $s = Source::parse((string) \file_get_contents($file));
            $out = "== $file\n";
            for ($i = 0; $i < $s->n; $i++) {
                $id = $s->ct[$i] !== 0 ? $s->ct[$i] : $s->kind[$i];
                $out .= $id . "\t" . \addcslashes($s->text($i), "\0..\37\\\\\177..\377") . "\n";
            }
            \fwrite(\STDOUT, $out);
        }
        return 0;
    }
}
