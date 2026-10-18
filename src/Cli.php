<?php

declare(strict_types=1);

namespace Fmt;

/**
 * manticore-fmt fix [--dry-run] [--diff] --rules=<csv|@PSR12|JSON> <paths...>
 * Exit codes follow cs-fixer: 0 ok, 8 would change (dry-run), 16 invalid usage / unsupported rule.
 */
final class Cli
{
    /** @param string[] $argv */
    public static function run(array $argv): int
    {
        $dry = false;
        $diff = false;
        $rulesCsv = '';
        $le = "\n";
        $paths = [];
        $cmd = $argv[1] ?? '';
        if ($cmd === 'dump-tokens') {
            return self::dumpTokens(\array_slice($argv, 2));
        }
        if ($cmd !== 'fix') {
            \fwrite(\STDERR, "usage: manticore-fmt fix [--dry-run] [--diff] --rules=<csv|@PSR12> <paths...>\n");
            return $cmd === '' || $cmd === 'help' || $cmd === '--help' ? 0 : 16;
        }
        for ($i = 2; $i < \count($argv); $i++) {
            $a = $argv[$i];
            if ($a === '--dry-run') {
                $dry = true;
            } elseif ($a === '--diff') {
                $diff = true;
            } elseif (\str_starts_with($a, '--rules=')) {
                $rulesCsv = \substr($a, 8);
            } elseif ($a === '--sequential' || \str_starts_with($a, '--using-cache=')) {
                // accepted for cs-fixer command-line compatibility
            } elseif (\str_starts_with($a, '--')) {
                \fwrite(\STDERR, "unknown option $a\n");
                return 16;
            } else {
                $paths[] = $a;
            }
        }
        if ($rulesCsv === '' || $paths === []) {
            \fwrite(\STDERR, "fix needs --rules=... and at least one path\n");
            return 16;
        }
        /** @var GapRule[] $rules */
        $rules = [];
        $missing = [];
        try {
            foreach (Rules::resolve($rulesCsv) as $name => $opt) {
                $r = Rules::make($name, $le, $opt);
                if ($r === null) {
                    $missing[] = $name;
                } else {
                    $rules[] = $r;
                }
            }
        } catch (\InvalidArgumentException $e) {
            \fwrite(\STDERR, $e->getMessage() . "\n");
            return 16;
        }
        if ($missing !== []) {
            \fwrite(\STDERR, 'rule not implemented: ' . \implode(', ', $missing) . "\n");
            return 16;
        }
        $rules = Engine::sort($rules);
        $changed = 0;
        foreach (Files::collect($paths) as $file) {
            $code = \file_get_contents($file);
            if ($code === false) {
                \fwrite(\STDERR, "cannot read $file\n");
                continue;
            }
            $s = Source::parse($code);
            Engine::run($s, $rules);
            $new = $s->join();
            if ($new === $code) {
                continue;
            }
            $changed++;
            \fwrite(\STDOUT, \sprintf("%4d) %s\n", $changed, $file));
            if ($diff) {
                \fwrite(\STDOUT, Diff::unified($code, $new, $file) . "\n");
            }
            if (!$dry) {
                \file_put_contents($file, $new);
            }
        }
        return ($dry && $changed > 0) ? 8 : 0;
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
