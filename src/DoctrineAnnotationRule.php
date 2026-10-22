<?php

declare(strict_types=1);

namespace Fmt;

use Fmt\Doc\DoctrineToken;
use Fmt\Doc\DoctrineTokens;

/**
 * AbstractDoctrineAnnotationFixer on docblock pieces: a docblock followed by a class or a class element of a `class` (not an interface / enum / trait) is split into
 * Doctrine annotation tokens (tags in `ignored_tags` are not annotations), `fixAnnotations` edits them, the code goes back.
 */
abstract class DoctrineAnnotationRule extends DocRule
{
    /** @var array<int, int[]> */
    private array $elements = [];

    /** @param string[] $ignoredTags */
    public function __construct(protected array $ignoredTags)
    {
    }

    /** the list upstream ignores by default */
    public const IGNORED = ['abstract', 'access', 'code', 'deprec', 'encode', 'exception', 'final', 'ingroup', 'inheritdoc', 'inheritDoc', 'magic', 'name', 'toc', 'tutorial',
        'private', 'static', 'staticvar', 'staticVar', 'throw', 'api', 'author', 'category', 'copyright', 'deprecated', 'example', 'filesource', 'global', 'ignore',
        'internal', 'license', 'link', 'method', 'package', 'param', 'property', 'property-read', 'property-write', 'return', 'see', 'since', 'source', 'subpackage', 'throws',
        'todo', 'TODO', 'usedBy', 'uses', 'var', 'version', 'after', 'afterClass', 'backupGlobals', 'backupStaticAttributes', 'before', 'beforeClass', 'codeCoverageIgnore',
        'codeCoverageIgnoreStart', 'codeCoverageIgnoreEnd', 'covers', 'coversDefaultClass', 'coversNothing', 'dataProvider', 'depends', 'expectedException',
        'expectedExceptionCode', 'expectedExceptionMessage', 'expectedExceptionMessageRegExp', 'group', 'large', 'medium', 'preserveGlobalState', 'requires',
        'runTestsInSeparateProcesses', 'runInSeparateProcess', 'small', 'test', 'testdox', 'ticket', 'uses', 'SuppressWarnings', 'noinspection', 'package_version',
        'enduml', 'startuml', 'psalm', 'phpstan', 'template', 'fix', 'FIXME', 'fixme', 'override'];

    /**
     * @param DoctrineToken[] $tokens edited in place (insertions go through the returned list)
     * @return DoctrineToken[]|null the new list, null: upstream throws here (the file is left as it is)
     */
    abstract protected function fixAnnotations(array $tokens): ?array;

    private bool $haveElements = false;

    public function prepare(Source $s): bool
    {
        $this->elements = [];
        $this->haveElements = false;
        return true;
    }

    private function accepts(Source $s, int $i): bool
    {
        $n = $s->n;
        $idx = $i;
        while ($idx < $n && $s->ct[$idx] === 0 && ($s->kind[$idx] === \__McTokId::T_ABSTRACT || $s->kind[$idx] === \__McTokId::T_FINAL || $s->kind[$idx] === \__McTokId::T_READONLY)) {
            $idx++;
        }
        if ($idx >= $n) {
            return false;
        }
        if ($s->kind[$idx] === \__McTokId::T_CLASS && $s->ct[$idx] === 0) {
            return true;
        }
        while ($idx < $n && ($s->ct[$idx] === CT::NULLABLE_TYPE || ($s->ct[$idx] === 0 && ($s->kind[$idx] === \__McTokId::T_PUBLIC || $s->kind[$idx] === \__McTokId::T_PROTECTED
            || $s->kind[$idx] === \__McTokId::T_PRIVATE || $s->kind[$idx] === \__McTokId::T_FINAL || $s->kind[$idx] === \__McTokId::T_ABSTRACT
            || $s->kind[$idx] === \__McTokId::T_NS_SEPARATOR || $s->kind[$idx] === \__McTokId::T_STRING || $s->kind[$idx] === \__McTokId::T_READONLY
            || $s->kind[$idx] === \__McTokId::T_PRIVATE_SET || $s->kind[$idx] === \__McTokId::T_PROTECTED_SET || $s->kind[$idx] === \__McTokId::T_PUBLIC_SET)))) {
            $idx++;
        }
        if (!$this->haveElements) {
            $this->haveElements = true;
            foreach (Analyzer::classyElements($s) as $at => [$type, $cls]) {
                $this->elements[$at] = [$cls];
            }
        }
        if (!isset($this->elements[$idx])) {
            return false;
        }
        $cls = $this->elements[$idx][0];
        return $s->kind[$cls] === \__McTokId::T_CLASS && $s->ct[$cls] === 0;
    }

    /** @param string[] $p */
    public function fixDoc(string $doc, Source $s, int $i, array $p, int $k): string
    {
        if ($i >= $s->n || !\str_contains($doc, '@') || !$this->accepts($s, $i)) {
            return $doc;
        }
        $tokens = DoctrineTokens::fromDocComment($doc, $this->ignoredTags);
        if ($tokens === null) {
            $s->abort = true;
            return $doc;
        }
        $fixed = $this->fixAnnotations($tokens);
        if ($fixed === null) {
            $s->abort = true;
            return $doc;
        }
        return DoctrineTokens::code($fixed);
    }
}
