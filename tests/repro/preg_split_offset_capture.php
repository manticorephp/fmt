<?php
// preg_split ignores PREG_SPLIT_OFFSET_CAPTURE: it returns plain strings where Zend returns [string, offset] pairs.
// Zend:      A:@|0 A:ab|1 A:c|4   (every element is an array: [text, byte offset])
// Manticore: S:@ S:ab S:c         (plain strings; $m[1] is then the second CHARACTER of the piece)
// Effect: Doctrine\Annotation\DocLexer (src/Doc/DoctrineTokens.php) positions every token wrong -- `@template` became `@ttemplate`.
foreach (preg_split('/(\w+)|\s+|(.)/', '@ab c', -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE) as $m) {
    echo is_array($m) ? 'A:' . $m[0] . '|' . $m[1] : 'S:' . $m, "\n";
}
