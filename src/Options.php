<?php

declare(strict_types=1);

namespace Fmt;

/** The command line of `fix` / `check`: `--name=value` or `--name value`; short -v -q -n. */
final class Options
{
    public string $config = '';
    public bool $noConfig = false;
    public string $rules = '';
    public bool $dry = false;
    public bool $diff = false;
    public string $format = '';
    public string $cacheFile = '';
    /** '' = as configured, 'yes' / 'no' */
    public string $useCache = '';
    public string $progress = 'none';
    public bool $verbose = false;
    public bool $quiet = false;
    public bool $stopOnViolation = false;
    /** '' = as configured */
    public string $allowRisky = '';
    public bool $allowUnimplemented = false;
    public string $pathMode = '';
    public int $workers = -1;
    public bool $sequential = false;
    /** @var string[] */
    public array $paths = [];

    private const VALUE = ['config', 'rules', 'format', 'cache-file', 'using-cache', 'show-progress', 'path-mode', 'workers', 'allow-risky'];

    /**
     * @param string[] $args
     * @throws ConfigException
     */
    public static function parse(array $args): self
    {
        $o = new self();
        $n = \count($args);
        for ($i = 0; $i < $n; $i++) {
            $a = $args[$i];
            if ($a === '-') {
                $o->paths[] = $a;
                continue;
            }
            if ($a === '--') {
                for ($i++; $i < $n; $i++) {
                    $o->paths[] = $args[$i];
                }
                break;
            }
            if ($a === '-v' || $a === '-vv' || $a === '-vvv') {
                $o->verbose = true;
                continue;
            }
            if ($a === '-q') {
                $o->quiet = true;
                continue;
            }
            if ($a === '-n') {
                $o->dry = true;
                continue;
            }
            if (!\str_starts_with($a, '--')) {
                $o->paths[] = $a;
                continue;
            }
            $name = \substr($a, 2);
            $val = null;
            $eq = \strpos($name, '=');
            if ($eq !== false) {
                $val = \substr($name, $eq + 1);
                $name = \substr($name, 0, $eq);
            }
            if (\in_array($name, self::VALUE, true) && $val === null && $name !== 'allow-risky') {
                if ($i + 1 >= $n) {
                    throw new ConfigException('The "--' . $name . '" option requires a value.');
                }
                $val = $args[++$i];
            }
            $o->set($name, $val);
        }
        return $o;
    }

    private function set(string $name, ?string $val): void
    {
        $v = $val ?? '';
        switch ($name) {
            case 'config':
                if ($v === '-') {
                    $this->noConfig = true;
                } else {
                    $this->config = $v;
                }
                return;
            case 'rules':
                $this->rules = $v;
                return;
            case 'dry-run':
                $this->dry = true;
                return;
            case 'diff':
                $this->diff = true;
                return;
            case 'format':
                if (!\in_array($v, Reporter::FORMATS, true)) {
                    throw new ConfigException('The format "' . $v . '" is not defined, supported are ' . RuleSets::joinNames(Reporter::FORMATS) . '.');
                }
                $this->format = $v;
                return;
            case 'cache-file':
                $this->cacheFile = $v;
                return;
            case 'using-cache':
                if ($v !== 'yes' && $v !== 'no') {
                    throw new ConfigException('The "--using-cache" option must be "yes" or "no".');
                }
                $this->useCache = $v;
                return;
            case 'no-cache':
                $this->useCache = 'no';
                return;
            case 'show-progress':
                if (!\in_array($v, ['none', 'dots', 'bar'], true)) {
                    throw new ConfigException('The progress type "' . $v . '" is not defined, supported are "none", "dots" and "bar".');
                }
                $this->progress = $v;
                return;
            case 'verbose':
                $this->verbose = true;
                return;
            case 'quiet':
                $this->quiet = true;
                return;
            case 'stop-on-violation':
                $this->stopOnViolation = true;
                return;
            case 'allow-risky':
                if ($val === null || $v === 'yes') {
                    $this->allowRisky = 'yes';
                } elseif ($v === 'no') {
                    $this->allowRisky = 'no';
                } else {
                    throw new ConfigException('The "--allow-risky" option must be "yes" or "no".');
                }
                return;
            case 'allow-unimplemented':
                $this->allowUnimplemented = true;
                return;
            case 'path-mode':
                $this->pathMode = $v;
                return;
            case 'sequential':
                $this->sequential = true;
                return;
            case 'workers':
                if ($v === '' || \strspn($v, '0123456789') !== \strlen($v)) {
                    throw new ConfigException('The "--workers" option must be a number (0 = one per core, 1 = sequential).');
                }
                $this->workers = (int) $v;
                return;
        }
        throw new ConfigException('The "--' . $name . '" option does not exist.');
    }
}
