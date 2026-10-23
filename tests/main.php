<?php

declare(strict_types=1);

// usage: bin/fmt-test <corpus-dir>
// 1. round trip (the first test): join(tokens, gaps) == input, byte for byte, for every file
// 2. Structure invariants  3. a few hand-written rule cases (byte parity vs upstream is bench/oracle.php)

use Fmt\Aliases;
use Fmt\Ansi;
use Fmt\Cache;
use Fmt\Cli;
use Fmt\Config;
use Fmt\ConfigException;
use Fmt\ConfigLoader;
use Fmt\Engine;
use Fmt\Fixers;
use Fmt\FileFinder;
use Fmt\Glob;
use Fmt\Notices;
use Fmt\Outcome;
use Fmt\Pool;
use Fmt\Processor;
use Fmt\Progress;
use Fmt\Stats;
use Fmt\Reporter;
use Fmt\RulePlan;
use Fmt\RuleSets;
use Fmt\Yaml;
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

/** the JSON of a parsed YAML text, or `ERR <message>` */
function yj(string $src): string
{
    try {
        return (string) \json_encode(Yaml::parse($src));
    } catch (\InvalidArgumentException $e) {
        return 'ERR ' . $e->getMessage();
    }
}

/** @param string[] $lines */
function ln(array $lines): string
{
    return \implode("\n", $lines) . "\n";
}

/** the message a config file raises, '' when it loads */
function cfgErr(string $file): string
{
    try {
        ConfigLoader::load($file);
    } catch (ConfigException $e) {
        return $e->getMessage();
    }
    return '';
}

function rmrf(string $d): void
{
    if (\is_dir($d) && !\is_link($d)) {
        foreach (\scandir($d) as $e) {
            if ($e !== '.' && $e !== '..') {
                rmrf($d . '/' . $e);
            }
        }
        @\rmdir($d);
    } elseif (\file_exists($d)) {
        @\unlink($d);
    }
}

$files = Files::collect([$argv[1] ?? 'bench/work/corpus-src']);
if (\count($files) === 0) {
    echo "FAIL corpus is empty (usage: bin/fmt-test <corpus-dir>)\n";
    exit(1);
}
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
check(fmt("<?php\n\$a = array(1, array(2));\n", ['array_syntax']) === "<?php\n\$a = [1, [2]];\n", 'array_syntax');
check(fmt("<?php\n\$a = [\n    1\n];\n", ['trailing_comma_in_multiline']) === "<?php\n\$a = [\n    1,\n];\n", 'trailing_comma_in_multiline');
check(fmt("<?php\nfunction f(int|null \$a): null|A {}\n", ['nullable_type_declaration']) === "<?php\nfunction f(?int \$a): ?A {}\n", 'nullable_type_declaration');
check(fmt("<?php\nfunction f(B|A|null \$a) {}\n", ['ordered_types']) === "<?php\nfunction f(null|A|B \$a) {}\n", 'ordered_types');
$s = Source::parse("<?php\nclass A\n{\n    public NULL|string|Foo \$b, \$c;\n}\n");
$s = Engine::run($s, Engine::sort([Rules::make('ordered_types', "\n", ['null_adjustment' => 'always_last'])]));
check($s->abort, 'ordered_types: the later name of a property list is typed `NULL$b,`, moving it would not lint');
check(fmt("<?php\nuse function f;\nuse B;\nuse A;\n", ['ordered_imports']) === "<?php\nuse A;\nuse B;\nuse function f;\n", 'ordered_imports');
check(fmt("<?php\nclass A\n{\n    public function f() {}\n    use T;\n}\n", ['ordered_class_elements']) === "<?php\nclass A\n{\n    use T;\n    public function f() {}\n}\n", 'ordered_class_elements');
check(fmt("<?php\nnamespace A;\n\nuse B\\C;\nuse B\\D;\nuse A\\E;\n\nnew C();\n", ['no_unused_imports']) === "<?php\nnamespace A;\n\nuse B\\C;\nnew C();\n", 'no_unused_imports');
$s = Source::parse("<?php\n\$f = function () {} ?>");
$s = Engine::run($s, Engine::sort([Rules::make('no_closing_tag', "\n")]));
check($s->abort, 'no_closing_tag: a closure before ?> would not lint');

// ---- @Symfony rules that are not phpdoc (SymRules) ----
check(fmt("<?php\necho __Line__, Foo::CLASS, 0Xff;\nclass A { function __TOSTRING(): INT {} }\n", ['magic_constant_casing', 'integer_literal_case', 'magic_method_casing', 'native_type_declaration_casing'])
    === "<?php\necho __LINE__, Foo::class, 0xFF;\nclass A { function __toString(): int {} }\n", 'casing rules');
check(fmt("<?php\n\$a = DIE; if (\$a <> 1) { \$b = !!\$a; }\n", ['no_alias_language_construct_call', 'standardize_not_equals', 'no_short_bool_cast'])
    === "<?php\n\$a = exit; if (\$a != 1) { \$b = (bool)\$a; }\n", 'alias / cast rules');
check(fmt("<?php \$a = 1;\n\$b = b'x';\n  namespace A;\n", ['linebreak_after_opening_tag', 'no_binary_string', 'no_leading_namespace_whitespace'])
    === "<?php\n\$a = 1;\n\$b = 'x';\nnamespace A;\n", 'tag / string / namespace whitespace rules');
check(fmt("<?php\n\$a = 1 ;\nfor ( ; ; ) {}\nfor (\$i = 0;  \$i < 3;\$i++) {}\n", ['no_singleline_whitespace_before_semicolons', 'space_after_semicolon'])
    === "<?php\n\$a = 1;\nfor (; ; ) {}\nfor (\$i = 0; \$i < 3; \$i++) {}\n", 'semicolon spacing rules');
check(fmt("<?php\n\$x = [ 1 , 2,3 ];\n\$y = \$a [ 0 ] -> b;\n", ['trim_array_spaces', 'no_whitespace_before_comma_in_array', 'whitespace_after_comma_in_array', 'no_spaces_around_offset', 'object_operator_without_whitespace'])
    === "<?php\n\$x = [1, 2, 3];\n\$y = \$a[0]->b;\n", 'array / offset / object operator spacing rules');
check(fmt("<?php\ndeclare ( strict_types=1 );\n//x\n\$a = ['a'\n => 1];\nuse A\\B as B;\nf(1, 2,);\n", ['declare_parentheses', 'single_line_comment_spacing', 'no_multiline_whitespace_around_double_arrow', 'no_unneeded_import_alias', 'no_trailing_comma_in_singleline'])
    === "<?php\ndeclare(strict_types=1);\n// x\n\$a = ['a' => 1];\nuse A\\B  ;\nf(1, 2);\n", 'declare / comment / double arrow / import alias / trailing comma rules');
check(fmt("<?php\n# a\n/* b */\nprint 'x';\n?>\n<?= \$a ?>\n", ['single_line_comment_style', 'no_mixed_echo_print', 'semicolon_after_instruction', 'echo_tag_syntax'])
    === "<?php\n// a\n// b\necho 'x';\n?>\n<?php echo \$a; ?>\n", 'comment style / echo / tag rules');
check(fmt("<?php\n\$a = 1;;\n{ { echo 2; } }\nwhile (f()) {}\nfunction g() { foo(); return; }\n\$this?->a;\n", ['no_empty_statement', 'no_unneeded_braces', 'empty_loop_body', 'no_useless_return', 'no_useless_nullsafe_operator'])
    === "<?php\n\$a = 1;\n  echo 2;  \nwhile (f()) ;\nfunction g() { foo();  }\n\$this->a;\n", 'empty statement / braces / loop body / return / nullsafe rules');
check(fmt("<?php\nswitch (\$a) { case 1: continue; }\n", ['switch_continue_to_break']) === "<?php\nswitch (\$a) { case 1: break; }\n", 'switch_continue_to_break');
check(fmt("<?php\nrequire ( 'a.php' ) ;\nfor (;;) { throw new E(\n'x'); }\nclass A { public \$a = null; }\n", ['include', 'empty_loop_condition', 'single_line_throw', 'no_null_property_initialization'])
    === "<?php\nrequire 'a.php';\n  while (true){ throw new E('x'); }\nclass A { public \$a; }\n", 'include / loop condition / throw / null property rules');
check(fmt("<?php\n\$a = \"x\\\"y\" . \"\${b}\" . `ls \$c`;\n", ['single_quote', 'simple_to_complex_string_variable', 'backtick_to_shell_exec'])
    === "<?php\n\$a = 'x\"y' . \"{\$b}\" . shell_exec(\"ls \$c\");\n", 'string rules');
