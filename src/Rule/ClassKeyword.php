<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\NativeNames;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\LanguageConstruct\ClassKeywordFixer (priority 8, risky). A string literal that names an existing class / interface / trait / enum
 * becomes `\Name::class`. Upstream asks its running interpreter (class_exists + exact-case ReflectionClass::getName); the answer here is the oracle's:
 * NativeNames::CLASS_LIKE = the internal classes of the oracle php + whatever upstream's composer autoloader can load.
 */
final class ClassKeyword extends FlatRule
{
    /** @var array<string, bool> */
    private array $known = [];
    private int $from = -1;
    private int $to = -1;

    public function __construct()
    {
        parent::__construct();
        foreach (\explode("\n", NativeNames::CLASS_LIKE) as $c) {
            $this->known[$c] = true;
        }
    }

    public function name(): string
    {
        return 'class_keyword';
    }

    public function priority(): int
    {
        return 8;
    }

    private static function nameOf(string $literal): string
    {
        return \str_replace('\\\\', '\\', \ltrim(\substr($literal, 1, -1), '\\'));
    }

    private function exists(string $name): bool
    {
        return isset($this->known[$name]);
    }

    protected function candidate(Source $s): bool
    {
        $this->from = -1;
        for ($i = 0; $i < $s->n; $i++) {
            if ($s->kind[$i] === \__McTokId::T_CONSTANT_ENCAPSED_STRING && $this->exists(self::nameOf($s->text($i)))) {
                if ($this->from < 0) {
                    $this->from = $i;
                }
                $this->to = $i;
            }
        }
        return $this->from >= 0;
    }

    protected function window(Source $s): array
    {
        return [$this->from, $this->to, false];
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        /** @var array<int, string> $subs */
        $subs = [];
        for ($index = $t->count() - 1; $index >= 0; $index--) {
            if ($t->i[$index] === -1 || $t->k[$index] !== \__McTokId::T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $name = self::nameOf($t->content($index));
            if ($this->exists($name)) {
                $subs[$index] = $name;
                $t->clearAt($index);
            }
        }
        if ($subs === []) {
            return;
        }
        // one rebuild instead of an insertAt (three array_splice) per hit; each hit becomes the three tokens followed by the emptied slot, as clearAt + insertAt leave it
        /** @var int[] $k */
        $k = [];
        /** @var string[] $c */
        $c = [];
        /** @var int[] $i */
        $i = [];
        for ($q = 0, $n = $t->count(); $q < $n; $q++) {
            if (isset($subs[$q])) {
                $k[] = \__McTokId::T_NAME_FULLY_QUALIFIED;
                $c[] = '\\'.$subs[$q];
                $i[] = FlatTokens::NEW;
                $k[] = \__McTokId::T_DOUBLE_COLON;
                $c[] = '::';
                $i[] = FlatTokens::NEW;
                $k[] = \__McTokId::T_CLASS;
                $c[] = 'class';
                $i[] = FlatTokens::NEW;
            }
            $k[] = $t->k[$q];
            $c[] = $t->c[$q];
            $i[] = $t->i[$q];
        }
        $t->k = $k;
        $t->c = $c;
        $t->i = $i;
    }
}
