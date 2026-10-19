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
        $plan = new self();
        /** @var array<string, mixed> $res */
        $res = [];
        foreach ($set as $name => $value) {
            $t = Aliases::target((string) $name, $value);
            if ($t !== null) {
                $res[$t[0]] = $t[1];
            } else {
                $res[(string) $name] = $value;
            }
        }
        $risky = [];
        foreach ($res as $name => $value) {
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
        foreach ($res as $name => $value) {
            $name = (string) $name;
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
