<?php

declare(strict_types=1);

// usage: bin/fmt-test <corpus-dir>
// 1. round trip (the first test): join(tokens, gaps) == input, byte for byte, for every file
// 2. Structure invariants  3. a few hand-written rule cases (byte parity vs upstream is bench/oracle.php)

use Fmt\Engine;
use Fmt\Files;
use Fmt\Gap;
use Fmt\GapRule;
use Fmt\Rules;
use Fmt\Source;
use Fmt\Structure;

$fails = 0;
function check(bool $ok, string $what): void
{
    global $fails;
    if (!$ok) {
        $fails++;
        echo "FAIL $what\n";
    }
}

/** @param string[] $names */
function fmt(string $code, array $names): string
{
    $rules = [];
    foreach ($names as $nm) {
        $r = Rules::make($nm, "\n");
        if ($r !== null) {
            $rules[] = $r;
        }
    }
    $s = Source::parse($code);
    Engine::run($s, Engine::sort($rules));
    return $s->join();
}

$files = Files::collect([$argv[1] ?? 'bench/work/corpus-src']);
check(\count($files) > 0, 'corpus is empty');
$tokens = 0;
foreach ($files as $f) {
    $code = (string) \file_get_contents($f);
    $s = Source::parse($code);
    $tokens += $s->n;
    // the join cannot notice a lexer that swallows code into trivia, so check the gaps hold nothing but trivia
    $ok = Gap::isTrivia($s->gapAfterLast);
    for ($i = 0; $i < $s->n && $ok; $i++) {
        $ok = Gap::isTrivia($s->gapBefore[$i]);
    }
    check($ok, "gaps are pure trivia $f");
    check($s->join() === $code, "roundtrip (clean) $f");
    $s->dirty = true;
    check($s->join() === $code, "roundtrip (forced rebuild) $f");

    $st = Structure::build($s);
    $bad = 0;
    for ($i = 0; $i < $s->n; $i++) {
        $p = $st->pair[$i];
        if ($p >= 0 && $st->pair[$p] !== $i) {
            $bad++;
        }
        if ($st->stmtStart[$i] > $i || $st->stmtEnd[$i] < $i) {
            $bad++;
        }
        if ($st->depth[$i] < 0) {
            $bad++;
        }
    }
    check($bad === 0, "structure invariants $f");
}
echo 'roundtrip: ' . \count($files) . " files, $tokens tokens\n";

$s = Source::parse("<?php\nclass A {\n    function f(\$x) {\n        if (\$x) { return [1]; }\n    }\n}\n");
$st = Structure::build($s);
$kinds = [];
for ($i = 0; $i < $s->n; $i++) {
    if ($s->kind[$i] === 123) {
        $kinds[] = $st->blockKind[$i];
    }
}
check($kinds === [Structure::BK_CLASS, Structure::BK_FUNCTION, Structure::BK_CONTROL], 'blockKind');
check($st->lineStart[0] === 1 && $st->lineStart[1] === 1, 'lineStart after open tag');

$ntw = ['no_trailing_whitespace'];
check(fmt("<?php\n\$a = 1;   \n", $ntw) === "<?php\n\$a = 1;\n", 'ntw basic');
check(fmt("<?php \n\$a;", $ntw) === "<?php\n\$a;", 'ntw open tag space');
check(fmt("<?php\n\$a;  ", $ntw) === "<?php\n\$a;", 'ntw eof');
check(fmt("<?php\n\$a; // c  \n", $ntw) === "<?php\n\$a; // c\n", 'ntw comment tail moves into whitespace');
check(fmt("<?php\r\n\$a = 1;\r\n", ['line_ending']) === "<?php\n\$a = 1;\n", 'line_ending crlf');
check(fmt("<?php\n\$a;\n   \n\$b;\n", ['no_whitespace_in_blank_line']) === "<?php\n\$a;\n\n\$b;\n", 'nwbl');
check(\count(Engine::groups(Engine::sort([Rules::make('no_trailing_whitespace', "\n"), Rules::make('line_ending', "\n")]))) === 2, 'neighbour rules do not fuse');

echo $fails === 0 ? "OK\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
