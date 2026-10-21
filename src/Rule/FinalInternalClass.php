<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\Analyzer;
use Fmt\CT;
use Fmt\Doc\DocBlock;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Preg;
use Fmt\Source;
use Fmt\Trivia;

/**
 * Parity target: PhpCsFixer\Fixer\ClassNotation\FinalInternalClassFixer (priority 67, risky). A class (not abstract, not final, not anonymous) whose class-level docblock annotations or
 * attributes include one of `include` (default `internal`) and none of `exclude` (default `final`, `Entity`, `ORM\Entity`, ..) becomes `final`; consider_absent_docblock_as_internal_class
 * also takes classes without any. The deprecated options annotation_include / annotation_exclude are refused. Whole-file FlatTokens port, last class first.
 */
final class FinalInternalClass extends FlatRule
{
    /** @var array<string, bool> */
    private array $include = [];
    /** @var array<string, bool> */
    private array $exclude = [];

    /**
     * @param string[] $include
     * @param string[] $exclude
     */
    public function __construct(array $include, array $exclude, private bool $absent, private string $ruleName = 'final_internal_class')
    {
        parent::__construct();
        foreach ($include as $k) {
            $this->include[\strtolower($k[0] === '@' ? \substr($k, 1) : $k)] = true;
        }
        foreach ($exclude as $k) {
            $this->exclude[\strtolower($k[0] === '@' ? \substr($k, 1) : $k)] = true;
        }
        foreach ($this->include as $k => $_) {
            if (isset($this->exclude[(string) $k])) {
                throw new \InvalidArgumentException('Annotation cannot be used in both "include" and "exclude" list, got duplicates: ' . $k . '.');
            }
        }
    }

    public function name(): string
    {
        return $this->ruleName;
    }

    public function priority(): int
    {
        return 67;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_CLASS && $s->ct[$i] === 0) {
                return true;
            }
        }
        return false;
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, bool> $inserts */
        $inserts = [];
        for ($index = $t->count() - 1; $index >= 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_CLASS || !$this->candidateClass($s, $t, $index)) {
                continue;
            }
            $inserts[$index] = true;
        }
        \krsort($inserts);
        foreach ($inserts as $at => $_) {
            $t->insertAt($at, [FlatTokens::code(\__McTokId::T_FINAL, 'final'), FlatTokens::ws(' ')]);
        }
    }

    private function candidateClass(Source $s, FlatTokens $t, int $index): bool
    {
        if ($t->i[$index] >= 0 && Analyzer::isAnonymousClass($s, $t->i[$index])) {
            return false;
        }
        $x = $index;
        while (true) {
            $x = $t->meaningful($x, -1);
            if ($x < 0) {
                break;
            }
            if (self::is($t, $x, \__McTokId::T_FINAL) || self::is($t, $x, \__McTokId::T_ABSTRACT)) {
                return false;
            }
            if (self::is($t, $x, \__McTokId::T_READONLY)) {
                continue;
            }
            break;
        }
        $decisions = [];
        $cur = $index;
        while ($cur >= 0) {
            $cur = $t->nonWhitespace($cur, -1);
            if ($cur < 0) {
                break;
            }
            $isComment = $t->k[$cur] === FlatTokens::COMMENT;
            $code = $t->i[$cur] !== -1;
            if (!($isComment || ($code && ($t->k[$cur] === CT::ATTRIBUTE_CLOSE || $t->k[$cur] === \__McTokId::T_READONLY)))) {
                break;
            }
            if ($code && $t->k[$cur] === CT::ATTRIBUTE_CLOSE) {
                $start = $t->blockStart($cur, \__McTokId::T_ATTRIBUTE);
                $decisions[] = $this->byAttribute($t, $start, $cur);
                $cur = $start;
            }
            if ($t->k[$cur] === FlatTokens::COMMENT && Trivia::isDoc($t->c[$cur])) {
                $decisions[] = $this->byDoc($t, $cur);
            }
        }
        foreach ($decisions as $d) {
            if ($d === 0) {
                return false;
            }
        }
        foreach ($decisions as $d) {
            if ($d === 1) {
                return true;
            }
        }
        return $decisions === [] && $this->absent;
    }

    /** @param array<string, bool> $tags */
    private function isInclude(array $tags): bool
    {
        if (\count($this->include) === 0) {
            return true;
        }
        foreach ($tags as $k => $_) {
            if (isset($this->include[(string) $k])) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, bool> $tags */
    private function isExclude(array $tags): bool
    {
        foreach ($tags as $k => $_) {
            if (isset($this->exclude[(string) $k])) {
                return true;
            }
        }
        return false;
    }

    /** 0 = false, 1 = true, 2 = null */
    private function byDoc(FlatTokens $t, int $index): int
    {
        $doc = new DocBlock($t->c[$index]);
        /** @var array<string, bool> $tags */
        $tags = [];
        foreach ($doc->getAnnotations() as $a) {
            if (!Preg::match('/@([^\(\s]+)/', $a->getContent(), $m)) {
                continue;
            }
            $tags[\strtolower(\substr($m[0], 1))] = true;
        }
        if ($this->isExclude($tags)) {
            return 0;
        }
        return $this->isInclude($tags) ? 1 : 2;
    }

    private function byAttribute(FlatTokens $t, int $start, int $end): int
    {
        /** @var array<string, bool> $cands */
        $cands = [];
        $str = '';
        $cur = $start;
        while ($cur < $end && ($cur = $t->meaningful($cur, 1)) >= 0) {
            if (!($t->i[$cur] !== -1 && ($t->k[$cur] === \__McTokId::T_STRING || $t->k[$cur] === \__McTokId::T_NS_SEPARATOR))) {
                if ($str !== '') {
                    $cands[$str] = true;
                    $str = '';
                }
                continue;
            }
            $str .= \strtolower($t->content($cur));
        }
        if ($this->isExclude($cands)) {
            return 0;
        }
        return $this->isInclude($cands) ? 1 : 2;
    }
}