check(fmt("<?php\nSTRLEN(\$a);\n\$x->STRLEN();\nnew \\exception();\n", ['native_function_casing', 'class_reference_name_casing']) === "<?php\nstrlen(\$a);\n\$x->STRLEN();\nnew \\Exception();\n", 'native function / class reference casing');
check(fmt("<?php\n\$i += 1;\n\$j++;\n\$a = (unset) \$b;\n", ['standardize_increment', 'increment_style', 'no_unset_cast']) === "<?php\n++\$i;\n++\$j;\n\$a =  null;\n", 'increment / unset cast rules');
check(fmt("<?php\nfinal class A { protected \$a; }\nfunction f(\$a) {\n    if (\$a) { return 1; } else { foo(); }\n    //\n}\n", ['protected_to_private', 'no_useless_else', 'no_empty_comment'])
    === "<?php\nfinal class A { private \$a; }\nfunction f(\$a) {\n    if (\$a) { return 1; }   foo(); \n    \n}\n", 'protected / else / empty comment rules');
check(fmt("<?php\nreturn (\$a);\necho (\$b);\n\$x = clone (\$y);\n", ['no_unneeded_control_parentheses']) === "<?php\nreturn \$a;\necho \$b;\n\$x = clone \$y;\n", 'no_unneeded_control_parentheses');
check(fmt("<?php\nfunction f(int\$a, ?array\$b) {}\nclass A { public int\$p; }\n", ['type_declaration_spaces']) === "<?php\nfunction f(int \$a, ?array \$b) {}\nclass A { public int\$p; }\n", 'type_declaration_spaces (the property keeps its stale index after the insertions, as upstream)');
check(fmt("<?php\n\$a = 'a' . 'b' . \$c . \"d\" . \"e\";\n", ['no_useless_concat_operator']) === "<?php\n\$a = 'ab' . \$c . \"de\";\n", 'no_useless_concat_operator');
check(fmt("<?php\nfunction f() {\n    \$a = 1;\n    return \$a;\n}\n", ['blank_line_before_statement']) === "<?php\nfunction f() {\n    \$a = 1;\n\n    return \$a;\n}\n", 'blank_line_before_statement');
check(fmt("<?php\n\$f = function () use (\$a, \$b) { return \$b; };\n", ['lambda_not_used_import']) === "<?php\n\$f = function () use ( \$b) { return \$b; };\n", 'lambda_not_used_import');
check(fmt("<?php\nclass A {\n    function a() {}\n    function b() {}\n}\n", ['class_attributes_separation']) === "<?php\nclass A {\n    function a() {}\n\n    function b() {}\n}\n", 'class_attributes_separation');
check(fmt("<?php\nif (\$a === 1 && \$b != null) { return; }\n", ['yoda_style']) === "<?php\nif (1 === \$a && null != \$b) { return; }\n", 'yoda_style');
check(fmt("<?php\nif (\$a):\n    foo();\nelse:\n    bar();\nendif;\n", ['no_alternative_syntax']) === "<?php\nif (\$a) {\n    foo();\n} else {\n    bar();\n}\n", 'no_alternative_syntax');
check(fmt("<?php\nclass ATest extends TestCase { public function test_a_b() {} }\n", ['php_unit_method_casing']) === "<?php\nclass ATest extends TestCase { public function testAB() {} }\n", 'php_unit_method_casing');
check(fmt("<?php\nfunction f(int \$a = null, A|B \$b = null, ?int \$c = null) {}\n", ['nullable_type_declaration_for_default_null_value']) === "<?php\nfunction f(?int \$a = null, A|B|null \$b = null, ?int \$c = null) {}\n", 'nullable_type_declaration_for_default_null_value');
// ---- CLI layer: YAML subset, globs, sets, aliases, plan validation, config files, finder, reporters, cache, pool ----
$tmp = \sys_get_temp_dir() . '/mfmt-test-' . \getmypid();
@\mkdir($tmp . '/src/sub', 0777, true);
@\mkdir($tmp . '/vendor/x', 0777, true);
@\mkdir($tmp . '/.hidden', 0777, true);
\file_put_contents($tmp . '/src/a.php', "<?php\n\n\$a = 1;  \n");
\file_put_contents($tmp . '/src/sub/b.php', "<?php\n\n\$b = 2;\n");
\file_put_contents($tmp . '/src/sub/b.inc', "<?php\n\$b = 2;  \n");
\file_put_contents($tmp . '/src/notes.txt', 'x');
\file_put_contents($tmp . '/vendor/x/v.php', "<?php\n\$v = 1;  \n");
\file_put_contents($tmp . '/.hidden/h.php', "<?php\n\$h = 1;  \n");

$y = Yaml::parse("# c\nrules:\n  '@PSR12': true  # x\n  no_extra_blank_lines:\n    tokens: [use, extra]\n  ordered_imports:\n    order:\n      - class\n      - function\nindent: \"\\t\"\nparallel: 4\nlist:\n- a\n- name: b\n  x: 1\nnone:\n");
check(\json_encode($y) === '{"rules":{"@PSR12":true,"no_extra_blank_lines":{"tokens":["use","extra"]},"ordered_imports":{"order":["class","function"]}},"indent":"\t","parallel":4,"list":["a",{"name":"b","x":1}],"none":null}', 'yaml subset: ' . \json_encode($y));
$bad = false;
try {
    Yaml::parse("a:\n  b: 1\n c: 2\n");
} catch (\InvalidArgumentException $e) {
    $bad = true;
}
check($bad, 'yaml: a mis-indented key is refused');

check(Glob::matches('*.php', 'a.php') && !Glob::matches('*.php', 'a/b.php') && Glob::matches('**/Tests/*.php', 'x/y/Tests/a.php')
    && Glob::matches('**/Tests/*.php', 'Tests/a.php') && Glob::matches('*.{php,inc}', 'a.inc') && Glob::matches('/\.dist$/', 'x.dist'), 'glob');
check(Glob::pathMatches('Tests', 'a/Tests/b.php') && !Glob::pathMatches('Tests', 'a/b.php') && Glob::pathMatches('src/*/x.php', 'src/q/x.php'), 'path patterns');

// sets: upstream's resolveSet
$r = RuleSets::resolve(['@PSR12' => true, 'braces_position' => false, 'line_ending' => ['x' => 1]]);
check(!isset($r['braces_position']) && $r['line_ending'] === ['x' => 1] && isset($r['no_trailing_whitespace']), 'sets: a later entry replaces, false removes');
check(RuleSets::resolve(['@PSR12' => false]) === [], 'sets: @set => false switches the whole set off');
$bad = false;
try {
    RuleSets::resolve(['@Nope' => true]);
} catch (\InvalidArgumentException $e) {
    $bad = $e->getMessage() === 'Set "@Nope" does not exist.';
}
check($bad, 'sets: unknown set');
check(\count(RuleSets::resolve(['@Symfony' => true])) === 151 && \count(RuleSets::resolve(['@PER-CS3.0' => true])) === 62, 'sets: sizes match upstream');
check(RuleSets::resolve(['@PSR12' => true])['braces_position'] === ['allow_single_line_anonymous_functions' => false, 'allow_single_line_empty_anonymous_classes' => true]
    && RuleSets::resolve(['@Symfony' => true])['braces_position']['allow_single_line_anonymous_functions'] === true, 'sets: options follow the later set');

// aliases
$a = Aliases::target('no_extra_consecutive_blank_lines', ['tokens' => ['use']]);
check($a !== null && $a[0] === 'no_extra_blank_lines' && $a[1] === ['tokens' => ['use']], 'alias: v2 name forwards its options');
$a = Aliases::target('single_blank_line_before_namespace', true);
check($a !== null && $a[0] === 'blank_lines_before_namespace' && $a[1] === ['min_line_breaks' => 2, 'max_line_breaks' => 2], 'alias: a proxy brings its own options');
$a = Aliases::target('visibility_required', ['elements' => ['method']]);
check($a !== null && $a[0] === 'modifier_keywords' && $a[1] === ['elements' => ['method']], 'alias: deprecated fixer with one successor');
check(Aliases::target('line_ending', true) === null && Aliases::known('yoda_style') && !Aliases::known('nope_rule'), 'alias: plain rules are no alias');
check(Aliases::suggest('no_trailing_whitespce') === 'no_trailing_whitespace', 'alias: did-you-mean');
Notices::clear();

