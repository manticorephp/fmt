<?php

declare(strict_types=1);

// Environment: UPSTREAM_DIR = a php-cs-fixer 3.95.27 checkout with its vendor/ (default bench/work/upstream); CORPUS_DIR = the 276-file corpus (default bench/work/corpus-src). Dev tooling only: CI never needs php.
define('UPSTREAM', getenv('UPSTREAM_DIR') ?: __DIR__.'/work/upstream');
define('ZEND_FIXER', UPSTREAM.'/php-cs-fixer');
define('CORPUS', getenv('CORPUS_DIR') ?: __DIR__.'/work/corpus-src');

/** @return list<string> relative paths of every *.php under $dir, sorted */
function phpFiles(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fi) {
        if ('php' === $fi->getExtension()) {
            $out[] = substr($fi->getPathname(), strlen($dir) + 1);
        }
    }
    sort($out);

    return $out;
}

function copyTree(string $from, string $to): void
{
    foreach (phpFiles($from) as $rel) {
        @mkdir(dirname($to.'/'.$rel), 0777, true);
        copy($from.'/'.$rel, $to.'/'.$rel);
    }
}

function rmTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $fi) {
        $fi->isDir() ? rmdir($fi->getPathname()) : unlink($fi->getPathname());
    }
    rmdir($dir);
}
