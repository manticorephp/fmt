<?php

declare(strict_types=1);

namespace Fmt;

/** `list-rules`, `list-sets`, `describe`: upstream's catalogue and how much of it this build implements. */
final class Describe
{
    /**
     * @param string[] $args --implemented / --unimplemented: bare names, one per line
     */
    public static function listRules(array $args): int
    {
        $only = '';
        foreach ($args as $a) {
            if ($a === '--implemented' || $a === '--unimplemented') {
                $only = $a;
            } else {
                throw new ConfigException('list-rules: unknown option ' . $a);
            }
        }
        $done = 0;
        $todo = 0;
        foreach (Fixers::all() as $name => $f) {
            $name = (string) $name;
            $impl = Fixers::implemented($name);
            $impl ? $done++ : $todo++;
            if ($only === '--implemented' && !$impl || $only === '--unimplemented' && $impl) {
                continue;
            }
            if ($only !== '') {
                \fwrite(\STDOUT, $name . "\n");
                continue;
            }
            $line = \str_pad($name, 48) . ($impl ? 'implemented    ' : 'not implemented');
            if ($f['risky'] === true) {
                $line .= '  risky';
            }
            if ($f['succ'] !== null) {
                /** @var string[] $succ */
                $succ = $f['succ'];
                $line .= '  deprecated' . ($succ === [] ? '' : ' -> ' . \implode(', ', $succ));
            }
            \fwrite(\STDOUT, $line . "\n");
        }
        if ($only === '') {
            \fwrite(\STDERR, $done . ' implemented, ' . $todo . " not implemented of " . ($done + $todo) . " upstream rules\n");
        }
        return 0;
    }

    public static function listSets(): int
    {
        \fwrite(\STDOUT, \str_pad('set', 34) . \str_pad('rules', 7) . \str_pad('implemented', 13) . \str_pad('missing', 9) . "notes\n");
        foreach (RuleSets::all() as $name => $def) {
            $name = (string) $name;
            $note = $def['risky'] === true ? 'risky' : '';
            if ($def['auto'] === true) {
                \fwrite(\STDOUT, \str_pad($name, 34) . \str_pad('-', 7) . \str_pad('-', 13) . \str_pad('-', 9) . "chosen from composer.json upstream: not supported\n");
                continue;
            }
            if ($def['succ'] !== null) {
                $note .= ($note === '' ? '' : ' ') . 'deprecated';
            }
            $rules = [];
            foreach (self::flat($name) as $r) {
                $rules[$r] = true;
            }
            $have = 0;
            foreach (\array_keys($rules) as $r) {
                if (Fixers::implemented((string) $r)) {
                    $have++;
                }
            }
            \fwrite(\STDOUT, \str_pad($name, 34) . \str_pad((string) \count($rules), 7) . \str_pad((string) $have, 13) . \str_pad((string) (\count($rules) - $have), 9) . $note . "\n");
        }
        return 0;
    }

    /** @return string[] rule names of a set after expansion and alias translation */
    public static function flat(string $set): array
    {
        $out = [];
        foreach (RuleSets::resolve([$set => true]) as $name => $v) {
            $t = Aliases::target((string) $name, $v);
            $out[] = $t !== null ? $t[0] : (string) $name;
        }
        Notices::clear();
        return $out;
    }

    /** @param string[] $args */
    public static function describe(array $args): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            throw new ConfigException('describe: give a rule or set name.');
        }
        if ($name[0] === '@') {
            $def = RuleSets::all()[$name] ?? null;
            if ($def === null) {
                throw new ConfigException('Set "' . $name . '" does not exist.');
            }
            \fwrite(\STDOUT, $name . "\n" . $def['desc'] . "\n");
            if ($def['auto'] === true) {
                \fwrite(\STDOUT, "chosen from composer.json upstream: not supported here\n");
                return 0;
            }
            if ($def['succ'] !== null) {
                /** @var string[] $succ */
                $succ = $def['succ'];
                \fwrite(\STDOUT, 'deprecated' . ($succ === [] ? '' : ', use ' . \implode(', ', $succ)) . "\n");
            }
            \fwrite(\STDOUT, "rules:\n");
            foreach (RuleSets::resolve([$name => true]) as $r => $v) {
                $r = (string) $r;
                \fwrite(\STDOUT, '  ' . \str_pad($r, 46) . (Fixers::implemented($r) ? 'implemented     ' : 'not implemented ')
                    . ($v === true ? '' : \json_encode($v, \JSON_UNESCAPED_SLASHES)) . "\n");
            }
            Notices::flush();
            return 0;
        }
        $f = Fixers::all()[$name] ?? null;
        if ($f === null) {
            $s = Aliases::suggest($name);
            throw new ConfigException('Rule "' . $name . '" does not exist' . ($s === null ? '.' : ' (did you mean "' . $s . '"?).'));
        }
        \fwrite(\STDOUT, $name . ' (' . (Fixers::implemented($name) ? 'implemented' : 'NOT implemented') . ($f['risky'] === true ? ', risky' : '') . ")\n" . $f['sum'] . "\n");
        if ($f['succ'] !== null) {
            /** @var string[] $succ */
            $succ = $f['succ'];
            \fwrite(\STDOUT, 'deprecated' . ($succ === [] ? '' : ', use ' . \implode(', ', $succ) . ' instead') . "\n");
        }
        /** @var array<int, array<string, mixed>> $opts */
        $opts = $f['opts'];
        if ($opts === []) {
            \fwrite(\STDOUT, "no options\n");
            return 0;
        }
        \fwrite(\STDOUT, "options:\n");
        foreach ($opts as $o) {
            /** @var string[] $types */
            $types = $o['types'] ?? [];
            \fwrite(\STDOUT, '  ' . $o['name'] . ' (' . \implode('|', $types) . ') default ' . \json_encode($o['def'], \JSON_UNESCAPED_SLASHES) . "\n");
            if (($o['allowed'] ?? null) !== null) {
                \fwrite(\STDOUT, '      allowed: ' . \json_encode($o['allowed'], \JSON_UNESCAPED_SLASHES) . "\n");
            }
            \fwrite(\STDOUT, '      ' . $o['desc'] . "\n");
        }
        return 0;
    }
}