// plan validation
function planError(array $raw, bool $risky = false, bool $skip = false): string
{
    try {
        RulePlan::build($raw, '    ', "\n", $risky, $skip);
    } catch (ConfigException $e) {
        return $e->getCode() . ':' . $e->getMessage();
    }
    return 'ok';
}
check(\str_starts_with(planError(['no_trailing_whitespce' => true]), '16:The rules contain unknown fixers: "no_trailing_whitespce" (did you mean "no_trailing_whitespace"?)'), 'plan: unknown rule');
check(\str_starts_with(planError(['array_push' => true]), '16:The rules contain risky fixers (') || \str_contains(planError(['array_push' => true]), 'risky'), 'plan: risky refused');
Fixers::hide('header_comment', true);
check(\str_starts_with(planError(['header_comment' => true], true), '16:rules not implemented (1): header_comment'), 'plan: unimplemented fails and lists');
check(planError(['header_comment' => true, 'line_ending' => true], true, true) === 'ok', 'plan: --allow-unimplemented skips');
Fixers::hide('header_comment', false);
check(\str_starts_with(planError(['braces_position' => []]), '32:[braces_position] Configuration must be an array'), 'plan: empty options');
check(\str_starts_with(planError(['line_ending' => ['a' => 1]]), '32:[line_ending] Is not configurable.'), 'plan: options for a rule without any');
check(\str_starts_with(planError(['blank_lines_before_namespace' => true, 'no_blank_lines_before_namespace' => true]), '1:Rule contains conflicting fixers:'), 'plan: conflicting fixers');
check(\str_starts_with(planError(['braces_position' => ['bogus' => 1]]), '32:[braces_position]'), 'plan: bad option names the rule');
check(planError(['@PSR12' => true]) === 'ok' && planError(['@PSR12' => true, 'no_extra_consecutive_blank_lines' => true]) === 'ok', 'plan: PSR12, with an alias');
$p = RulePlan::build(['@Symfony' => true], '    ', "\n", true, true);
check(\count($p->rules) >= 62 && \count($p->rules) + \count($p->missing) === 151, 'plan: @Symfony = 151 rules, at least 62 implemented (' . \count($p->rules) . '/' . \count($p->missing) . ')');
Notices::clear();
check(\json_encode(Cli::parseRules('@PSR12,-line_ending')) === '{"@PSR12":true,"line_ending":false}' && \json_encode(Cli::parseRules('{"a":{"b":1}}')) === '{"a":{"b":1}}', 'rules argument');

// config files
\file_put_contents($tmp . '/c.json', '{"rules":{"@PSR12":true},"risky":false,"indent":"tab","line_ending":"crlf","paths":["src"],"exclude":["sub"],"notName":["a.*"],"cache":{"enabled":false},"parallel":2}');
$c = ConfigLoader::load($tmp . '/c.json');
check($c->indent === "\t" && $c->lineEnding === "\r\n" && $c->paths === [$tmp . '/src'] && $c->exclude === ['sub'] && $c->cache === false && $c->workers === 2
    && $c->rules === ['@PSR12' => true], 'config: json');
\file_put_contents($tmp . '/c.yaml', "rules:\n  '@PER-CS': true\n  array_syntax: {syntax: short}\nlineEnding: \"\\n\"\nindent: 2\nname: ['*.php', '*.inc']\ncache: .fmt.cache\nparallel: false\n");
$c = ConfigLoader::load($tmp . '/c.yaml');
check($c->indent === '  ' && $c->name === ['*.php', '*.inc'] && $c->cacheFile === $tmp . '/.fmt.cache' && $c->workers === 1 && $c->rules['array_syntax'] === ['syntax' => 'short'], 'config: yaml');
\file_put_contents($tmp . '/bad.yaml', "rules:\n  - a\n");
$e1 = '';
try {
    ConfigLoader::load($tmp . '/bad.yaml');
} catch (ConfigException $e) {
    $e1 = $e->getMessage();
}
check(\str_contains($e1, 'missing value for "a"'), 'config: a rule list is refused: ' . $e1);
\file_put_contents($tmp . '/bad2.json', '{"nope":1}');
$e1 = '';
try {
    ConfigLoader::load($tmp . '/bad2.json');
} catch (ConfigException $e) {
    $e1 = $e->getMessage();
}
check(\str_contains($e1, 'unknown key "nope"'), 'config: unknown key');
\file_put_contents($tmp . '/.manticore-fmt.dist.json', '{}');
check(ConfigLoader::discover($tmp) === $tmp . '/.manticore-fmt.dist.json', 'config: .dist is found');
\file_put_contents($tmp . '/.manticore-fmt.yaml', "rules:\n  line_ending: true\n");
check(ConfigLoader::discover($tmp) === $tmp . '/.manticore-fmt.yaml', 'config: the non-dist file wins');
\unlink($tmp . '/.manticore-fmt.yaml');
\unlink($tmp . '/.manticore-fmt.dist.json');

// finder
$cfg = new Config();
$names = [];
foreach (FileFinder::find($cfg, [$tmp]) as $f) {
    $names[] = \substr($f, \strlen($tmp) + 1);
}
check($names === ['src/a.php', 'src/sub/b.php'], 'finder: defaults skip vendor, dot dirs and non-php: ' . \implode(',', $names));
$cfg->exclude = [];
$cfg->name = ['*.php', '*.inc'];
$cfg->notPath = ['sub/b.inc'];
$names = [];
foreach (FileFinder::find($cfg, [$tmp . '/src', $tmp . '/vendor']) as $f) {
    $names[] = \substr($f, \strlen($tmp) + 1);
}
check($names === ['src/a.php', 'src/sub/b.php', 'vendor/x/v.php'] || $names === ['src/a.php', 'src/sub/b.php'], 'finder: CLI paths override the config filters: ' . \implode(',', $names));
$cfg = new Config();
$cfg->paths = [$tmp . '/src'];
$cfg->exclude = ['sub'];
$names = [];
foreach (FileFinder::find($cfg, []) as $f) {
    $names[] = \substr($f, \strlen($tmp) + 1);
}
check($names === ['src/a.php'], 'finder: config paths + exclude: ' . \implode(',', $names));
$cfg->pathMode = 'intersection';
$names = [];
foreach (FileFinder::find($cfg, [$tmp . '/src/sub', $tmp . '/src/a.php']) as $f) {
    $names[] = \substr($f, \strlen($tmp) + 1);
}
check($names === ['src/a.php'], 'finder: intersection: ' . \implode(',', $names));

// processor, reporters, cache, pool
$plan = RulePlan::build(['@PSR12' => true], '    ', "\n", false, false);
$proc = new Processor($plan->rules, true, true, true);
$o = $proc->file($tmp . '/src/a.php');
check($o->status === Outcome::CHANGED && $o->applied === ['no_trailing_whitespace'] && \str_contains($o->diff, '-$a = 1;  '), 'processor: dry run reports, applied rules by attribution');
check(\file_get_contents($tmp . '/src/a.php') === "<?php\n\n\$a = 1;  \n", 'processor: a dry run writes nothing');
$wire = Outcome::fromWire($o->toWire());
check($wire !== null && $wire->path === $o->path && $wire->applied === $o->applied && $wire->diff === $o->diff && $wire->status === $o->status, 'outcome: wire round trip');
$j = \json_decode(Reporter::render('json', [$o], true, true, 2, 0.5, 4.0), true);
check($j['files'][0]['name'] === $o->path && $j['files'][0]['appliedFixers'] === ['no_trailing_whitespace'] && isset($j['about']), 'report: json');
check(\str_contains(Reporter::render('checkstyle', [$o], true, true, 2, 0.5, 4.0), 'source="PHP-CS-Fixer.no_trailing_whitespace"'), 'report: checkstyle');
check(\str_contains(Reporter::render('junit', [$o], true, true, 2, 0.5, 4.0), '<failure type="code_style"><![CDATA[applied fixers:'), 'report: junit');
check(\str_contains(Reporter::render('txt', [$o], true, false, 2, 0.5, 4.0), '   1) ' . $o->path) && \str_contains(Reporter::render('txt', [$o], true, false, 2, 0.5, 4.0), 'Found 1 of 2 files that can be fixed'), 'report: txt');
$cacheFile = $tmp . '/cache.json';
$cache = new Cache($cacheFile, 'sig1');
$clean = $proc->file($tmp . '/src/sub/b.php');
check($clean->status === Outcome::OK && $clean->hash !== '', 'processor: clean file carries its hash');
$cache->note($clean);
$cache->note($o);
$cache->save();
$cache2 = new Cache($cacheFile, 'sig1');
check($cache2->isClean($tmp . '/src/sub/b.php', $clean->hash) && !$cache2->isClean($o->path, md5(\file_get_contents($o->path))), 'cache: clean files are remembered, a changed one is not');
check((new Cache($cacheFile, 'sig2'))->count() === 0, 'cache: another signature discards it');
$cached = new Processor($plan->rules, true, false, false, $cache2);
check($cached->file($tmp . '/src/sub/b.php')->status === Outcome::CACHED && $cached->file($o->path)->status === Outcome::CHANGED, 'cache: a clean file is skipped');
\file_put_contents($tmp . '/src/sub/b.php', "<?php\n\n\$b = 3;\n");
check($cached->file($tmp . '/src/sub/b.php')->status === Outcome::OK, 'cache: an edited file is looked at again');

$all = FileFinder::find(new Config(), [$files[0] === '' ? '.' : \dirname($files[0])]);
$seq = Pool::sequential($all, new Processor($plan->rules, true, true, true));
$par = Pool::run($all, 4, new Processor($plan->rules, true, true, true));
$same = \count($seq) === \count($par);
for ($i = 0; $same && $i < \count($seq); $i++) {
    $seq[$i]->secs = $par[$i]->secs = 0.0; // timing differs run to run
    $same = $seq[$i]->toWire() === $par[$i]->toWire();
}
check($same && \count($all) > 12, 'pool: 4 workers == sequential on ' . \count($all) . ' files');

