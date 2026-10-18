<?php

declare(strict_types=1);

// The fork owns the corpus and the upstream worktree (php-cs-fixer 3.95.27 + its vendor); nothing is copied.
const FORK = '/Users/taras/var/projects/php-cs-fixer-ds';
const ZEND_FIXER = FORK.'/bench/work/upstream/php-cs-fixer';
const CORPUS = FORK.'/bench/work/corpus-src';

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
