<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\CT;
use Fmt\FlatNamespaces;
use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\Import\GroupImportFixer (priority 0). The single imports that share a namespace (and an import type) are replaced by one group
 * `use A\B\{C, D as E};`, inserted where the first one stood (found as the first emptied token behind its start index); groups come by namespace length then name, types in
 * the order class, function, const; `group_types` lists the types to group. Whole-file FlatTokens port: the declarations keep their load-time indexes while the insertions
 * shift the list, as upstream's.
 */
final class GroupImport extends FlatRule
{
    /** @param string[] $groupTypes classy / functions / constants */
    public function __construct(private array $groupTypes)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'group_import';
    }

    public function priority(): int
    {
        return 0;
    }

    protected function candidate(Source $s): bool
    {
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_USE && $s->ct[$i] === 0) {
                return true;
            }
        }
        return false;
    }

    private static function nsWithSlash(string $full): string
    {
        $p = \strrpos($full, '\\');
        if ($p === false || $p === 0) {
            return $full;
        }
        return \substr($full, 0, $p + 1);
    }

    /** @param array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int} $a */
    private static function key(array $a): string
    {
        return self::nsWithSlash($a[1]) . $a[0];
    }

    private ?Source $src = null;

    protected function run(Source $s, FlatTokens $t): void
    {
        $this->src = $s;
        $decls = FlatNamespaces::uses($s, $t, false);
        if ($decls === []) {
            return;
        }
        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($decls as $d) {
            $k = self::key($d);
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        /** @var array<int, array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int}> $same */
        $same = [];
        foreach ($decls as $d) {
            if ($counts[self::key($d)] > 1) {
                $same[] = $d;
            }
        }
        // usort (stable): namespace length, then full name
        $sorted = [];
        foreach ($same as $d) {
            $pos = \count($sorted);
            while ($pos > 0 && self::cmp($sorted[$pos - 1], $d) > 0) {
                $pos--;
            }
            $next = [];
            for ($x = 0; $x < $pos; $x++) {
                $next[] = $sorted[$x];
            }
            $next[] = $d;
            for ($x = $pos; $x < \count($sorted); $x++) {
                $next[] = $sorted[$x];
            }
            $sorted = $next;
        }
        if ($sorted === []) {
            return;
        }
        $names = [1 => 'classy', 2 => 'functions', 3 => 'constants'];
        $byType = [];
        foreach ([1, 2, 3] as $type) {
            $list = [];
            foreach ($sorted as $d) {
                if ($d[0] === $type) {
                    $list[] = $d;
                }
            }
            if ($list !== []) {
                $byType[$type] = $list;
            }
        }
        foreach ($byType as $type => $uses) {
            if (\in_array($names[$type], $this->groupTypes, true)) {
                $this->removeSingles($t, $uses);
            }
        }
        foreach ($byType as $type => $uses) {
            if (\in_array($names[$type], $this->groupTypes, true)) {
                $this->addGroups($s, $t, $uses);
            }
        }
    }

    /**
     * @param array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int} $a
     * @param array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int} $b
     */
    private static function cmp(array $a, array $b): int
    {
        $d = \strlen(self::nsWithSlash($a[1])) <=> \strlen(self::nsWithSlash($b[1]));
        return $d !== 0 ? $d : $a[1] <=> $b[1];
    }

    /** @param array<int, array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int}> $uses */
    private function removeSingles(FlatTokens $t, array $uses): void
    {
        foreach ($uses as $d) {
            $index = $d[5];
            $end = $d[6];
            while ($index !== $end) {
                $k = $t->k[$index];
                if (($t->i[$index] !== -1 && ($k === \__McTokId::T_USE || $k === \__McTokId::T_STRING || $k === \__McTokId::T_NS_SEPARATOR || $k === \__McTokId::T_AS
                        || $k === CT::CONST_IMPORT || $k === CT::FUNCTION_IMPORT)) || self::realWs($t, $index)) {
                    $t->clearAt($index);
                }
                ++$index;
            }
            if ($index < $t->count() && self::is($t, $index, 59)) {
                $t->clearAt($index);
            }
            ++$index;
            if ($index < $t->count() && self::realWs($t, $index)) {
                $t->clearAt($index);
            }
        }
    }

    /** a T_WHITESPACE token (an emptied one has no id) */
    private static function realWs(FlatTokens $t, int $q): bool
    {
        return $t->k[$q] === FlatTokens::WS && $t->c[$q] !== '';
    }

    private static function emptied(FlatTokens $t, int $q): bool
    {
        return $t->k[$q] === FlatTokens::WS && $t->c[$q] === '' && $t->i[$q] === -1;
    }

    /** @param array<int, array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int}> $uses */
    private function addGroups(Source $s, FlatTokens $t, array $uses): void
    {
        $current = null;
        $insert = $uses[0][5];
        if ($insert >= $t->count()) {
            $s->abort = true;
            return;
        }
        if (!self::emptied($t, $insert)) {
            do {
                ++$insert;
                if ($insert >= $t->count()) {
                    $s->abort = true; // upstream reads past the end: the file stays as it is
                    return;
                }
            } while (!self::emptied($t, $insert));
        }
        $count = \count($uses);
        foreach ($uses as $x => $d) {
            if ($current === null || self::different($current, $d)) {
                $current = $d;
                $insert += $this->newGroup($t, $insert, $d, \rtrim(self::nsWithSlash($d[1]), '\\'));
                continue;
            }
            $new = [FlatTokens::code(44, ','), FlatTokens::ws(' ')];
            if ($d[3] === 1) {
                $t->insertAt($insert, $new);
                $insert += \count($new);
                $new = [];
                $insert += $this->aliasPart($t, $insert, $d);
            }
            $new[] = FlatTokens::code(\__McTokId::T_STRING, $d[2]);
            if ($x + 1 >= $count || self::different($current, $uses[$x + 1])) {
                $new[] = FlatTokens::code(CT::GROUP_IMPORT_BRACE_CLOSE, '}');
                $new[] = FlatTokens::code(59, ';');
                $new[] = FlatTokens::ws("\n");
            }
            $t->insertAt($insert, $new);
            $insert += \count($new);
        }
    }

    /**
     * @param array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int} $a
     * @param array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int} $b
     */
    private static function different(array $a, array $b): bool
    {
        return self::nsWithSlash($a[1]) !== self::nsWithSlash($b[1]) || $a[0] !== $b[0];
    }

    /** @param array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int} $d */
    private function aliasPart(FlatTokens $t, int $at, array $d): int
    {
        $p = \strripos($d[1], '\\');
        $new = [FlatTokens::code(\__McTokId::T_STRING, \substr($d[1], ($p === false ? 0 : $p) + 1)), FlatTokens::ws(' '), FlatTokens::code(\__McTokId::T_AS, 'as'), FlatTokens::ws(' ')];
        $t->insertAt($at, $new);
        return \count($new);
    }

    /** @param array{0: int, 1: string, 2: string, 3: int, 4: int, 5: int, 6: int} $d */
    private function newGroup(FlatTokens $t, int $at, array $d, string $namespace): int
    {
        $inserted = 0;
        $new = [FlatTokens::code(\__McTokId::T_USE, 'use'), FlatTokens::ws(' ')];
        if ($d[0] === 2 || $d[0] === 3) {
            $new[] = $d[0] === 2 ? FlatTokens::code(CT::FUNCTION_IMPORT, 'function') : FlatTokens::code(CT::CONST_IMPORT, 'const');
            $new[] = FlatTokens::ws(' ');
        }
        foreach (\explode('\\', $namespace) as $part) {
            if ($part === '' && $this->src !== null) {
                $this->src->abort = true; // `use \A\B;`: new Token([T_STRING, '']) throws, the file stays as it is
            }
            $new[] = FlatTokens::code(\__McTokId::T_STRING, $part);
            $new[] = FlatTokens::code(\__McTokId::T_NS_SEPARATOR, '\\');
        }
        $new[] = FlatTokens::code(CT::GROUP_IMPORT_BRACE_OPEN, '{');
        $c = \count($new);
        $t->insertAt($at, $new);
        $inserted += $c;
        $at += $c;
        if ($d[3] === 1) {
            $n = $this->aliasPart($t, $at + 1, $d) + 1;
            $inserted += $n;
            $at += $n;
        }
        $t->insertAt($at, [FlatTokens::code(\__McTokId::T_STRING, $d[2])]);
        return $inserted + 1;
    }
}