// phpdoc: one line per rule family (expectations are upstream's output)
check(fmt("<?php\n/**\n * Foo.\n *\n * @access private\n * @package X\n */\nclass A {}\n/** @access public */\nfunction f() {}\n", ['phpdoc_no_access', 'phpdoc_no_package']) === "<?php\n/**\n * Foo.\n *\n */\nclass A {}\n\nfunction f() {}\n", 'phpdoc: phpdoc_no_access, phpdoc_no_package');
check(fmt("<?php\n/**\n * @{inheritdoc} { @link x }\n * @type int @property-read\n */\n", ['general_phpdoc_tag_rename', 'phpdoc_no_alias_tag', 'phpdoc_inline_tag_normalizer']) === "<?php\n/**\n * {@inheritdoc} { @see x }\n * @var int @property\n */\n", 'phpdoc: phpdoc tag rename, alias, inline tags');
check(fmt("<?php\n/**\n *\n * Summary\n *\n * @param int \$a The a.\n *\n */\nfunction f(\$a) {}\n", ['phpdoc_trim', 'phpdoc_summary', 'phpdoc_annotation_without_dot']) === "<?php\n/**\n * Summary.\n *\n * @param int \$a the a\n */\nfunction f(\$a) {}\n", 'phpdoc: phpdoc_trim, phpdoc_summary, phpdoc_annotation_without_dot');
check(fmt("<?php\nclass A\n{\n    /** */\n    public \$a;\n    /**\n     *\n     */\n}\n", ['no_empty_phpdoc']) === "<?php\nclass A\n{\n    public \$a;\n}\n", 'phpdoc: no_empty_phpdoc');
check(fmt("<?php\n/**\n * @param NULL|Integer \$a\n * @return String|boolean|null\n */\nfunction f(\$a) {}\n", ['phpdoc_scalar', 'phpdoc_types', 'phpdoc_types_order']) === "<?php\n/**\n * @param null|int \$a\n * @return null|bool|string\n */\nfunction f(\$a) {}\n", 'phpdoc: phpdoc_scalar, phpdoc_types, phpdoc_types_order');
check(fmt("<?php\nclass A\n{\n/**\n        * Doc.\n   no star\n      */\n\n    public \$a;\n}\n", ['align_multiline_comment', 'phpdoc_indent', 'no_blank_lines_after_phpdoc']) === "<?php\nclass A\n{\n    /**\n     * Doc.\n     * no star\n     */\n    public \$a;\n}\n", 'phpdoc: align_multiline_comment, phpdoc_indent, no_blank_lines_after_phpdoc');
check(fmt("<?php\nclass A\n{\n    /** @var int \$a */\n    public \$a;\n    /** {@inheritdoc} */\n    public function f() {}\n}\n/**\n * @covers Foo\n */\nclass FooTest extends TestCase {}\n", ['phpdoc_var_without_name', 'phpdoc_no_useless_inheritdoc', 'php_unit_fqcn_annotation']) === "<?php\nclass A\n{\n    /** @var int */\n    public \$a;\n    /** */\n    public function f() {}\n}\n/**\n * @covers \\Foo\n */\nclass FooTest extends TestCase {}\n", 'phpdoc: phpdoc_var_without_name, phpdoc_no_useless_inheritdoc, php_unit_fqcn_annotation');

$fmtWith = static function (string $code, string $name, array $opt): string {
    $s = Source::parse($code);
    $s = Engine::run($s, [Rules::make($name, "\n", $opt)]);
    return $s->rebuild()->join();
};
check($fmtWith("<?php\nnamespace App;\n\nuse Foo\\Bar;\n\n/** @param \\Foo\\Bar \$b */\nfunction f(\\Foo\\Bar \$b, \\App\\Baz \$z): \\Foo\\Bar { return new \\Foo\\Bar(); }\n", 'fully_qualified_strict_types', [])
    === "<?php\nnamespace App;\n\nuse Foo\\Bar;\n\n/** @param Bar \$b */\nfunction f(Bar \$b, Baz \$z): Bar { return new Bar(); }\n", 'phpdoc: fully_qualified_strict_types');
check($fmtWith("<?php\nnamespace App;\n\nuse DateTime;\nuse function strlen;\n\n/** @return DateTime */\nfunction f(DateTime \$d) { return strlen('a'); }\n", 'global_namespace_import',
    ['import_classes' => false, 'import_constants' => false, 'import_functions' => false])
    === "<?php\nnamespace App;\n\nuse DateTime;\nuse function strlen;\n\n/** @return \\DateTime */\nfunction f(\\DateTime \$d) { return \\strlen('a'); }\n", 'phpdoc: global_namespace_import (false)');

// the 20 rules @PhpCsFixer adds to @Symfony (upstream's samples)
check($fmtWith("<?php\n\$a = isset(\$a) && isset(\$b);\n", 'combine_consecutive_issets', []) === "<?php\n\$a = isset(\$a, \$b)  ;\n", 'pcf: combine_consecutive_issets');
check($fmtWith("<?php\nunset(\$a); unset(\$b);\n", 'combine_consecutive_unsets', []) === "<?php\nunset(\$a, \$b); \n", 'pcf: combine_consecutive_unsets');
check($fmtWith("<?php\nif (\$a) {\n    return 1;\n} elseif (\$b) {\n    return 2;\n}\n", 'no_superfluous_elseif', []) === "<?php\nif (\$a) {\n    return 1;\n}\nif (\$b) {\n    return 2;\n}\n", 'pcf: no_superfluous_elseif');
check($fmtWith("<?php\nfunction f() {\n    \$a = 1;\n    return \$a;\n}\n", 'return_assignment', []) === "<?php\nfunction f() {\n    return 1;\n}\n", 'pcf: return_assignment');
check($fmtWith("<?php\nfinal class A { function f() { return static::class . new static(); } }\n", 'self_static_accessor', []) === "<?php\nfinal class A { function f() { return self::class . new self(); } }\n", 'pcf: self_static_accessor');
check($fmtWith("<?php\n\$a = \"x \$b[0] \$c->d\";\n", 'explicit_string_variable', []) === "<?php\n\$a = \"x {\$b[0]} {\$c->d}\";\n", 'pcf: explicit_string_variable');
check($fmtWith("<?php\n\$a = <<<EOT\nplain \\\\ text\nEOT;\n", 'heredoc_to_nowdoc', []) === "<?php\n\$a = <<<'EOT'\nplain \\ text\nEOT;\n", 'pcf: heredoc_to_nowdoc');
check($fmtWith("<?php\n/**\n * @return void\n */\nfunction f() {}\n", 'phpdoc_no_empty_return', []) === "<?php\n/**\n */\nfunction f() {}\n", 'pcf: phpdoc_no_empty_return');
check($fmtWith("<?php\n\$x = [\n];\n", 'no_whitespace_in_empty_array', []) === "<?php\n\$x = [];\n", 'pcf: no_whitespace_in_empty_array');
check($fmtWith("<?php\nclass ATest extends TestCase {}\n", 'php_unit_internal_class', ['types' => ['normal']]) === "<?php\n\n/**\n * @internal\n */\nclass ATest extends TestCase {}\n", 'pcf: php_unit_internal_class');
// exit codes
$cli = static function (array $args): int {
    \ob_start();
    $r = Cli::run(\array_merge(['manticore-fmt'], $args));
    \ob_end_clean();
    return $r;
};
check($cli(['check', '-q', '--no-cache', '--config=-', '--rules=@PSR12', $tmp . '/src/a.php']) === 8, 'exit: check finds a change = 8');
check($cli(['check', '-q', '--no-cache', '--config=-', '--rules=@PSR12', $tmp . '/src/sub/b.php']) === 0, 'exit: nothing to change = 0');
check($cli(['check', '-q', '--config=-', '--rules=nope_rule', $tmp . '/src']) === 16, 'exit: invalid config = 16');
check($cli(['check', '-q', '--config=-', '--rules={"line_ending":[]}', $tmp . '/src']) === 32, 'exit: invalid rule config = 32');
Fixers::hide('header_comment', true);
check($cli(['check', '-q', '--config=-', '--rules=@PSR12,header_comment', $tmp . '/src']) === 16, 'exit: unimplemented rules = 16');
check($cli(['check', '-q', '--no-cache', '--config=-', '--allow-unimplemented', '--rules=@PSR12,header_comment', $tmp . '/src/a.php']) === 8, 'exit: --allow-unimplemented runs the rest');
Fixers::hide('header_comment', false);
check($cli(['nope']) === 16 && $cli(['help']) === 0 && $cli(['fix', '--bogus']) === 16, 'exit: usage');
\file_put_contents($tmp . '/bad.php', "<?php\n\$a = \x01;\n");
check($cli(['check', '-q', '--no-cache', '--config=-', '--rules=@PSR12', $tmp . '/bad.php']) === 4, 'exit: invalid file in a dry run = 4');
$cli(['fix', '-q', '--no-cache', '--config=-', '--rules=@PSR12', $tmp . '/src/a.php']);
check(\file_get_contents($tmp . '/src/a.php') === "<?php\n\n\$a = 1;\n", 'fix: writes the file');

