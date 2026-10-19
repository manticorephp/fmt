<?php

declare(strict_types=1);

namespace Fmt;

/**
 * Upstream's RuleSet::resolveSet over the generated set table (src/RuleSetTable.php): nested sets expand in place, a later
 * entry replaces an earlier one (options are replaced, never merged), `@set => false` switches every rule of the set off, and
 * rules that end up `false` are dropped. A rule value is `true` (defaults) or an options array.
 */
final class RuleSets
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $table = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        if (self::$table === null) {
            /** @var array<string, array<string, mixed>> $t */
            $t = \json_decode(RuleSetTable::JSON, true);
            self::$table = $t;
        }
        return self::$table;
    }

    public static function exists(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    /**
     * @param array<string, mixed> $set name => bool | options array (a name starting with @ is a set)
     * @return array<string, mixed> rule => true | options array
     * @throws \InvalidArgumentException on a malformed entry or an unknown / unsupported set
     */
    public static function resolve(array $set): array
    {
        /** @var array<string, mixed> $out */
        $out = [];
        foreach ($set as $name => $value) {
            $name = (string) $name;
            if ($name === '') {
                throw new \InvalidArgumentException('Rule/set name must not be empty.');
            }
            if (!\is_bool($value) && !\is_array($value)) {
                $what = $name[0] === '@' ? 'Set must be enabled (true) or disabled (false). Other values are not allowed.'
                    : 'Rule must be enabled (true), disabled (false) or configured (non-empty, assoc array). Other values are not allowed.';
                if ($value === null) {
                    $what .= ' To disable the ' . ($name[0] === '@' ? 'set' : 'rule') . ', use "FALSE" instead of "NULL".';
                }
                throw new \InvalidArgumentException('[' . $name . '] ' . $what);
            }
            if ($name[0] === '@') {
                if (!\is_bool($value)) {
                    throw new \InvalidArgumentException('Nested rule set "' . $name . '" configuration must be a boolean.');
                }
                foreach (self::subset($name, $value) as $k => $v) {
                    $out[$k] = $v;
                }
            } else {
                $out[$name] = $value;
            }
        }
        /** @var array<string, mixed> $res */
        $res = [];
        foreach ($out as $k => $v) {
            if ($v !== false) {
                $res[$k] = $v;
            }
        }
        return $res;
    }

    /** @return array<string, mixed> */
    private static function subset(string $name, bool $on): array
    {
        $def = self::all()[$name] ?? null;
        if ($def === null) {
            throw new \InvalidArgumentException('Set "' . $name . '" does not exist.');
        }
        if ($def['auto'] === true) {
            throw new \InvalidArgumentException('Set "' . $name . '" is chosen from composer.json by upstream; name the concrete set instead (see `list-sets`).');
        }
        if ($def['succ'] !== null) {
            /** @var string[] $succ */
            $succ = $def['succ'];
            Notices::add('Rule set "' . $name . '" is deprecated. ' . ($succ === [] ? 'No replacement available' : 'Use ' . self::joinNames($succ) . ' instead') . '.');
        }
        /** @var array<string, mixed> $rules */
        $rules = $def['rules'];
        /** @var array<string, mixed> $out */
        $out = [];
        foreach ($rules as $k => $v) {
            $k = (string) $k;
            if ($k[0] === '@') {
                foreach (self::subset($k, $on) as $k2 => $v2) {
                    $out[$k2] = $v2;
                }
            } else {
                $out[$k] = $on ? $v : false;
            }
        }
        return $out;
    }

    /** @param string[] $names */
    public static function joinNames(array $names): string
    {
        $q = [];
        foreach ($names as $n) {
            $q[] = '"' . $n . '"';
        }
        $c = \count($q);
        if ($c === 1) {
            return $q[0];
        }
        return \implode(', ', \array_slice($q, 0, $c - 1)) . ' and ' . $q[$c - 1];
    }
}
