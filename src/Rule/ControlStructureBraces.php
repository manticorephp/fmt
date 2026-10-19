<?php

declare(strict_types=1);

namespace Fmt\Rule;

use Fmt\EditRule;
use Fmt\Gap;
use Fmt\Source;

/**
 * Parity target: PhpCsFixer\Fixer\ControlStructure\ControlStructureBracesFixer (priority 1). A control structure whose body is a single
 * statement gets ` {` after its condition and ` }` (`; }` when the statement does not end in `;` / `}`) after the statement. Upstream's
 * backward walk runs on the Source with VIRTUAL inserts: an inner structure (walked first) leaves `{` after its condition and its `}`
 * after its statement end, and an outer structure's statement-end search goes through them exactly as upstream's goes through the
 * inserted tokens (the outer `}` lands after the inner one). The inserts become edits; nothing is copied or shifted.
 */
final class ControlStructureBraces extends EditRule
{

    private ?Source $s = null;
    /** @var array<int, string> token index => text inserted right after it (before its gap), in insertion order */
    private array $ins = [];
    /** @var array<int, int> condition end (or keyword) that got a virtual `{` => the token its virtual `}` follows */
    private array $open = [];
    /** set by statementEnd: the end it returned is a virtual `}` (else the token itself) */
    private bool $virtual = false;
    /** @var array<int, string> gap index => the gap rewritten (`; }` inserted after a comment before `?>`) */
    private array $gapEdit = [];
    private bool $fail = false;

    public function name(): string
    {
        return 'control_structure_braces';
    }

    public function priority(): int
    {
        return 1;
    }

    private function eff(int $i): int
    {
        $s = $this->s;
        return $s->ct[$i] !== 0 ? $s->ct[$i] : $s->kind[$i];
    }

    /** 1 = a control keyword with a condition, 2 = without one (do, else, finally, try), 0 = none */
    private static function ctl(int $k): int
    {
        if ($k === \__McTokId::T_IF || $k === \__McTokId::T_ELSEIF || $k === \__McTokId::T_FOR || $k === \__McTokId::T_FOREACH || $k === \__McTokId::T_WHILE
            || $k === \__McTokId::T_SWITCH || $k === \__McTokId::T_CATCH || $k === \__McTokId::T_DECLARE) {
            return 1;
        }
        if ($k === \__McTokId::T_ELSE || $k === \__McTokId::T_DO || $k === \__McTokId::T_TRY || $k === \__McTokId::T_FINALLY) {
            return 2;
        }
        return 0;
    }

    private function isControl(int $i): bool
    {
        $s = $this->s;
        return $s->ct[$i] === 0 && self::ctl($s->kind[$i]) !== 0;
    }

    public function fixFile(Source $s): void
    {
        $this->s = $s;
        $this->ins = [];
        $this->open = [];
        $this->gapEdit = [];
        $this->fail = false;
        $n = $s->n;
        for ($i = $n - 1; $i >= 0; $i--) {
            if ($s->ct[$i] !== 0 || self::ctl($s->kind[$i]) === 0) {
                continue;
            }
            $k = $s->kind[$i];
            if ($k === \__McTokId::T_ELSE && $i + 1 < $n && $this->eff($i + 1) === \__McTokId::T_IF) {
                continue;
            }
            $pe = $this->parenEnd($i);
            if ($this->fail || $pe + 1 >= $n) {
                continue;
            }
            $na = $pe + 1;
            $ka = $this->eff($na);
            if ($ka === 59 || $ka === 123 || $ka === 58 || $ka === \__McTokId::T_CLOSE_TAG) {
                continue;
            }
            $se = -1;
            $this->virtual = false;
            if ($s->ct[$na] === 0 && ($ka === \__McTokId::T_IF || $ka === \__McTokId::T_FOR || $ka === \__McTokId::T_FOREACH || $ka === \__McTokId::T_SWITCH || $ka === \__McTokId::T_WHILE)) {
                $pb = $na + 1 < $n ? $s->pair[$na + 1] : -1;
                if ($pb > 0 && $pb + 1 < $n && $this->eff($pb + 1) === 58) {
                    $se = $this->altEnd($na);
                    if ($se >= 0 && ($s->kind[$se] === \__McTokId::T_ELSE || $s->kind[$se] === \__McTokId::T_ELSEIF)) {
                        $s->abort = true; // `}` lands after the `else` / `elseif` of an inner `if (..):`: upstream's result does not lint
                        return;
                    }
                    if ($se >= 0 && $se + 1 < $n && $this->eff($se + 1) === 59) {
                        $se++;
                    }
                }
            }
            if ($se < 0) {
                $se = $this->statementEnd($pe);
            }
            if ($this->fail || $se === -1) {
                $s->abort = true; // upstream runs off the end of the file and throws: the file stays as it is
                return;
            }
            $closeText = ' }';
            if (!$this->virtual && ($se < 0 || !($s->ct[$se] === 0 && ($s->kind[$se] === 59 || $s->kind[$se] === 125)))) {
                $closeText = '; }';
            }
            $this->insertAfter($se, $closeText);
            $this->ins[$pe] = ($this->ins[$pe] ?? '') . ' {';
            $this->open[$pe] = $se;
        }
        foreach ($this->ins as $t => $txt) {
            if ($t >= 0) {
                $s->edit(Source::gapAt($t + 1), Source::gapAt($t + 1), $txt);
            }
        }
        foreach ($this->gapEdit as $g => $txt) {
            $s->edit(Source::gapAt($g), Source::gapAt($g) + 1, $txt);
        }
    }

