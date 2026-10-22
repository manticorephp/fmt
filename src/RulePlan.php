<?php

declare(strict_types=1);

namespace Fmt;

/**
 * The rules one run applies: user rules (sets expanded, aliases translated) validated like upstream's ConfigurationResolver
 * (unknown names, empty options, risky fixers) and split into the implemented ones, built into GapRules, and the rest.
 */
final class RulePlan
{
    /** @var GapRule[] priority order */
    public array $rules = [];
    /** @var string[] resolved rules this build has no implementation for */
    public array $missing = [];
    /** @var array<string, mixed> rule => true | options, the resolved set (implemented and not) */
    public array $resolved = [];

    /**
     * @param array<string, mixed> $raw name => bool | options, as written in a config / --rules
     * @throws ConfigException
     */
    public static function build(array $raw, string $indent, string $lineEnding, bool $allowRisky, bool $allowUnimplemented): self
    {
        $unknown = [];
        foreach ($raw as $k => $v) {
            $k = (string) $k;
            if ($k !== '' && $k[0] !== '@' && !Aliases::known($k)) {
                $s = Aliases::suggest($k);
                $unknown[] = '"' . $k . '"' . ($s === null ? '' : ' (did you mean "' . $s . '"?)');
            }
        }
        if ($unknown !== []) {
            throw new ConfigException('The rules contain unknown fixers: ' . \implode(', ', $unknown) . '.');
        }
        try {
            $set = RuleSets::resolve($raw);
        } catch (\InvalidArgumentException $e) {
            throw new ConfigException($e->getMessage());
        }
        // FixerFactory::useRuleSet: fixers that must not run together
        $conflictTable = ['blank_lines_before_namespace' => ['no_blank_lines_before_namespace', 'single_blank_line_before_namespace'],
            'no_blank_lines_before_namespace' => ['single_blank_line_before_namespace'], 'single_import_per_statement' => ['group_import']];
        /** @var array<string, string[]> $found */
        $found = [];
        $msg = 'Rule contains conflicting fixers:';
        foreach (\array_keys($set) as $name) {
            $name = (string) $name;
            foreach ($conflictTable[$name] ?? [] as $other) {
                if (isset($set[$other])) {
                    $found[$name][] = $other;
                }
            }
        }
        if ($found !== []) {
            $report = [];
            foreach ($found as $fixer => $others) {
                $keep = [];
                foreach ($others as $c) {
                    if (!isset($report[$c]) || !\in_array($fixer, $report[$c], true)) {
                        $keep[] = $c;
                    }
                }
                $report[$fixer] = $keep;
                if ($keep !== []) {
                    $msg .= "\n- \"" . $fixer . '" with ' . RuleSets::joinNames($keep);
                }
            }
            throw new ConfigException($msg, 1);
        }
        foreach ($set as $name => $value) {
            $f = Fixers::all()[(string) $name] ?? null;
            if (\is_array($value) && $f !== null && $f['opts'] === []) {
                throw new ConfigException('[' . $name . '] Is not configurable.', ConfigException::INVALID_FIXER_CONFIG);
            }
        }
        $plan = new self();
        /** @var array<string, mixed> $res */
        $res = [];
        /** @var list<array{0: string, 1: mixed, 2: int, 3: string}> $entries name, value, slot priority, slot name ('' = the rule's own): an alias runs in its own slot */
        $entries = [];
        foreach ($set as $name => $value) {
            try {
                $ex = Aliases::expand((string) $name, $value);
            } catch (\InvalidArgumentException $e) {
                throw new ConfigException('[' . $name . '] ' . $e->getMessage(), ConfigException::INVALID_FIXER_CONFIG);
            }
            if ($ex === null) {
                $res[(string) $name] = $value;
                $entries[] = [(string) $name, $value, 0, ''];
                continue;
            }
            $slot = Aliases::slot((string) $name);
            foreach ($ex as [$rn, $rv]) {
                $res[$rn] = $rv;
                $entries[] = [$rn, $rv, $slot ?? 0, $slot === null ? '' : (string) $name];
            }
        }
        $risky = [];
        foreach ($entries as [$name, $value]) {
            if (\is_array($value) && $value === []) {
                throw new ConfigException('[' . $name . '] Configuration must be an array and may not be empty.', ConfigException::INVALID_FIXER_CONFIG);
            }
            if (Fixers::isRisky((string) $name)) {
                $risky[] = (string) $name;
            }
        }
        if ($risky !== [] && !$allowRisky) {
            \sort($risky);
            throw new ConfigException('The rules contain risky fixers (' . RuleSets::joinNames($risky)
                . '), but they are not allowed to run. Perhaps you forget to use --allow-risky=yes option?');
        }
        $plan->resolved = $res;
        foreach ($entries as [$name, $value, $slotPrio, $slotName]) {
            if (!Fixers::implemented($name)) {
                $plan->missing[] = $name;
                continue;
            }
            /** @var array<string, mixed> $opt */
            $opt = [];
            if (\is_array($value)) {
                foreach ($value as $k => $x) {
                    $opt[(string) $k] = $x;
                }
            }
            try {
                $r = Rules::make($name, $lineEnding, $opt, $indent);
            } catch (\InvalidArgumentException $e) {
                throw new ConfigException('[' . $name . '] ' . $e->getMessage(), ConfigException::INVALID_FIXER_CONFIG);
            }
            if ($r === null) {
                $plan->missing[] = $name;
            } else {
                if ($slotName !== '') {
                    $r->slotPriority = $slotPrio;
                    $r->slotName = $slotName;
                }
                $plan->rules[] = $r;
            }
        }
        if ($plan->missing !== [] && !$allowUnimplemented) {
            \sort($plan->missing);
            throw new ConfigException('rules not implemented (' . \count($plan->missing) . '): ' . \implode(', ', $plan->missing)
                . "\nUse --allow-unimplemented to skip them.");
        }
        $plan->rules = Engine::sort($plan->rules);
        return $plan;
    }
}
