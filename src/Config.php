<?php

declare(strict_types=1);

namespace Fmt;

/** What a `.manticore-fmt.{json,yaml,yml}` file (or the command line) says; every field has upstream's default. */
final class Config
{
    /** @var array<string, mixed> name => bool | options, as written (sets unexpanded) */
    public array $rules = [];
    public bool $rulesGiven = false;
    public bool $risky = false;
    public string $indent = '    ';
    public string $lineEnding = "\n";
    /** @var string[] roots (directories or files), absolute or relative to the config file's directory, already joined */
    public array $paths = [];
    /** @var string[] directories pruned during the walk (a name matches at any depth, a/b only that path); null-equivalent default `vendor` */
    public array $exclude = ['vendor'];
    /** @var string[] file name globs; any must match */
    public array $name = ['*.php'];
    /** @var string[] */
    public array $notName = [];
    /** @var string[] relative-path patterns; when set, one must match */
    public array $path = [];
    /** @var string[] */
    public array $notPath = [];
    public bool $ignoreDotFiles = true;
    public bool $cache = true;
    public string $cacheFile = '';
    /** 0 = one worker per core, 1 = sequential */
    public int $workers = 0;
    public int $chunkSize = 0;
    public string $format = 'txt';
    public bool $allowUnimplemented = false;
    public string $pathMode = 'override';
    public string $file = '';
}