// ---- YAML / JSON config: robustness (flow over lines, BOM, block scalars, anchors, documents, duplicates, errors)
check(yj("statements: [break,\n      return]\nm: {a: 1,\n b: 2}\nn: [\n  x,   # why\n  y,\n]\n") === '{"statements":["break","return"],"m":{"a":1,"b":2},"n":["x","y"]}', 'yaml: flow collections over several lines: ' . yj("statements: [break,\n      return]\nm: {a: 1,\n b: 2}\nn: [\n  x,   # why\n  y,\n]\n"));
check(yj("\xEF\xBB\xBFrules: {a: true}\n") === '{"rules":{"a":true}}', 'yaml: a BOM is skipped');
check(yj("h: |\n  line1\n   more\n\n  line3\n") === '{"h":"line1\n more\n\nline3\n"}', 'yaml: literal block scalar keeps lines');
check(yj("a: |-\n  x\n  y\n\nb: |+\n  k\n\nc: 1\n") === '{"a":"x\ny","b":"k\n\n","c":1}', 'yaml: chomping - and +');
check(yj("a: >\n  one\n  two\n\n  three\n   indented\n  four\nb: >-\n  p\n  q\n") === '{"a":"one two\nthree\n indented\nfour\n","b":"p q"}', 'yaml: folded block scalar: ' . yj("a: >\n  one\n  two\n\n  three\n   indented\n  four\nb: >-\n  p\n  q\n"));
check(yj("a: |2\n    deep\nb: |  # note\n  t\n") === '{"a":"  deep\n","b":"t\n"}', 'yaml: indentation indicator and a comment after the header');
check(yj("- |\n  txt\n- >-\n  f\n  g\n- x\n") === '["txt\n","f g","x"]', 'yaml: block scalars as sequence items');
check(yj(\ln(['h: |', '  /**', '   * Copyright', '   */', 'x: 1'])) === '{"h":"\/**\n * Copyright\n *\/\n","x":1}', 'yaml: header_comment style text');
check(str_contains(yj("a: |x\n  t\n"), 'invalid block scalar header'), 'yaml: a bad block scalar header is named');

check(yj("base: &b\n  x: 1\n  y: 2\nd:\n  <<: *b\n  y: 3\n") === '{"base":{"x":1,"y":2},"d":{"x":1,"y":3}}', 'yaml: merge key, explicit key wins');
check(yj("m: &m\n  a: 1\nn: &n\n  a: 9\n  b: 2\no:\n  <<: [*m, *n]\n  c: 3\n") === '{"m":{"a":1},"n":{"a":9,"b":2},"o":{"a":1,"b":2,"c":3}}', 'yaml: merge list, first source wins');
check(yj("a: {<<: {x: 1}, y: 2}\nb:\n  y: 5\n  <<: {y: 1, z: 2}\n") === '{"a":{"x":1,"y":2},"b":{"y":5,"z":2}}', 'yaml: merge of a flow map, key before the merge wins');
check(yj("s: &s hello\nt: *s\nl: &l [1, 2]\nu: *l\nk:\n  - &i {q: 1}\n  - *i\n") === '{"s":"hello","t":"hello","l":[1,2],"u":[1,2],"k":[{"q":1},{"q":1}]}', 'yaml: anchors and aliases on scalars, sequences, maps');
check(str_contains(yj("a: !!str x\n"), 'YAML line 1: tags (!!str, !Tag) are not supported') && str_contains(yj("a: !Tag x\n"), 'tags'), 'yaml: tags are refused by name');
check(str_contains(yj("a: *nope\n"), 'unknown alias *nope') && str_contains(yj("name: *.php\n"), "quote the text"), 'yaml: an unknown alias says how to quote');
check(str_contains(yj("a: 1\n<<: 5\n"), 'merge key "<<" needs a mapping'), 'yaml: a bad merge value is named');
check(str_contains(yj("? a\n: b\n"), 'complex mapping keys'), 'yaml: complex keys are refused by name');

check(yj("---\na: 1\n...\n") === '{"a":1}' && yj("--- # c\na: 1\n") === '{"a":1}' && yj("# lead\n---\na: 1\n... # end\n# tail\n") === '{"a":1}', 'yaml: a leading --- and a trailing ... work');
check(yj("a: 1\n---\nb: 2\n") === 'ERR YAML line 2: multi-document YAML is not supported (a second "---")', 'yaml: two documents are refused');
check(yj("---\na: 1\n---\nb: 2\n") === 'ERR YAML line 3: multi-document YAML is not supported (a second "---")', 'yaml: --- twice is refused: ' . yj("---\na: 1\n---\nb: 2\n"));
check(str_contains(yj("a: 1\n...\n---\nb: 2\n"), 'multi-document YAML is not supported'), 'yaml: a document after ... is refused');
check(str_contains(yj("%YAML 1.2\n---\na: 1\n"), 'directives'), 'yaml: directives are refused');

check(yj("a: 1\nb: 2\na: 3\n") === 'ERR YAML line 3: duplicate mapping key "a" (first on line 1); YAML keys are unique', 'yaml: a duplicate key names key and lines');
check(str_contains(yj("r: {x: 1, x: 2}\n"), 'duplicate mapping key "x"') && str_contains(yj("m:\n  q: 1\n  \"q\": 2\n"), 'duplicate mapping key "q"'), 'yaml: duplicates in flow maps and quoted keys');
check(yj("a:\n  x: 1\nb:\n  x: 2\n") === '{"a":{"x":1},"b":{"x":2}}', 'yaml: the same key in two maps is fine');

check(str_contains(yj("a: 1\n\tb: 2\n"), 'YAML line 2: a tab character is used for indentation'), 'yaml: tab indentation is named, with its line');
check(yj("a: 1\r\nb:\r\n  - x\r\n  - y\r\nc: |\r\n  l1\r\n  l2\r\n") === '{"a":1,"b":["x","y"],"c":"l1\nl2\n"}', 'yaml: CRLF input');
check(str_contains(yj("a: 1\r\n\tb: 2\r\n"), 'YAML line 2: a tab'), 'yaml: CRLF + tab error carries the line');
check(str_contains(yj("a: [x, y\n"), 'YAML line 1: the "[" opened here is never closed') && str_contains(yj("a: \"x\n"), 'YAML line 1: the quoted scalar opened here is never closed'), 'yaml: unterminated [ and " name their line');
check(str_contains(yj("a:\n  b: 1\n c: 2\n"), 'YAML line 3: bad indentation'), 'yaml: a mis-indented key says bad indentation, with the line');
check(str_contains(yj("a: b: c\n"), 'YAML line 1: a mapping value (": ") is not allowed here') && str_contains(yj("a: - x\n"), 'a block sequence cannot start on the line of a mapping key'), 'yaml: nested mapping / sequence on a key line');
check(str_contains(yj("- a\nb: 1\n"), 'YAML line 2') && str_contains(yj("a: 1\n- b\n"), 'a sequence entry'), 'yaml: a mapping and a sequence at one level');
check(str_contains(yj("a: \"\\q\"\n"), 'unknown escape \\q') && str_contains(yj("a: \"\\q\"\n"), 'single quotes'), 'yaml: an unknown escape hints at single quotes');

