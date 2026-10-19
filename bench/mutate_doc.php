<?php

declare(strict_types=1);

// usage: php bench/mutate_doc.php <src-corpus> <dst-dir> [seed]
// PHPDoc dirt, docblock by docblock (each mutation drawn at random, seeded per file): tag case and aliases (`@Param`, `@type`,
// `@link`, `@property-read`, `@inheritDocs`), inline tag shapes (`@{inheritdoc}`, `{ @link x }`, `{{@internal}}`), `@access` /
// `@package` lines, blank lines at the start / end / doubled, mangled indentation and stars, empty docblocks, scalar aliases and
// type case / order (`integer`, `NULL|Int`), `@var $x Type`, single-line `/**  @var  int  $x */`, summaries without a dot,
// annotation descriptions with one, `@return self` / `this`, blank lines after a docblock, annotations reordered, `//` comments
// turned into docblocks before statements. A file that no longer lints is kept clean.

require __DIR__.'/lib.php';
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

function p(int $pct): bool
{
    return mt_rand(0, 99) < $pct;
}

function pick(array $a): mixed
{
    return $a[mt_rand(0, count($a) - 1)];
}

function mutateDoc(string $d): string
{
    $multi = str_contains($d, "\n");
    // tag case / aliases
    if (p(25)) {
        $d = preg_replace_callback('/@(param|return|var|throws|see)\b/', static fn ($m) => '@'.pick([ucfirst($m[1]), strtoupper($m[1]), $m[1]]), $d, 1);
    }
    if (p(20)) {
        $d = preg_replace_callback('/@(var|see|property|const)\b/', static fn ($m) => '@'.['var' => pick(['type', 'var']), 'see' => 'link', 'property' => pick(['property-read', 'property-write']), 'const' => 'const'][$m[1]], $d, 1);
    }
    if (p(15)) {
        $d = preg_replace_callback('/\{@inheritdoc\}|@inheritdoc\b/i', static fn ($m) => pick(['@inheritDocs', '{@inheritDocs}', '@{inheritdoc}', '{ @inheritdoc }', '{{@inheritDoc}}', '@inheritDoc', '{@inheritdoc}', '@INHERITDOC']), $d);
    }
    if (p(10)) {
        $d = preg_replace('/\{@(link|see|internal|example) ([^}]*)\}/', pick(['{ @$1 $2 }', '@{$1 $2}', '{{@$1  $2 }}', '{@$1 $2  }']), $d);
    }
    // types
    if (p(25)) {
        $d = preg_replace_callback('/(@(?:param|return|var|property|throws)\s+)([^\s$]+)/', static function ($m) {
            $t = $m[2];
            $map = ['int' => pick(['integer', 'Int', 'INT', 'int']), 'bool' => pick(['boolean', 'Bool', 'bool']), 'float' => pick(['double', 'real', 'Float']),
                'callable' => pick(['callback', 'Callable']), 'string' => pick(['str', 'String', 'string']), 'null' => pick(['NULL', 'Null', 'null']),
                'mixed' => pick(['Mixed', 'mixed']), 'array' => pick(['Array', 'array']), 'self' => pick(['Self', 'self', '$this']), 'void' => pick(['Void', 'void']),
                'false' => 'FALSE', 'true' => 'True', 'resource' => 'Resource', 'object' => 'Object', 'iterable' => 'Iterable', 'static' => 'Static'];
            $t = preg_replace_callback('/(?<![\\\\\w$-])(int|bool|float|callable|string|null|mixed|array|self|void|false|true|resource|object|iterable|static)(?![\w\\\\-])/', static fn ($x) => $map[$x[1]], $t);
            if (str_contains($t, '|') && p(50)) {
                $parts = explode('|', $t);
                if (!str_contains($t, '<') && !str_contains($t, '{') && !str_contains($t, '(')) {
                    shuffle($parts);
                    $t = implode('|', $parts);
                }
            } elseif (p(30) && !str_contains($t, '|') && !str_starts_with($t, '?')) {
                $t = pick(['null|'.$t, $t.'|null', $t.'|NULL', '?'.$t]);
            }
            return $m[1].$t;
        }, $d);
    }
    // @var $x Type / names dropped / spacing
    if (p(15)) {
        $d = preg_replace('/@var(\s+)([^\s$*]+)(\s+)(\$\w+)/', '@var$1$4$3$2', $d, 1);
    }
    if (p(10)) {
        $d = preg_replace('/(@(?:var|type)\s+[^\s$*]+)\s+\$\w+/', '$1', $d, 1);
    }
    if (!$multi && p(30)) {
        $d = preg_replace('/^\/\*\*\s*/', pick(['/**  ', "/**\t", '/** ', '/**   ']), $d);
        $d = preg_replace('/\s*\*\/$/', pick(['  */', ' */', "\t*/", '*/']), $d);
        $d = preg_replace('/(@\w+)\s+/', pick(['$1  ', "$1\t", '$1 ']), $d, 1);
    }
    if (!$multi && p(10)) {
        $d = pick(['/** */', '/**  */', "/**\n */", "/**\n *\n */", "/**\n *\n *\n */"]);
    }
    if ($multi) {
        $lines = explode("\n", $d);
        $n = count($lines);
        if ($n > 2) {
            $ind = preg_match('/^(\s*)\*/', $lines[1], $m) ? $m[1] : ' ';
            if (p(15)) {
                array_splice($lines, 1, 0, [$ind.'*']);
            }
            if (p(15)) {
                array_splice($lines, count($lines) - 1, 0, [$ind.pick(['*', '* ', '*  '])]);
            }
            if (p(15)) {
                // a blank separator doubled / tripled
                foreach ($lines as $k => $l) {
                    if ($k > 0 && trim($l) === '*') {
                        array_splice($lines, $k, 0, array_fill(0, mt_rand(1, 2), $l));
                        break;
                    }
                }
            }
            if (p(10)) {
                foreach ($lines as $k => $l) {
                    if ($k > 0 && trim($l) === '*') {
                        unset($lines[$k]);
                        break;
                    }
                }
                $lines = array_values($lines);
            }
            if (p(15)) {
                $tags = [];
                foreach ($lines as $k => $l) {
                    if (preg_match('/^\s*\*\s*@(param|return|throws|access|see|var)\b/', $l)) {
                        $tags[] = $k;
                    }
                }
                if (count($tags) >= 2) {
                    $a = $tags[0];
                    $b = $tags[count($tags) - 1];
                    [$lines[$a], $lines[$b]] = [$lines[$b], $lines[$a]];
                }
            }
            if (p(6)) {
                array_splice($lines, 1, 0, [$ind.'* '.pick(['{@inheritdoc}', '@inheritDoc', '@covers Foo\\Bar', '@uses Baz', '@covers ::x', '@expectedException \\RuntimeException'])]);
            }
            if (p(10)) {
                array_splice($lines, count($lines) - 1, 0, [$ind.'* '.pick(['@access public', '@access private', '@package Foo\\Bar', '@subpackage Baz', '@package', '@ACCESS protected'])]);
            }
            if (p(15)) {
                // summary without / with a dot, annotation description with a dot
                foreach ($lines as $k => $l) {
                    if ($k > 0 && preg_match('/^\s*\*\s+[A-Za-z]/', $l)) {
                        $lines[$k] = preg_match('/\.$/', $l) ? substr($l, 0, -1) : $l.pick(['.', '', '!', '?']);
                        break;
                    }
                }
            }
            if (p(15)) {
                foreach ($lines as $k => $l) {
                    if (preg_match('/^\s*\*\s*@(param|return|throws)\s+\S+(\s+\$\w+)?\s+[A-Za-z]\w*/', $l) && !str_ends_with($l, '.')) {
                        $lines[$k] = $l.pick(['.', '...', '. ', '.']);
                        break;
                    }
                }
            }
            if (p(15)) {
                // indentation of the star lines mangled
                $mode = mt_rand(0, 3);
                foreach ($lines as $k => $l) {
                    if ($k === 0) {
                        continue;
                    }
                    $lines[$k] = match ($mode) {
                        0 => ltrim($l),
                        1 => "\t".ltrim($l),
                        2 => preg_replace('/^\s*\*/', $ind.'  *', $l),
                        default => p(50) ? '    '.$l : $l,
                    };
                }
            }
            if (p(5)) {
                // a line without a star
                foreach ($lines as $k => $l) {
                    if ($k > 0 && $k < count($lines) - 1 && preg_match('/^\s*\*\s+\w/', $l)) {
                        $lines[$k] = preg_replace('/^\s*\*\s+/', $ind, $l);
                        break;
                    }
                }
            }
            if (p(8)) {
                $lines[0] = '/**  '.pick(['', 'Summary on the first line.', '@var int']);
            }
            $d = implode("\n", $lines);
        }
    }
    if (p(8)) {
        $d = preg_replace('/@return\s+(static|\$this)\b/', '@return '.pick(['self', '$self', 'this', '@this', '$this', 'static']), $d);
    }
    if (p(5)) {
        $d = preg_replace('/(@param\s+)(\S+\s+)(\$\w+)/', '$1$2&$3', $d, 1);
    }
    return $d;
}