    /** a statement end below -1 is "after the last comment piece of gap -(se + 2)" (the `?>` case) */
    private function insertAfter(int $se, string $txt): void
    {
        if ($se >= 0) {
            $this->ins[$se] = ($this->ins[$se] ?? '') . $txt;
            return;
        }
        $g = -$se - 2;
        $cur = $this->gapEdit[$g] ?? $this->s->gapBefore[$g];
        $p = Gap::pieces($cur);
        $k = \count($p) - 1;
        while ($k >= 0 && Gap::isSpace($p[$k])) {
            $k--;
        }
        \array_splice($p, $k + 1, 0, [$txt]);
        $this->gapEdit[$g] = \implode('', $p);
    }

    private function parenEnd(int $q): int
    {
        $s = $this->s;
        if (self::ctl($s->kind[$q]) === 2) {
            return $q;
        }
        if ($q + 1 >= $s->n || $this->eff($q + 1) !== 40) {
            return $q;
        }
        $e = $s->pair[$q + 1];
        if ($e < 0) {
            $this->fail = true;
            return $q;
        }
        return $e;
    }

    /** findStatementEnd; sets $this->virtual when the end is a virtual `}`. A value below -1 encodes a comment piece (insertAfter). */
    private function statementEnd(int $pe): int
    {
        $s = $this->s;
        $n = $s->n;
        $this->virtual = false;
        if (isset($this->open[$pe])) {
            $this->virtual = true; // the next token is the `{` inserted for an inner structure: its block ends at its virtual `}`
            return $this->open[$pe];
        }
        $nx = $pe + 1;
        if ($nx >= $n) {
            return -1;
        }
        $kn = $this->eff($nx);
        if ($kn === 123) {
            return $s->pair[$nx];
        }
        if ($this->isControl($nx)) {
            $end = $this->statementEnd($this->parenEnd($nx));
            if ($end === -1) {
                return -1;
            }
            if ($kn === \__McTokId::T_IF || $kn === \__McTokId::T_TRY || $kn === \__McTokId::T_DO) {
                while (true) {
                    $c = $end + 1;
                    if ($end < 0 || $c >= $n || !$this->isControl($c)) {
                        break;
                    }
                    $kc = $s->kind[$c];
                    $cont = $kn === \__McTokId::T_IF ? ($kc === \__McTokId::T_ELSE || $kc === \__McTokId::T_ELSEIF)
                        : ($kn === \__McTokId::T_DO ? $kc === \__McTokId::T_WHILE : ($kc === \__McTokId::T_CATCH || $kc === \__McTokId::T_FINALLY));
                    if (!$cont) {
                        break;
                    }
                    $end = $this->statementEnd($this->parenEnd($c));
                    if ($end === -1) {
                        return -1;
                    }
                    if (($kn === \__McTokId::T_IF && $kc === \__McTokId::T_ELSE) || ($kn === \__McTokId::T_TRY && $kc === \__McTokId::T_FINALLY)) {
                        return $end;
                    }
                }
            }
            return $end;
        }
        for ($q = $pe + 1; $q < $n; $q++) {
            $k = $this->eff($q);
            if ($k === 123) {
                $q = $s->pair[$q];
                if ($q < 0) {
                    return -1;
                }
                continue;
            }
            if ($k === 59) {
                return $q;
            }
            if ($k === \__McTokId::T_CLOSE_TAG) {
                // getPrevNonWhitespace: the last comment of the gap before `?>`, else the token before it
                $g = $s->gapBefore[$q];
                if ($g !== '' && Gap::hasComment($g)) {
                    return -$q - 2;
                }
                return $q - 1;
            }
        }
        return -1;
    }

    /** AlternativeSyntaxAnalyzer::findAlternativeSyntaxBlockEnd from the start token at $q */
    private function altEnd(int $q): int
    {
        $s = $this->s;
        $start = $s->kind[$q];
        for ($j = $q + 1; $j < $s->n; $j++) {
            if ($s->ct[$j] !== 0) {
                continue;
            }
            $k = $s->kind[$j];
            if (self::isAltEnd($start, $k)) {
                return $j;
            }
            if ($k === $start && $this->isAltStart($j)) {
                $j = $this->altEnd($j);
                if ($j < 0) {
                    return -1;
                }
            }
        }
        return -1;
    }

    private static function isAltEnd(int $start, int $k): bool
    {
        if ($start === \__McTokId::T_IF || $start === \__McTokId::T_ELSEIF) {
            return $k === \__McTokId::T_ENDIF || $k === \__McTokId::T_ELSE || $k === \__McTokId::T_ELSEIF;
        }
        if ($start === \__McTokId::T_ELSE) {
            return $k === \__McTokId::T_ENDIF;
        }
        if ($start === \__McTokId::T_FOR) {
            return $k === \__McTokId::T_ENDFOR;
        }
        if ($start === \__McTokId::T_FOREACH) {
            return $k === \__McTokId::T_ENDFOREACH;
        }
        if ($start === \__McTokId::T_WHILE) {
            return $k === \__McTokId::T_ENDWHILE;
        }
        return $k === \__McTokId::T_ENDSWITCH;
    }

    private function isAltStart(int $q): bool
    {
        $s = $this->s;
        $nx = $q + 1;
        if ($nx < $s->n && $this->eff($nx) === 40) {
            $e = $s->pair[$nx];
            if ($e < 0) {
                return false;
            }
            $nx = $e + 1;
        }
        return $nx < $s->n && $this->eff($nx) === 58;
    }
}
