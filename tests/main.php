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
    $s = Engine::run($s, Engine::sort($rules));
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

// wave 3: one line per rule family (byte parity vs upstream is bench/oracle.php)
check(fmt("<?php\nIF (\$a) { RETURN; } ELSE { EXIT; }\n", ['lowercase_keywords']) === "<?php\nif (\$a) { return; } else { exit; }\n", 'lowercase_keywords');
check(fmt("<?php\n\$a = Foo::CLASS . (INT) \$b;\n", ['lowercase_keywords', 'lowercase_cast']) === "<?php\n\$a = Foo::class . (int) \$b;\n", 'lowercase_keywords: ::CLASS, lowercase_cast');
check(fmt("<?php\n\$a = (Boolean) \$b + (BINARY) \$c;\n", ['short_scalar_cast']) === "<?php\n\$a = (bool) \$b + (string) \$c;\n", 'short_scalar_cast');
check(fmt("<?php\n\$a = TRUE . Foo::NULL . \$o->False . null;\n", ['constant_case']) === "<?php\n\$a = true . Foo::NULL . \$o->False . null;\n", 'constant_case');
check(fmt("<?php\nswitch (\$a) { case 1; break; default; break; }\n", ['switch_case_semicolon_to_colon']) === "<?php\nswitch (\$a) { case 1: break; default: break; }\n", 'switch_case_semicolon_to_colon');
check(fmt("<?php\nFoo :: bar( \$a ) ; baz (1);\n", ['no_space_around_double_colon', 'spaces_inside_parentheses', 'no_spaces_after_function_name'])
    === "<?php\nFoo::bar(\$a) ; baz(1);\n", 'double colon, parentheses, function name');
check(fmt("<?php\nclass  A  extends  B  implements  C ,  D  {  }\n", ['class_definition']) === "<?php\nclass A extends B implements C, D\n{  }\n", 'class_definition');
check(fmt("<?php\nfunction  f ( \$a )  :  int{}\n", ['function_declaration', 'return_type_declaration']) === "<?php\nfunction f(\$a): int {}\n", 'function_declaration + return_type_declaration');
check(fmt("<?php\nnamespace A;\nuse B;use function C;\nclass D {}\n", ['blank_line_after_namespace', 'single_line_after_imports', 'blank_line_between_import_groups'])
    === "<?php\nnamespace A;\n\nuse B;\n\nuse function C;\n\nclass D {}\n", 'namespace and import blank lines');
check(fmt("<?php \$a = [\n1,\n   2,\n];\n", ['array_indentation', 'blank_line_after_opening_tag']) === "<?php\n\n\$a = [\n    1,\n    2,\n];\n", 'array_indentation, blank_line_after_opening_tag');
check(fmt("<?php\n\$a = 1; \$b = 2;\n", ['no_multiple_statements_per_line']) === "<?php\n\$a = 1;\n\$b = 2;\n", 'no_multiple_statements_per_line');
check(Rules::make('encoding', "\n")?->preParse("\xEF\xBB\xBF<?php\n") === "<?php\n", 'encoding drops a BOM');
check(\count(Engine::groups(Engine::sort(\array_map(static fn (string $n): GapRule => Rules::make($n, "\n"), ['lowercase_keywords', 'constant_case', 'no_space_around_double_colon',
    'spaces_inside_parentheses', 'return_type_declaration', 'types_spaces'])))) === 1, 'text and gap rules fuse into one pass');
$s = Source::parse("<?php\nfunction f(\$a, ) {}\n");
Engine::run($s, Engine::sort([Rules::make('function_declaration', "\n")]));
check($s->unsupported !== '', 'function_declaration reports a trailing comma it cannot delete');
// equal priorities run in the order of the rule names (upstream's FixerFactory), whatever order they are given in
$ord = [];
foreach (Engine::sort([Rules::make('no_extra_blank_lines', "\n"), Rules::make('line_ending', "\n"), Rules::make('blank_line_after_namespace', "\n"), Rules::make('binary_operator_spaces', "\n")]) as $r) {
    $ord[] = $r->name();
}
check(\implode(',', $ord) === 'line_ending,blank_line_after_namespace,no_extra_blank_lines,binary_operator_spaces', 'rule order: priority, then name, got ' . \implode(',', $ord));

// edit phase: token edits rebuild the Source, the rules after see the re-parsed file
check(fmt("<?php\nif (\$a) {\n} else  if (\$b) {\n}\n", ['elseif']) === "<?php\nif (\$a) {\n} elseif (\$b) {\n}\n", 'elseif merges else if');
check(fmt("<?php\nif (\$a) {\n} else // c\n if (\$b) {\n}\n", ['elseif']) === "<?php\nif (\$a) {\n} elseif// c\n (\$b) {\n}\n", 'elseif drops the blank after if behind a comment');
check(fmt("<?php\necho 1\n?>\n", ['no_closing_tag', 'single_blank_line_at_eof']) === "<?php\necho 1;\n", 'no_closing_tag inserts ;, the next rule sees the rebuilt file');
check(fmt("<?php\nif (\$a) {\n} else/* c */ if  (\$b) {\n}\n", ['elseif', 'single_space_around_construct']) === "<?php\nif (\$a) {\n} elseif   (\$b) {\n}\n",
    'elseif: the blanks before and after the old `if` stay two whitespace tokens (single_space_around_construct sets only the first)');
check(fmt("<?php\nuse \\A;\nuse function \\f;\n", ['no_leading_import_slash']) === "<?php\nuse A;\nuse function f;\n", 'no_leading_import_slash');
check(fmt("<?php\nclass A\n{\n    use B,\n        C, D;\n}\n", ['single_trait_insert_per_statement']) === "<?php\nclass A\n{\n    use B;\n    use C;use D;\n}\n", 'single_trait_insert_per_statement');
check(fmt("<?php\n\$a = new A;\n\$b = new class {};\n", ['new_with_parentheses']) === "<?php\n\$a = new A();\n\$b = new class() {};\n", 'new_with_parentheses');
check(fmt("<?php\nif (\$a) b(); else c();\n", ['control_structure_braces']) === "<?php\nif (\$a) { b(); } else { c(); }\n", 'control_structure_braces');
check(fmt("<?php\nuse A, B;\nuse C\\{D, E};\n", ['single_import_per_statement']) === "<?php\nuse A;\nuse B;\nuse C\\D;\nuse C\\E;\n", 'single_import_per_statement');
check(fmt("<?php\nclass A\n{\n    static public function f() {}\n    var \$a;\n    const B = 1;\n}\n", ['modifier_keywords'])
    === "<?php\nclass A\n{\n    public static function f() {}\n    public \$a;\n    public const B = 1;\n}\n", 'modifier_keywords');
check(fmt("<?php\nclass A\n{\n    private static \$a, \$b = [1, 2];\n}\n", ['single_class_element_per_statement'])
    === "<?php\nclass A\n{\n    private static \$a;\n    private static \$b = [1, 2];\n}\n", 'single_class_element_per_statement');
$s = Source::parse("<?php\n\$f = function () {} ?>");
$s = Engine::run($s, Engine::sort([Rules::make('no_closing_tag', "\n")]));
check($s->abort, 'no_closing_tag: a closure before ?> would not lint');

echo $fails === 0 ? "OK\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