function id(array $t, int $i): int|string
{
    return is_array($t[$i]) ? $t[$i][0] : $t[$i];
}

function txt(array $t, int $i): string
{
    return is_array($t[$i]) ? $t[$i][1] : $t[$i];
}

function trivia(array $t, int $i): bool
{
    return isset($t[$i]) && is_array($t[$i]) && in_array($t[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}
/** a type text as written in a docblock, with a random variant */
function typeVariant(string $t): string
{
    $t = trim($t);
    if ('' === $t) {
        return pick(['', 'mixed', 'int']);
    }
    return match (mt_rand(0, 9)) {
        0 => str_starts_with($t, '?') ? 'null|'.substr($t, 1) : $t.'|null',
        1 => str_starts_with($t, '?') ? substr($t, 1).'|null' : $t,
        2 => ctype_upper($t[0]) ? '\\'.$t : $t,
        3 => 'mixed',
        4 => strtoupper($t[0]).substr($t, 1),
        default => $t,
    };
}

/**
 * Synthesized docblocks before members without one: `@param` / `@return` / `@var` repeating the signature (variants: nullable,
 * leading backslash, mixed, a description), `@inheritDoc`, `@final` / `@abstract`.
 */
function synthDoc(array $t, int $k): ?string
{
    $mods = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY];
    $fn = -1;
    $var = -1;
    for ($j = $k; isset($t[$j]); ++$j) {
        if (trivia($t, $j)) {
            continue;
        }
        $id = id($t, $j);
        if (T_FUNCTION === $id) {
            $fn = $j;
            break;
        }
        if (T_VARIABLE === $id) {
            $var = $j;
            break;
        }
        if (!in_array($id, $mods, true) && !in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, '?', T_ARRAY, '|'], true)) {
            return null;
        }
    }
    $lines = [];
    if ($var >= 0) {
        $type = '';
        for ($j = $k; $j < $var; ++$j) {
            if (!trivia($t, $j) && !in_array(id($t, $j), $mods, true)) {
                $type .= txt($t, $j);
            }
        }
        $lines[] = '@var '.typeVariant($type).(p(30) ? ' '.txt($t, $var) : '').(p(15) ? ' The value.' : '');
    } elseif ($fn >= 0) {
        $open = -1;
        for ($j = $fn; isset($t[$j]); ++$j) {
            if ('(' === id($t, $j)) {
                $open = $j;
                break;
            }
        }
        if ($open < 0) {
            return null;
        }
        $d = 0;
        $type = '';
        for ($j = $open; isset($t[$j]); ++$j) {
            $id = id($t, $j);
            if ('(' === $id || '[' === $id) {
                ++$d;
                if (1 === $d) {
                    continue;
                }
            } elseif (')' === $id || ']' === $id) {
                --$d;
                if (0 === $d) {
                    break;
                }
            }
            if (1 !== $d) {
                continue;
            }
            if (',' === $id) {
                $type = '';
            } elseif (T_VARIABLE === $id) {
                if (p(75)) {
                    $lines[] = '@param '.ltrim(typeVariant($type).' ').txt($t, $j).(p(15) ? ' The '.substr(txt($t, $j), 1) : '');
                }
                $type = '#';
            } elseif ('#' !== $type && !trivia($t, $j) && !in_array($id, $mods, true) && '&' !== $id && T_ELLIPSIS !== $id && T_ATTRIBUTE !== $id) {
                $type .= txt($t, $j);
            }
        }
        $ret = '';
        for (++$j; isset($t[$j]); ++$j) {
            $id = id($t, $j);
            if ('{' === $id || ';' === $id || T_DOUBLE_ARROW === $id) {
                break;
            }
            if (':' !== $id && !trivia($t, $j)) {
                $ret .= txt($t, $j);
            }
        }
        if ('' !== $ret && p(60)) {
            $lines[] = '@return '.typeVariant($ret).(p(10) ? ' The result.' : '');
        }
        if (p(8)) {
            array_unshift($lines, pick(['@inheritDoc', '{@inheritdoc}', '@inheritdoc']));
        }
        if (p(5)) {
            $lines[] = pick(['@final', '@abstract', '@internal']);
        }
    }
    if ([] === $lines) {
        return null;
    }
    if (1 === count($lines) && p(40)) {
        return '/** '.$lines[0].' */';
    }

    return "/**\n * ".implode("\n * ", $lines)."\n */";
}

