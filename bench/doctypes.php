<?php

declare(strict_types=1);

// usage: php -d xdebug.mode=off bench/doctypes.php <corpus-dir>...
// Differential check of the docblock model under Zend: for every docblock of the corpora, upstream's DocBlock / Annotation /
// TypeExpression vs src/Doc (lines, annotations, tag names, types content, the inner type tree). Prints the first mismatches.

require __DIR__.'/lib.php';
require UPSTREAM.'/vendor/autoload.php';
foreach (['Preg', 'Doc/NsContext', 'Doc/Line', 'Doc/Tag', 'Doc/TypeExpression', 'Doc/Annotation', 'Doc/DocBlock'] as $f) {
    require __DIR__.'/../src/'.$f.'.php';
}

use PhpCsFixer\DocBlock\DocBlock as UDoc;
use PhpCsFixer\DocBlock\TypeExpression as UType;
use Fmt\Doc\DocBlock as MDoc;
use Fmt\Doc\TypeExpression as MType;

function utree(UType $t): string
{
    $out = [];
    $t->walkTypes(static function (UType $x) use (&$out): void { $out[] = $x->toString().($x->isCompositeType() ? '#'.$x->getTypesGlue() : ''); });

    return implode(' ; ', $out);
}

function mtree(MType $t): string
{
    $out = [];
    $t->walkTypes(static function (MType $x) use (&$out): void { $out[] = $x->toString().($x->isCompositeType() ? '#'.$x->getTypesGlue() : ''); });

    return implode(' ; ', $out);
}

$bad = 0;
$docs = 0;
$types = 0;
foreach (array_slice($argv, 1) as $dir) {
    foreach (phpFiles(realpath($dir)) as $rel) {
        foreach (@token_get_all(file_get_contents($dir.'/'.$rel)) as $tok) {
            if (!is_array($tok) || T_DOC_COMMENT !== $tok[0]) {
                continue;
            }
            ++$docs;
            $u = new UDoc($tok[1]);
            $m = new MDoc($tok[1]);
            $ua = $u->getAnnotations();
            $ma = $m->getAnnotations();
            $why = null;
            if (count($u->getLines()) !== count($m->lines)) {
                $why = 'line count';
            } elseif (count($ua) !== count($ma)) {
                $why = 'annotation count';
            } else {
                foreach ($ua as $k => $a) {
                    $b = $ma[$k];
                    if ($a->getStart() !== $b->start || $a->getEnd() !== $b->end || $a->getTag()->getName() !== $b->getTag()->getName()) {
                        $why = "annotation $k shape";
                        break;
                    }
                    if (!$a->supportTypes()) {
                        continue;
                    }
                    ++$types;
                    $ut = $a->getTypeExpression();
                    $mt = $b->getTypeExpression();
                    if ((null === $ut) !== (null === $mt)) {
                        $why = "annotation $k types null";
                        break;
                    }
                    if (null !== $ut && (utree($ut) !== mtree($mt) || $a->getVariableName() !== $b->getVariableName())) {
                        $why = "annotation $k types ".utree($ut).' vs '.mtree($mt);
                        break;
                    }
                }
            }
            if (null !== $why && ++$bad <= 10) {
                echo "MISMATCH $rel: $why\n".json_encode($tok[1])."\n";
            }
        }
    }
}
echo "$docs docblocks, $types typed annotations, $bad mismatches\n";
exit($bad > 0 ? 1 : 0);
