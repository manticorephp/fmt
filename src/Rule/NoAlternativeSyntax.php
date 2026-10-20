<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\FlatRule;
use Fmt\FlatTokens;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\NoAlternativeSyntaxFixer (priority 42). `if (x): ... endif;` -> `if (x) { ... }` (also elseif / else:, foreach,
 * while, for, switch, declare): the colon becomes `{` (spaces around it added where none), `else:` / `elseif (x):` get a `}` before them, `endif;` becomes `}`.
 * Without fix_non_monolithic_code only a file with one open tag and no inline HTML is fixed. Whole-file FlatTokens port, last token to first.
 */
final class NoAlternativeSyntax extends FlatRule
{
    public function __construct(private bool $nonMonolithic)
    {
        parent::__construct();
    }

    public function name(): string
    {
        return 'no_alternative_syntax';
    }

    public function priority(): int
    {
        return 42;
    }

    protected function candidate(Source $s): bool
    {
        $alt = false;
        $open = 0;
        $html = 0;
        for ($i = 0; $i < $s->n; $i++) {
            switch ($s->kind[$i]) {
                case \__McTokId::T_ENDIF:
                case \__McTokId::T_ENDFOREACH:
                case \__McTokId::T_ENDWHILE:
                case \__McTokId::T_ENDFOR:
                case \__McTokId::T_ENDSWITCH:
                case \__McTokId::T_ENDDECLARE:
                    $alt = true;
                    break;
                case \__McTokId::T_OPEN_TAG:
                case \__McTokId::T_OPEN_TAG_WITH_ECHO:
                    $open++;
                    break;
                case \__McTokId::T_INLINE_HTML:
                    $html++;
                    break;
            }
        }
        if (!$alt) {
            return false;
        }
        if ($this->nonMonolithic) {
            return true;
        }
        return $open === 1 && ($html === 0 || ($html === 1 && \preg_match('/^#!.+$/', $s->text(0)) === 1));
    }

    protected function run(Source $s, FlatTokens $t): void
    {
        for ($q = $t->count() - 1; $q >= 0; $q--) {
            if ($t->i[$q] === -1) {
                continue;
            }
            $k = $t->k[$q];
            if ($k === \__McTokId::T_ELSEIF) {
                $nx = $t->meaningful($q, 1);
                $pe = self::is($t, $nx, 40) ? $t->blockEnd($nx, 41) : $q;
                $colon = $t->meaningful($pe, 1);
                if (self::is($t, $colon, 58)) {
                    $this->addBraces($t, FlatTokens::code(\__McTokId::T_ELSEIF, 'elseif'), $q, $colon);
                }
            } elseif ($k === \__McTokId::T_ELSE) {
                $colon = $t->meaningful($q, 1);
                if (self::is($t, $colon, 58)) {
                    $this->addBraces($t, FlatTokens::code(\__McTokId::T_ELSE, 'else'), $q, $colon);
                }
            }
            $k = $t->k[$q];
            if ($k === \__McTokId::T_IF || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_WHILE || $k === \__McTokId::T_FOR || $k === \__McTokId::T_SWITCH
                || $k === \__McTokId::T_DECLARE) {
                $open = self::nextOf($t, $q, 40);
                if ($open < 0) {
                    continue;
                }
                $close = $t->blockEnd($open, 41);
                $after = $t->meaningful($close, 1);
                if (!self::is($t, $after, 58)) {
                    continue;
                }
                $items = [];
                if (!$t->isWs($after - 1)) {
                    $items[] = FlatTokens::ws(' ');
                }
                $items[] = FlatTokens::code(123, '{');
                if (!$t->isWs($after + 1)) {
                    $items[] = FlatTokens::ws(' ');
                }
                $t->clearAt($after);
                $t->insertAt($after, $items);
                continue;
            }
            if ($k === \__McTokId::T_ENDIF || $k === \__McTokId::T_ENDFOREACH || $k === \__McTokId::T_ENDWHILE || $k === \__McTokId::T_ENDFOR || $k === \__McTokId::T_ENDSWITCH
                || $k === \__McTokId::T_ENDDECLARE) {
                $nx = $t->meaningful($q, 1);
                $t->k[$q] = 125;
                $t->i[$q] = FlatTokens::NEW;
                $t->c[$q] = '}';
                if (self::is($t, $nx, 59)) {
                    $t->clearAt($nx);
                }
            }
        }
    }

    /** @param array{0:int,1:string,2:int} $token */
    private function addBraces(FlatTokens $t, array $token, int $q, int $colon): void
    {
        $items = [FlatTokens::code(125, '}'), FlatTokens::ws(' '), $token];
        if (!$t->isWs($q + 1)) {
            $items[] = FlatTokens::ws(' ');
        }
        $t->clearAt($q);
        $t->insertAt($q, $items);
        $colon += \count($items);
        $items = [FlatTokens::code(123, '{')];
        if (!$t->isWs($colon + 1)) {
            $items[] = FlatTokens::ws(' ');
        }
        $t->clearAt($colon);
        $t->insertAt($colon, $items);
    }
}