check(yj("a: 'it''s'\nb: \"t\\tn\\x41\\u00e9\\U0001F600 \\\"q\\\"\"\nc: \"\\/\\\\\"\n") === '{"a":"it\'s","b":"t\tnA\u00e9\ud83d\ude00 \"q\"","c":"\/\\\\"}', 'yaml: quote escapes');
check(yj("\"k: x\": 1\n'q': 2\nkey:    value   \nz:\ty\n") === '{"k: x":1,"q":2,"key":"value","z":"y"}', 'yaml: quoted keys, extra spaces, tab after the colon');
check(yj("list:\n  - k: v\n    n:\n      deep: 1\n  - k2: v2\n  - - a\n    - b\n  -\n    c: 1\n") === '{"list":[{"k":"v","n":{"deep":1}},{"k2":"v2"},["a","b"],{"c":1}]}', 'yaml: sequences of maps with nested maps and sequences');
check(yj("a:\nb: ~\nc: null\nd: Null\ne:\n  # comment only\nf: 1\n") === '{"a":null,"b":null,"c":null,"d":null,"e":null,"f":1}', 'yaml: empty values are null');
check(yj("a: v # c\n# own\n   # odd\nb:    # after key\n  - x # after item\n     # deeper comment\n  - y\nurl: http://h/p#frag\nq: \"a # not\" # yes\n") === '{"a":"v","b":["x","y"],"url":"http:\/\/h\/p#frag","q":"a # not"}', 'yaml: comments everywhere');
check(yj("a: true\nb: True\nc: FALSE\nd: yes\ne: No\nf: on\ng: Off\nh: y\ni: ~\nj: NULL\n") === '{"a":true,"b":true,"c":false,"d":"yes","e":"No","f":"on","g":"Off","h":"y","i":null,"j":null}', 'yaml: 1.2 core booleans and null (yes/no/on/off are strings)');
check(yj("a: 12\nb: -7\nc: +5\nd: 0755\ne: 0o17\nf: 0x1F\ng: 1.5\nh: 1e3\ni: .5\nj: 1_000\nk: 0b1\nl: 1.2.3\n") === '{"a":12,"b":-7,"c":5,"d":755,"e":15,"f":31,"g":1.5,"h":1000,"i":0.5,"j":"1_000","k":"0b1","l":"1.2.3"}', 'yaml: integers, octal, hex, floats: ' . yj("a: 12\nb: -7\nc: +5\nd: 0755\ne: 0o17\nf: 0x1F\ng: 1.5\nh: 1e3\ni: .5\nj: 1_000\nk: 0b1\nl: 1.2.3\n"));
check(yj("a: 99999999999999999999\nb: 9223372036854775807\n") === '{"a":1.0e+20,"b":9223372036854775807}', 'yaml: an integer past int64 is a float: ' . yj("a: 99999999999999999999\nb: 9223372036854775807\n"));
$inf = Yaml::parse("a: .inf\nb: -.INF\nc: .nan\n");
check($inf['a'] === \INF && $inf['b'] === -\INF && \is_nan($inf['c']), 'yaml: .inf .nan');
check(yj("w: C:\\Users\\x\\y\nl:\n  - D:\\a b\\c\n  - 'E:\\q'\n") === '{"w":"C:\\\\Users\\\\x\\\\y","l":["D:\\\\a b\\\\c","E:\\\\q"]}', 'yaml: Windows paths in plain and single-quoted values');
check(yj("a: \"multi\n  line\n\n  text\"\nb: plain\n  folded\n  more\nc: 'x\n  y'\n") === '{"a":"multi line\ntext","b":"plain folded more","c":"x y"}', 'yaml: multi-line quoted and plain scalars fold');
check(yj("a: 1 \nb: 2   \nc: [1, 2,]\nd: {x: 1,}\ne: []\nf: {}\n") === '{"a":1,"b":2,"c":[1,2],"d":{"x":1},"e":[],"f":[]}', 'yaml: trailing spaces and commas, empty collections');
check(yj("a:\n  b:\n    c:\n      d: [1, {e: [2, 3]}]") === '{"a":{"b":{"c":{"d":[1,{"e":[2,3]}]}}}}', 'yaml: deep nesting, no final newline');
check(yj("x:\n- a\n- b\ny: 1\n") === '{"x":["a","b"],"y":1}', 'yaml: a sequence at its key\'s indentation');
check(yj("  a: 1\n  b: 2\n") === '{"a":1,"b":2}' && yj("") === 'null' && yj("# only a comment\n") === 'null' && yj("---\n") === 'null', 'yaml: indented top level, empty documents');
$long = \str_repeat('word ', 2000) . 'end';
check(Yaml::parse('s: ' . $long . "\n")['s'] === $long, 'yaml: a very long string');
$deep = '';
for ($i = 0; $i < 60; $i++) {
    $deep .= \str_repeat('  ', $i) . 'k' . $i . ":\n";
}
check(\substr_count(yj($deep . \str_repeat('  ', 60) . "v: 1\n"), '{') === 61, 'yaml: 60 levels of mapping');
check(str_contains(yj("a: [x]: 1\n"), 'flow collection cannot be a key') && str_contains(yj("a: [,]\n"), 'a value is missing') && str_contains(yj("a: [x: 1]\n"), 'single-pair mapping'), 'yaml: flow collection edge cases are named');
check(str_contains(yj("a: 'x' y\n"), 'unexpected "y" after the value') && str_contains(yj("a: x\n  - b\n") . 'zz', 'x - b'), 'yaml: junk after a quoted scalar; deeper text continues a plain scalar');

// JSON configs
$cd = $tmp . '/cfgt';
\mkdir($cd, 0777, true);
\file_put_contents($cd . '/bom.json', "\xEF\xBB\xBF{\"risky\":true}");
\file_put_contents($cd . '/bom.yaml', "\xEF\xBB\xBFrisky: true\n");
check(ConfigLoader::load($cd . '/bom.json')->risky === true && ConfigLoader::load($cd . '/bom.yaml')->risky === true, 'config: a BOM is skipped in JSON and YAML');
\file_put_contents($cd . '/dup.json', '{"risky":false,"risky":true}');
check(ConfigLoader::load($cd . '/dup.json')->risky === true, 'config: JSON duplicate keys, last wins (as json_decode)');
\file_put_contents($cd . '/dup.yaml', "risky: false\nrisky: true\n");
check(str_contains(cfgErr($cd . '/dup.yaml'), 'dup.yaml') && str_contains(cfgErr($cd . '/dup.yaml'), 'YAML line 2') && str_contains(cfgErr($cd . '/dup.yaml'), 'duplicate mapping key "risky"'), 'config: YAML duplicate key names file, line and key: ' . cfgErr($cd . '/dup.yaml'));
\file_put_contents($cd . '/dupn.yaml', "line_ending: lf\nlineEnding: crlf\n");
check(str_contains(cfgErr($cd . '/dupn.yaml'), '"lineEnding" repeats "line_ending"'), 'config: two spellings of one key are refused');
\file_put_contents($cd . '/syn.json', "{\n  \"risky\": true,\n  \"indent\": 4,\n}\n");
check(str_contains(cfgErr($cd . '/syn.json'), 'syn.json') && str_contains(cfgErr($cd . '/syn.json'), 'invalid JSON at line 4 column 1'), 'config: JSON syntax error carries file, line, column: ' . cfgErr($cd . '/syn.json'));
\file_put_contents($cd . '/empty.json', '');
check(str_contains(cfgErr($cd . '/empty.json'), 'invalid JSON at line 1'), 'config: an empty JSON file is an error');
\file_put_contents($cd . '/list.yaml', "- a\n- b\n");
\file_put_contents($cd . '/list.json', '["a"]');
check(str_contains(cfgErr($cd . '/list.yaml'), 'must be a map') && str_contains(cfgErr($cd . '/list.json'), 'must be a map'), 'config: a list at the top is refused');
\file_put_contents($cd . '/empty.yaml', "# nothing\n");
check(cfgErr($cd . '/empty.yaml') === '', 'config: an empty YAML file is an empty config');
\file_put_contents($cd . '/tag.yaml', "rules:\n  a: !!bool yes\n");
check(str_contains(cfgErr($cd . '/tag.yaml'), 'Cannot parse config file') && str_contains(cfgErr($cd . '/tag.yaml'), 'YAML line 2: tags'), 'config: a YAML refusal names file, line, feature: ' . cfgErr($cd . '/tag.yaml'));
\file_put_contents($cd . '/uni.json', '{"rules":{"header_comment":{"header":"caf\u00e9 \ud83d\ude00 \u0041"}},"parallel":9007199254740993}');
$u = ConfigLoader::load($cd . '/uni.json');
check($u->rules['header_comment']['header'] === "caf\u{e9} \u{1F600} A" && $u->workers === 9007199254740993, 'config: JSON unicode escapes and an int past 2^53');
\file_put_contents($cd . '/bigj.json', '{"parallel":99999999999999999999}');
check(str_contains(cfgErr($cd . '/bigj.json'), 'non-negative integer, true or false'), 'config: a JSON int past int64 is refused as an integer');
\file_put_contents($cd . '/hdr.yaml', "rules:\n  header_comment:\n    header: |-\n      Copyright (c) Acme\n\n      All rights reserved.\n    location: after_open\n");
$h = ConfigLoader::load($cd . '/hdr.yaml');
check($h->rules['header_comment']['header'] === "Copyright (c) Acme\n\nAll rights reserved." && $h->rules['header_comment']['location'] === 'after_open', 'config: multi-line header_comment from a block scalar');
\file_put_contents($cd . '/anc.yaml', "x-common: &common\n  syntax: short\nrules:\n  array_syntax: *common\n  list_syntax: {<<: *common}\nparallel: 3\n");
check(cfgErr($cd . '/anc.yaml') === 'Config file "' . $cd . '/anc.yaml": unknown key "x-common" (allowed: rules, risky, indent, lineEnding, paths, exclude, name, notName, path, notPath, ignoreDotFiles, cache, parallel, format, allowUnimplemented, pathMode).', 'config: a top-level anchor holder key is not silently accepted');