[$self, $src, $dst, $seed] = $argv + [null, null, null, '7'];
if (null === $src || null === $dst) {
    fwrite(STDERR, "usage: php bench/mutate_doc.php <src-corpus> <dst-dir> [seed]\n");
    exit(2);
}
$src = realpath($src);
$changed = 0;
foreach (phpFiles($src) as $rel) {
    $code = file_get_contents($src.'/'.$rel);
    mt_srand((int) $seed + crc32($rel));
    $t = token_get_all($code);
    $out = '';
    $prevCode = ';';
    $prevDoc = false;
    foreach ($t as $k => $tok) {
        if (!trivia($t, $k)) {
            if (in_array($prevCode, [';', '{', '}'], true) && !$prevDoc && p(25) && null !== ($doc = synthDoc($t, $k))) {
                $ws = isset($t[$k - 1]) && T_WHITESPACE === id($t, $k - 1) ? txt($t, $k - 1) : "\n";
                $ind = substr($ws, (int) strrpos($ws, "\n") + 1);
                $out .= str_replace("\n", "\n".$ind, $doc)."\n".$ind;
            }
            $prevCode = id($t, $k);
            $prevDoc = false;
        } elseif (T_DOC_COMMENT === id($t, $k)) {
            $prevDoc = true;
        }
        if (!is_array($tok)) {
            $out .= $tok;
            continue;
        }
        [$id, $text] = $tok;
        if (T_DOC_COMMENT === $id && p(60)) {
            $text = mutateDoc($text);
            if (p(10)) {
                $text .= "\n";
            }
        } elseif (T_COMMENT === $id && str_starts_with($text, '//') && p(15)) {
            $body = trim(substr($text, 2));
            if ('' !== $body && !str_contains($body, '*/')) {
                $text = pick(['/** '.$body.' */', "/**\n * ".$body."\n */", '/** @var int $x '.$body.' */']);
            }
        } elseif (T_WHITESPACE === $id && p(3) && isset($t[$k - 1]) && is_array($t[$k - 1]) && T_DOC_COMMENT === $t[$k - 1][0]) {
            $text = "\n".$text;
        }
        $out .= $text;
    }
    if ($out !== $code) {
        $ok = true;
        try {
            token_get_all($out, TOKEN_PARSE);
        } catch (ParseError) {
            $ok = false;
        }
        if (!$ok) {
            $out = $code;
        } else {
            ++$changed;
        }
    }
    @mkdir(dirname($dst.'/'.$rel), 0777, true);
    file_put_contents($dst.'/'.$rel, $out);
}
echo "$changed files changed\n";
