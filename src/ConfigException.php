<?php

declare(strict_types=1);

namespace Fmt;

/** A configuration problem; `$code` is the exit-status flag upstream gives it (16 invalid config, 32 invalid fixer config). */
final class ConfigException extends \RuntimeException
{
    public const INVALID_CONFIG = 16;
    public const INVALID_FIXER_CONFIG = 32;

    public function __construct(string $message, int $code = self::INVALID_CONFIG)
    {
        parent::__construct($message, $code);
    }
}
