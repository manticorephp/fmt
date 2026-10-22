<?php

declare(strict_types=1);

// usage: bin/fmt-test <corpus-dir>
// 1. round trip (the first test): join(tokens, gaps) == input, byte for byte, for every file
// 2. Structure invariants  3. a few hand-written rule cases (byte parity vs upstream is bench/oracle.php)

use Fmt\Aliases;
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

foreach (\array_merge(Files::collect([$tmp]), [$tmp . '/src/sub/b.inc', $tmp . '/src/notes.txt', $tmp . '/c.json', $tmp . '/c.yaml', $tmp . '/bad.yaml', $tmp . '/bad2.json', $cacheFile]) as $f) {
    @\unlink($f);
}
foreach ([$tmp . '/src/sub', $tmp . '/src', $tmp . '/vendor/x', $tmp . '/vendor', $tmp . '/.hidden', $tmp] as $d) {
    @\rmdir($d);
}

echo $fails === 0 ? "OK\n" : "$fails FAILED\n";
exit($fails === 0 ? 0 : 1);