// the same configuration in JSON and in YAML
$richJson = '{"rules":{"@Symfony":true,"yoda_style":false,"array_syntax":{"syntax":"short"},"ordered_imports":{"sort_algorithm":"alpha","imports_order":["class","function","const"]},"no_extra_blank_lines":{"tokens":["use","extra"]},"native_function_invocation":{"include":["@compiler_optimized"],"scope":"namespaced"}},'
    . '"risky":true,"indent":"tab","lineEnding":"\r\n","paths":["src","lib/a.php"],"exclude":["vendor","fixtures"],"name":["*.php","*.inc"],"notName":["*.blade.php"],"path":["src/Foo"],"notPath":["cache"],'
    . '"ignoreDotFiles":false,"cache":{"enabled":true,"file":"var/.fmt.cache"},"parallel":{"workers":3,"chunk":7},"format":"json","allowUnimplemented":true,"pathMode":"intersection"}';
$richYaml = \ln([
    "\xEF\xBB\xBF---",
    '# the same as rich.json',
    'rules:',
    "  '@Symfony': true",
    '  yoda_style: false',
    '  array_syntax: {syntax: short}',
    '  ordered_imports:',
    '    sort_algorithm: alpha',
    '    imports_order: [class, function,',
    '                    const]',
    '  no_extra_blank_lines:',
    '    tokens:',
    '    - use',
    '    - extra',
    '  native_function_invocation:',
    '    include: ["@compiler_optimized"]',
    '    scope: namespaced',
    'risky: true',
    'indent: tab',
    'lineEnding: "\r\n"',
    'paths:',
    '  - src',
    '  - lib/a.php',
    'exclude: [vendor, fixtures]',
    "name: ['*.php', '*.inc']",
    "notName: ['*.blade.php']",
    'path: [src/Foo]',
    'notPath: [cache]',
    'ignoreDotFiles: false',
    'cache:',
    '  enabled: true',
    '  file: var/.fmt.cache',
    'parallel: {workers: 3, chunk: 7}',
    'format: json',
    'allowUnimplemented: true',
    'pathMode: intersection',
    '...',
]);
\file_put_contents($cd . '/rich.json', $richJson);
\file_put_contents($cd . '/rich.yaml', $richYaml);
$cj = ConfigLoader::load($cd . '/rich.json');
$cy = ConfigLoader::load($cd . '/rich.yaml');
$dumpCfg = static function (Config $c): string {
    return \json_encode([$c->rules, $c->rulesGiven, $c->risky, $c->indent, $c->lineEnding, $c->paths, $c->exclude, $c->name, $c->notName, $c->path, $c->notPath, $c->ignoreDotFiles,
        $c->cache, $c->cacheFile, $c->workers, $c->chunkSize, $c->format, $c->allowUnimplemented, $c->pathMode]);
};
check($dumpCfg($cj) === $dumpCfg($cy) && $cy->workers === 3 && $cy->chunkSize === 7 && $cy->paths === [$cd . '/src', $cd . '/lib/a.php'] && $cy->cacheFile === $cd . '/var/.fmt.cache'
    && $cy->indent === "\t" && $cy->lineEnding === "\r\n" && $cy->pathMode === 'intersection' && $cy->ignoreDotFiles === false, 'config: JSON and YAML give the same Config: ' . $dumpCfg($cy));
$pj = RulePlan::build($cj->rules, $cj->indent, $cj->lineEnding, true, true);
$py = RulePlan::build($cy->rules, $cy->indent, $cy->lineEnding, true, true);
$rj = $pj->resolved;
$ry = $py->resolved;
\ksort($rj);
\ksort($ry);
check(\json_encode($rj) === \json_encode($ry) && \count($rj) > 50, 'config: JSON and YAML resolve to byte-identical rules (' . \count($rj) . ')');
$dirty = "<?php\n\$a = array(1, 2);  \n\$b = 1;\n";
foreach (['j', 'y'] as $w) {
    \mkdir($cd . '/' . $w . '/src', 0777, true);
    \file_put_contents($cd . '/' . $w . '/src/a.php', $dirty);
    \file_put_contents($cd . '/' . $w . '/src/b.php', "<?php\n\$c = [1];\n");
}
\file_put_contents($cd . '/j/.manticore-fmt.json', '{"rules":{"array_syntax":{"syntax":"short"},"no_trailing_whitespace":true,"yoda_style":false},"paths":["src"],"name":["*.php"],"cache":false,"parallel":1,"format":"txt"}');
\file_put_contents($cd . '/y/.manticore-fmt.yaml', \ln(['rules:', '  array_syntax: {syntax: short}', '  no_trailing_whitespace: true', '  yoda_style: false', 'paths: [src]', 'name: ["*.php"]', 'cache: false', 'parallel: 1', 'format: txt']));
$exJ = $cli(['check', '-q', '--config=' . $cd . '/j/.manticore-fmt.json']);
$exY = $cli(['check', '-q', '--config=' . $cd . '/y/.manticore-fmt.yaml']);
check($exJ === 8 && $exY === 8, 'config: check with the JSON and the YAML config = 8');
$cli(['fix', '-q', '--config=' . $cd . '/j/.manticore-fmt.json']);
$cli(['fix', '-q', '--config=' . $cd . '/y/.manticore-fmt.yaml']);
check(\file_get_contents($cd . '/j/src/a.php') === "<?php\n\$a = [1, 2];\n\$b = 1;\n" && \file_get_contents($cd . '/j/src/a.php') === \file_get_contents($cd . '/y/src/a.php')
    && \file_get_contents($cd . '/j/src/b.php') === \file_get_contents($cd . '/y/src/b.php'), 'config: fix gives identical results from JSON and YAML: ' . \file_get_contents($cd . '/y/src/a.php'));

// discovery: precedence, path directory vs cwd, relative paths, --config=-
$dd = $cd . '/disc';
\mkdir($dd . '/proj/src', 0777, true);
\mkdir($dd . '/other', 0777, true);
$bad = "<?php\n\$a = 1;  \n";
\file_put_contents($dd . '/proj/src/x.php', $bad);
$hit = '{"rules":{"no_trailing_whitespace":true}}';
$miss = "rules:\n  line_ending: true\n";
$missJ = '{"rules":{"line_ending":true}}';
$runs = static function (string $cwd, array $args) use ($cli): int {
    $old = (string) \getcwd();
    \chdir($cwd);
    $r = $cli($args);
    \chdir($old);
    return $r;
};
\file_put_contents($dd . '/proj/.manticore-fmt.dist.yml', $hit);
check($runs($dd . '/other', ['check', '-q', '--no-cache', $dd . '/proj']) === 8, 'discover: a lone .dist.yml is used (dir of the path argument)');
check($runs($dd . '/proj', ['check', '-q', '--no-cache', '.']) === 8, 'discover: relative file argument');
\file_put_contents($dd . '/proj/.manticore-fmt.dist.json', $missJ);
check($runs($dd . '/other', ['check', '-q', '--no-cache', $dd . '/proj']) === 0, 'discover: .dist.json beats .dist.yml (json, yaml, yml order within a tier)');
\file_put_contents($dd . '/proj/.manticore-fmt.yml', $hit);
check($runs($dd . '/other', ['check', '-q', '--no-cache', $dd . '/proj']) === 8, 'discover: a non-dist file beats every .dist');
\file_put_contents($dd . '/proj/.manticore-fmt.yaml', $miss);
check($runs($dd . '/other', ['check', '-q', '--no-cache', $dd . '/proj']) === 0, 'discover: .yaml beats .yml');
\file_put_contents($dd . '/proj/.manticore-fmt.json', $hit);
check($runs($dd . '/other', ['check', '-q', '--no-cache', $dd . '/proj']) === 8, 'discover: .json beats .yaml');
check($runs($dd . '/other', ['check', '-q', '--no-cache', '--config=-', '--rules=line_ending', $dd . '/proj']) === 0, 'discover: --config=- ignores every file');
check($runs($dd . '/other', ['check', '-q', '--no-cache', '--config=' . $dd . '/proj/.manticore-fmt.yaml', $dd . '/proj']) === 0, 'discover: --config=FILE beats discovery');
\file_put_contents($dd . '/other/.manticore-fmt.json', $missJ);
check($runs($dd . '/other', ['check', '-q', '--no-cache', $dd . '/proj']) === 8, 'discover: the directory of the path argument beats the cwd');
\file_put_contents($dd . '/proj/src/y.php', $bad);
\unlink($dd . '/proj/.manticore-fmt.json');
\unlink($dd . '/proj/.manticore-fmt.yaml');
\unlink($dd . '/proj/.manticore-fmt.yml');
\unlink($dd . '/proj/.manticore-fmt.dist.json');
\unlink($dd . '/proj/.manticore-fmt.dist.yml');
\mkdir($dd . '/proj/src/deep', 0777, true);
\file_put_contents($dd . '/proj/src/deep/z.php', $bad);
\unlink($dd . '/proj/src/y.php');
check($runs($dd . '/other', ['check', '-q', '--no-cache', $dd . '/proj/src']) === 0, 'discover: no file next to the path falls back to the cwd config');
\file_put_contents($dd . '/proj/.manticore-fmt.json', '{"rules":{"no_trailing_whitespace":true},"paths":["src"],"exclude":["deep"],"cache":false}');
check($runs($dd . '/other', ['check', '-q', '--config=' . $dd . '/proj/.manticore-fmt.json']) === 8, 'config paths are relative to the config file, not the cwd');
\file_put_contents($dd . '/proj/src/x.php', "<?php\n\$a = 1;\n");
check($runs($dd . '/other', ['check', '-q', '--config=' . $dd . '/proj/.manticore-fmt.json']) === 0, 'config exclude: relative paths, deep/ pruned (x.php is clean now)');
check($runs($dd . '/proj', ['check', '-q', '--config=.manticore-fmt.json']) === 0 && $runs($dd . '/proj/src', ['check', '-q', '--config=../.manticore-fmt.json']) === 0, 'config: --config with a relative path resolves paths against that file');
rmrf($cd);

