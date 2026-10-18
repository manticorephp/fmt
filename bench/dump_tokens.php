<?php
// Zend side of the token parity check: php -d xdebug.mode=off bench/dump_tokens.php <dir|file>... > zend.txt
// Per file `== path`, then per non-trivia upstream token (PhpCsFixer\Tokenizer\Tokens::fromCode) `id<TAB>escaped content`.
// id = the token id (CT::* for custom tokens), or the character code for a plain one-char token.
require '/Users/taras/var/projects/php-cs-fixer-ds/bench/work/upstream/vendor/autoload.php';

use PhpCsFixer\Tokenizer\Tokens;

function collect(string $p, array &$out): void
{
    if (is_file($p)) {
        $out[] = $p;
        return;
    }
    $names = scandir($p);
    sort($names);
    foreach ($names as $n) {
        if ($n === '.' || $n === '..') {
            continue;
        }
        if (is_dir("$p/$n")) {
            collect("$p/$n", $out);
        } elseif (str_ends_with($n, '.php')) {
            $out[] = "$p/$n";
        }
    }
}

$files = [];
foreach (array_slice($argv, 1) as $p) {
    collect(rtrim($p, '/'), $files);
}
foreach ($files as $f) {
    echo "== $f\n";
    try {
        $tokens = Tokens::fromCode((string) file_get_contents($f));
    } catch (Throwable $e) {
        echo "PARSE ERROR\n";
        continue;
    }
    foreach ($tokens as $t) {
        if ($t->isWhitespace() || $t->isComment()) {
            continue;
        }
        $id = $t->getId() ?? ord($t->getContent());
        echo $id, "\t", addcslashes($t->getContent(), "\0..\37\\\177..\377"), "\n";
    }
}
