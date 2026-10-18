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
    $ok = Gap::isTrivia($s->gapBefore[$s->n]);
    for ($i = 0; $i < $s->n && $ok; $i++) {
        $ok = Gap::isTrivia($s->gapBefore[$i]) && $s->gapFlags[$i] === Gap::flags($s->gapBefore[$i]);
    }
    check($ok, "gaps are pure trivia $f");
    check($s->join() === $code, "roundtrip (clean) $f");
    check($s->joinAll() === $code, "roundtrip (rebuilt from the columns) $f");

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
        if ($p > $i) {
            // opener: owner set, parent below it, sep chain walks the top-level commas and ends at the closer
            if ($st->owner[$i] === Structure::OW_NONE || $st->owner[$p] !== $st->owner[$i] || $st->parent[$i] !== $st->parent[$p]) {
                $bad++;
            }
            $c = $st->sep[$i];
            $guard = 0;
            while ($c !== $p && $guard++ < $s->n) {
                if ($c <= $i || $c >= $p || $s->kind[$c] !== 44 || $st->parent[$c] !== $i) {
                    $bad++;
                    break;
                }
                $c = $st->sep[$c];
            }
        } elseif ($p < 0 && $s->kind[$i] === 44 && $st->parent[$i] >= 0 && $st->sep[$i] < 0) {
            $bad++; // a comma inside a bracket always has a separator successor
        }
        if ($p < 0 && $st->owner[$i] !== Structure::OW_NONE) {
            $bad++;
        }
        if ($st->parent[$i] >= 0 && $st->depth[$i] !== $st->depth[$st->parent[$i]] + 1) {
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
        $kinds[] = $st->owner[$i];
    }
}
check($kinds === [Structure::OW_CLASS_BODY, Structure::OW_FUNC_BODY, Structure::OW_CONTROL], 'brace owners');
check($s->isLineStart(0) && $s->isLineStart(1), 'line start after open tag');
check($s->lineIndent(0) === '' && $s->lineIndent(5) === '    ', 'lineIndent');
$s->setGap(1, "\n\n");
check($s->isLineStart(1) && !$s->isLineStart(2), 'isLineStart follows rewritten gaps');
$s->setGap(2, "\n\t\t");
check($s->lineIndent(2) === "\t\t" && $s->lineIndent(3) === "\t\t", 'lineIndent follows rewritten gaps');
/** OW_* of every opener (in order) of a snippet */
function owners(string $code): string
{
    $s = Source::parse($code);
    $st = Structure::build($s);
    $o = [];
    for ($i = 0; $i < $s->n; $i++) {
        if ($st->pair[$i] > $i) {
            $o[] = $st->owner[$i];
        }
    }
    return \implode(',', $o);
}

check(owners("<?php\nfunction f(\$a, \$b = [1, 2]) { return g(\$a)[0]; }") === '20,40,2,21,42', 'owners: function, params, array, call, index'
    . ' got ' . owners("<?php\nfunction f(\$a, \$b = [1, 2]) { return g(\$a)[0]; }"));
check(owners("<?php\n\$f = function (\$x) use (\$y): int { return 1; }; \$g = static fn(int \$a) => \$a;") === '20,24,3,20', 'owners: closure, use, fn');
check(owners("<?php\nclass A extends B { public function __construct(public int \$a = 1) {} use T { a as b; } }") === '1,20,2,8', 'owners: class, ctor params, trait adapt'
    . ' got ' . owners("<?php\nclass A extends B { public function __construct(public int \$a = 1) {} use T { a as b; } }"));
check(owners("<?php\nif (\$a) { } elseif (\$b) { } else { } foreach (\$x as [\$p, \$q]) { switch (\$p) { } }") === '22,4,22,4,4,22,41,4,22,4', 'owners: control structures, destructuring'
    . ' got ' . owners("<?php\nif (\$a) { } elseif (\$b) { } else { } foreach (\$x as [\$p, \$q]) { switch (\$p) { } }"));
check(owners("<?php\n\$r = match (\$x) { 1 => f(a: 2), default => new class(1) { }, }; \$o->{\$n}(...); \"{\$a->b} \${c}\";") === '23,5,21,21,1,11,21,12,12', 'owners: match, args, anon class, dyn brace, string curly'
    . ' got ' . owners("<?php\n\$r = match (\$x) { 1 => f(a: 2), default => new class(1) { }, }; \$o->{\$n}(...); \"{\$a->b} \${c}\";"));
check(owners("<?php\nuse A\\{B, C}; #[Attr(1)] class H { public int \$p { get => 1; set { \$this->p = \$value; } } }") === '10,43,21,1,9,7', 'owners: group import, attribute, property hook'
    . ' got ' . owners("<?php\nuse A\\{B, C}; #[Attr(1)] class H { public int \$p { get => 1; set { \$this->p = \$value; } } }"));

// sep: elements of a call
$s = Source::parse("<?php f(1, [2, 3], g(4, 5), 6);");
$st = Structure::build($s);
$cnt = 0;
$open = 2;
for ($c = $st->sep[$open]; ; $c = $st->sep[$c]) {
    $cnt++;
    if ($c === $st->pair[$open]) {
        break;
    }
}
check($cnt === 4, "sep chain: 4 arguments, got $cnt");

// statement segments
$s = Source::parse("<?php\nswitch (\$x) {\n    case 1: f(); break;\n    default: g();\n}\nlabel: \$y = \$a->{'k'} ?: 2;\nif (\$z): h(); else: k(); endif;\n?>\nhtml\n<?php echo 1;");
$st = Structure::build($s);
$starts = [];
for ($i = 0; $i < $s->n; $i++) {
    if ($st->stmtStart[$i] === $i) {
        $starts[] = \substr($s->text($i), 0, 6);
    }
}
check(\implode('|', $starts) === "<?php\n|switch|case|f|break|defaul|g|}|label|\$y|if|h|else|k|endif|?>\n|html\n|<?php |echo", 'statement starts, got ' . \implode('|', $starts));

$ntw = ['no_trailing_whitespace'];
check(fmt("<?php\n\$a = 1;   \n", $ntw) === "<?php\n\$a = 1;\n", 'ntw basic');
check(fmt("<?php \n\$a;", $ntw) === "<?php\n\$a;", 'ntw open tag space');
check(fmt("<?php\n\$a;  ", $ntw) === "<?php\n\$a;", 'ntw eof');
check(fmt("<?php\n\$a; // c  \n", $ntw) === "<?php\n\$a; // c\n", 'ntw comment tail moves into whitespace');
check(fmt("<?php\r\n\$a = 1;\r\n", ['line_ending']) === "<?php\n\$a = 1;\n", 'line_ending crlf');
check(fmt("<?php\n\$a;\n   \n\$b;\n", ['no_whitespace_in_blank_line']) === "<?php\n\$a;\n\n\$b;\n", 'nwbl');
check(\count(Engine::groups(Engine::sort([Rules::make('no_trailing_whitespace', "\n"), Rules::make('line_ending', "\n"), Rules::make('no_whitespace_in_blank_line', "\n")]))) === 1, 'the three P0 rules fuse into one pass');

echo $fails === 0 ? "OK\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
