<?php

declare(strict_types=1);

// usage: php bench/mutate_space.php <src-corpus> <dst-dir> [seed]
// Spacing-rule dirt: the clean corpus is already cs-fixer-clean for cast/operator/argument spacing, so the oracle on it proves
// little. This rewrites whitespace BETWEEN tokens (outside strings / heredocs / inline HTML) and the inside of casts:
// spaces removed, doubled, tabs, newlines, inline comments, `//` comments, blanks inserted between glued tokens.
// Only gaps change, never a token's bytes, so the code stays valid (a removal never glues two words or two operators).

require __DIR__.'/lib.php';

const WORD = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_\\$';
const OPS = '-+*/%&|^<>=!?.:~@';

function pick(array $a): string
{
    return $a[mt_rand(0, count($a) - 1)];
}

function glue(string $a, string $b): bool
{
    if ('' === $a || '' === $b) {
        return false;
    }
    $x = $a[strlen($a) - 1];
    $y = $b[0];
    $w = static fn (string $c): bool => false !== strpos(WORD, $c) || ord($c) >= 0x80;
    $o = static fn (string $c): bool => false !== strpos(OPS, $c);

    return ($w($x) && $w($y)) || ($o($x) && $o($y)) || '#' === $y || '#' === $x || '?' === $x && '>' === $y || '<' === $x && '?' === $y;
}

function mutate(string $code): string
{
    $toks = token_get_all($code);
    $cnt = count($toks);
    $out = [];
    $str = 0; // inside "..." / `...` / heredoc
    $kinds = [];
    foreach ($toks as $i => $t) {
        $kinds[] = is_array($t) ? $t[0] : $t;
    }
    $castIds = [T_INT_CAST, T_DOUBLE_CAST, T_STRING_CAST, T_ARRAY_CAST, T_OBJECT_CAST, T_BOOL_CAST, T_UNSET_CAST];
    for ($i = 0; $i < $cnt; ++$i) {
        $t = $toks[$i];
        $text = is_array($t) ? $t[1] : $t;
        $id = $kinds[$i];
        if (T_START_HEREDOC === $id) {
            $str = 2;
        } elseif (T_END_HEREDOC === $id) {
            $str = 0;
        } elseif (('"' === $id || '`' === $id) && 2 !== $str) {
            $str = 0 === $str ? 1 : 0;
        }
        if ($str > 0 || T_END_HEREDOC === $id || T_START_HEREDOC === $id || T_INLINE_HTML === $id || T_OPEN_TAG === $id
            || T_OPEN_TAG_WITH_ECHO === $id || T_CLOSE_TAG === $id) {
            $out[] = $text;
            continue;
        }
        if (in_array($id, $castIds, true) && mt_rand(0, 99) < 35) {
            $name = trim($text, "( \t)");
            $text = '('.pick(['', ' ', '  ', "\t"]).$name.pick(['', ' ', "\t", '  ']).')';
        } elseif (T_WHITESPACE === $id) {
            $prev = $toks[$i - 1] ?? null;
            $next = $toks[$i + 1] ?? null;
            $ptext = is_array($prev) ? $prev[1] : (string) $prev;
            $ntext = is_array($next) ? $next[1] : (string) $next;
            $lc = is_array($prev) && T_COMMENT === $prev[0] && !str_starts_with($ptext, '/*');
            $nl = str_contains($text, "\n") || $lc;
            $nextHtml = is_array($next) && in_array($next[0], [T_INLINE_HTML, T_END_HEREDOC, T_CLOSE_TAG], true);
            $prevHtml = is_array($prev) && in_array($prev[0], [T_START_HEREDOC, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO], true);
            if (!$nextHtml && !$prevHtml && mt_rand(0, 99) < ($nl ? 12 : 30)) {
                if ($nl) {
                    $text = pick(["\n", "\n    ", " \n", "\n\n  ", "\n\t", "\r\n  ", " // c\n  ", " /* c */\n", "\n/* c */ "]);
                    if (str_contains($text, "\r") && !str_contains($code, "\r")) {
                        $text = "\n";
                    }
                } else {
                    $c = pick(['', ' ', '  ', "\t", ' /* c */ ', '/**/', " \t", ' /* c */', '/* c */ ']);
                    if ('&' === $ptext && str_contains($c, '/*')) {
                        $c = ' '; // tests/repro/ampersand_comment_before_var.php: the prelude tokenizer mistypes `& /* c */ $x`
                    }
                    if ('' === $c && glue($ptext, $ntext)) {
                        $c = ' ';
                    }
                    $text = $c;
                }
            }
        } else {
            $next = $toks[$i + 1] ?? null;
            if (null !== $next && !is_array($next) || is_array($next) && T_WHITESPACE !== $next[0]) {
                $ntext = is_array($next) ? $next[1] : (string) $next;
                $nid = is_array($next) ? $next[0] : $next;
                $skip = in_array($nid, [T_INLINE_HTML, T_END_HEREDOC, T_CLOSE_TAG, T_ENCAPSED_AND_WHITESPACE], true) || '"' === $nid || '`' === $nid
                    || (T_COMMENT === $id && !str_starts_with($text, '/*'));
                if (!$skip && 2 !== $str && mt_rand(0, 99) < 9) {
                    $text .= pick([' ', '  ', "\t", '&' === $text ? ' ' : ' /* c */ ', "\n    "]);
                }
            }
        }
        $out[] = $text;
    }

    return implode('', $out);
}

[$self, $src, $dst, $seed] = $argv + [null, null, null, '11'];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate_space.php <src> <dst> [seed]\n");
    exit(2);
}
mt_srand((int) $seed);
rmTree($dst);
$bad = 0;
foreach (phpFiles($src) as $rel) {
    $code = file_get_contents($src.'/'.$rel);
    $new = mutate($code);
    // the mutation must keep the file lintable (the tokenizer linter upstream uses): otherwise upstream refuses it
    try {
        token_get_all($new, TOKEN_PARSE);
    } catch (ParseError $e) {
        ++$bad;
        if (getenv('MUT_DEBUG')) {
            fwrite(STDERR, "$rel: ".$e->getMessage().' line '.$e->getLine()."\n");
        }
        $new = $code;
    }
    @mkdir(dirname($dst.'/'.$rel), 0777, true);
    file_put_contents($dst.'/'.$rel, $new);
}
echo count(phpFiles($dst))." files -> $dst ($bad kept clean: mutation did not lint)\n";