foreach (\array_merge(Files::collect([$tmp]), [$tmp . '/src/sub/b.inc', $tmp . '/src/notes.txt', $tmp . '/c.json', $tmp . '/c.yaml', $tmp . '/bad.yaml', $tmp . '/bad2.json', $cacheFile]) as $f) {
    @\unlink($f);
}
foreach ([$tmp . '/src/sub', $tmp . '/src', $tmp . '/vendor/x', $tmp . '/vendor', $tmp . '/.hidden', $tmp] as $d) {
    @\rmdir($d);
}

// ---- color decision table: --color flag > FORCE_COLOR > NO_COLOR > TERM=dumb > TTY
check(Ansi::decide('auto', true, null, null, 'xterm') === true, 'ansi: auto + tty = on');
check(Ansi::decide('auto', false, null, null, 'xterm') === false, 'ansi: auto + no tty = off');
check(Ansi::decide('auto', true, '1', null, 'xterm') === false, 'ansi: NO_COLOR turns a tty off');
check(Ansi::decide('auto', true, '', null, 'xterm') === true, 'ansi: an empty NO_COLOR is ignored');
check(Ansi::decide('auto', true, null, null, 'dumb') === false, 'ansi: TERM=dumb = off');
check(Ansi::decide('auto', false, null, '1', 'dumb') === true, 'ansi: FORCE_COLOR forces on, even without a tty and with TERM=dumb');
check(Ansi::decide('auto', false, '1', '1', null) === true, 'ansi: FORCE_COLOR beats NO_COLOR');
check(Ansi::decide('auto', true, null, '0', 'xterm') === true && Ansi::decide('auto', false, null, '0', null) === false, 'ansi: FORCE_COLOR=0 and empty do not force');
check(Ansi::decide('always', false, '1', null, 'dumb') === true, 'ansi: --color=always beats NO_COLOR, TERM and no tty');
check(Ansi::decide('never', true, null, '1', 'xterm') === false, 'ansi: --color=never beats FORCE_COLOR');
check(Ansi::paint(false, Ansi::RED, 'x') === 'x' && Ansi::paint(true, Ansi::RED, 'x') === "\033[31mx\033[0m", 'ansi: paint');

// ---- progress line (pure)
check(Progress::render(0, 0, 0.0, 0, 0, 0, 0, false) === '[####################] 0/0 100% 0.0 files/s ETA --  ok 0  changed 0  cached 0  errors 0', 'progress: nothing to do: ' . Progress::render(0, 0, 0.0, 0, 0, 0, 0, false));
check(Progress::render(50, 200, 5.0, 10, 30, 7, 3, false) === '[#####---------------] 50/200  25% 10.0 files/s ETA 15s  ok 30  changed 10  cached 7  errors 3', 'progress: quarter: ' . Progress::render(50, 200, 5.0, 10, 30, 7, 3, false));
check(Progress::render(200, 200, 20.0, 1, 199, 0, 0, false) === '[####################] 200/200 100% 10.0 files/s ETA 0s  ok 199  changed 1  cached 0  errors 0', 'progress: done');
check(Progress::render(1, 4, 0.5, 0, 1, 0, 0, true) === "[\033[32m#####\033[0m\033[2m---------------\033[0m] 1/4  25% 2.0 files/s ETA 2s  \033[32mok 1\033[0m  \033[33mchanged 0\033[0m  \033[2mcached 0\033[0m  \033[2merrors 0\033[0m", 'progress: colors');
check(\str_contains(Progress::render(1, 4, 0.5, 0, 0, 0, 1, true), "\033[31merrors 1\033[0m"), 'progress: errors are red');
check(Progress::duration(7.2) === '8s' && Progress::duration(65) === '1m05s' && Progress::duration(7380) === '2h03m', 'progress: duration');

// ---- stats aggregation + json shape
$mk = static function (string $p, int $st, float $secs, int $bytes, array $applied = []): Outcome {
    $x = new Outcome($p, $st);
    $x->secs = $secs;
    $x->bytes = $bytes;
    $x->applied = $applied;
    return $x;
};
$res = [$mk('a.php', Outcome::CHANGED, 0.5, 1048576, ['braces', 'indent']), $mk('b.php', Outcome::OK, 0.1, 1048576), $mk('c.php', Outcome::CACHED, 0.0, 0),
    $mk('d.php', Outcome::FAILED, 0.2, 0), $mk('e.php', Outcome::INVALID, 0.3, 0), $mk('f.php', Outcome::CHANGED, 0.4, 0, ['braces'])];
$st = Stats::collect($res, 8, 2.0, 3.5, 12.25);
check($st->total === 8 && $st->changed === 2 && $st->ok === 1 && $st->cached === 1 && $st->errors === 2 && $st->skipped === 2, 'stats: counts');
check(\array_keys($st->rules) === ['braces', 'indent'] && $st->rules['braces'] === 2, 'stats: rules by files changed, most first');
check(\array_keys($st->slowest) === ['a.php', 'f.php', 'e.php', 'd.php', 'b.php'], 'stats: five slowest first');
check($st->filesPerSec() === 3.0 && $st->mbPerSec() === 1.0, 'stats: throughput counts processed files only');
$sj = (string) \json_encode($st->toArray(), \JSON_UNESCAPED_SLASHES);
check($sj === '{"files":{"total":8,"changed":2,"ok":1,"cached":1,"skipped":2,"errors":2},"time":{"wall":2,"cpu":3.5},"peakMemoryMb":12.25,"bytes":2097152,"throughput":{"filesPerSec":3,"mbPerSec":1},"rules":[{"rule":"braces","files":2},{"rule":"indent","files":1}],"slowest":[{"file":"a.php","seconds":0.5},{"file":"f.php","seconds":0.4},{"file":"e.php","seconds":0.3},{"file":"d.php","seconds":0.2},{"file":"b.php","seconds":0.1}]}', 'stats: json shape: ' . $sj);
$cj = Reporter::render('json', [$res[0]], true, false, 8, 2.0, 12.25, false, $st);
check(\str_contains($cj, '"stats":{"files":{"total":8') && !\str_contains(Reporter::render('json', [$res[0]], true, false, 8, 2.0, 12.25), '"stats"'), 'stats: json report carries stats only on request');
check(Reporter::render('checkstyle', [$res[0]], true, false, 8, 2.0, 12.25, false, $st) === Reporter::render('checkstyle', [$res[0]], true, false, 8, 2.0, 12.25), 'stats: checkstyle ignores them');
$tx = $st->text(false);
check(\str_contains($tx, "  files       8 total, 2 changed, 1 ok, 1 cached, 2 skipped, 2 errors\n") && \str_contains($tx, "  time        2.000 s wall, 3.500 s cpu\n")
    && \str_contains($tx, "Top rules by files changed\n      2  braces\n") && \str_contains($tx, "Slowest files\n    0.5000 s  a.php\n") && !\str_contains($tx, "\033"), 'stats: text: ' . $tx);
check(\str_contains($st->text(true), "\033[1mStatistics\033[0m"), 'stats: text headings are bold with color');

// ---- colored text report; off = byte-identical to the plain one
$cd = new Outcome('x.php', Outcome::CHANGED);
$cd->diff = "--- x.php\n+++ x.php\n@@ -1 +1 @@\n-a\n+b\n";
$plain = Reporter::render('txt', [$cd], true, false, 1, 0.5, 1.0);
$col = Reporter::render('txt', [$cd], true, false, 1, 0.5, 1.0, true);
check($plain === Reporter::render('txt', [$cd], true, false, 1, 0.5, 1.0, false) && !\str_contains($plain, "\033"), 'report: color off = plain');
check(\str_contains($col, "\033[33mx.php\033[0m") && \str_contains($col, "\033[31m-a\033[0m") && \str_contains($col, "\033[32m+b\033[0m") && \str_contains($col, "\033[33mFound 1 of 1") && \str_contains($col, "--- x.php\n"), 'report: color on: ' . \str_replace("\033", 'E', $col));
$rt = Outcome::fromWire($mk('w.php', Outcome::OK, 0.25, 77)->toWire());
check($rt !== null && $rt->secs === 0.25 && $rt->bytes === 77, 'wire: secs and bytes survive the pool pipe');

echo $fails === 0 ? "OK\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
