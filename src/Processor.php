<?php

declare(strict_types=1);

namespace Fmt;

/** Formats one file (or one string) with a RulePlan: what Cli did inline, shared by the sequential loop and the pool's workers. */
final class Processor
{
    /**
     * @param GapRule[] $rules priority order
     * @param bool $dry do not write
     * @param bool $diff fill Outcome::$diff
     * @param bool $applied fill Outcome::$applied (runs the rules one at a time)
     */
    public function __construct(private array $rules, private bool $dry, private bool $diff, private bool $applied, private ?Cache $cache = null)
    {
    }

    public function file(string $path): Outcome
    {
        $t = \microtime(true);
        $o = $this->read($path);
        $o->secs = \microtime(true) - $t;
        return $o;
    }

    private function read(string $path): Outcome
    {
        $code = @\file_get_contents($path);
        if ($code === false) {
            $o = new Outcome($path, Outcome::FAILED);
            $o->msg = 'cannot read the file';
            return $o;
        }
        if ($this->cache !== null) {
            $h = \md5($code);
            if ($this->cache->isClean($path, $h)) {
                $c = new Outcome($path, Outcome::CACHED);
                $c->hash = $h;
                $c->bytes = \strlen($code);
                return $c;
            }
        }
        $o = $this->code($path, $code);
        $o->bytes = \strlen($code);
        if ($o->status === Outcome::CHANGED && !$this->dry) {
            $new = $this->fixed;
            if (@\file_put_contents($path, $new) === false) {
                $o->status = Outcome::FAILED;
                $o->msg = 'cannot write the file';
            } else {
                $o->hash = \md5($new);
            }
        } elseif ($o->status === Outcome::CHANGED) {
            $o->hash = '';
        }
        return $o;
    }

    /** the result of the last code() that changed something */
    public string $fixed = '';

    public function code(string $label, string $code): Outcome
    {
        $orig = $code;
        $out = new Outcome($label, Outcome::OK);
        $out->hash = \md5($orig);
        $pre = [];
        foreach ($this->rules as $r) {
            $c2 = $r->preParse($code);
            if ($c2 !== $code && $this->applied) {
                $pre[] = $r->sortName();
            }
            $code = $c2;
        }
        foreach ($this->rules as $r) {
            $r->forFile($label);
        }
        $s = Source::parse($code);
        if (\in_array(\__McTokId::T_BAD_CHARACTER, $s->kind, true)) {
            $out->status = Outcome::INVALID;
            $out->msg = 'does not lint (bad character)';
            $out->hash = '';
            return $out;
        }
        $applied = $pre;
        if ($this->applied) {
            $prev = $s->join();
            foreach ($this->rules as $r) {
                $s = Engine::run($s, [$r]);
                if ($s->unsupported !== '' || $s->abort) {
                    break;
                }
                $cur = $s->join();
                if ($cur !== $prev) {
                    $applied[] = $r->sortName();
                    $prev = $cur;
                }
            }
        } else {
            $s = Engine::run($s, $this->rules);
        }
        if ($s->unsupported !== '') {
            $out->status = Outcome::FAILED;
            $out->msg = 'not fixed, ' . $s->unsupported;
            $out->hash = '';
            return $out;
        }
        if ($s->lintUse && !$s->abort && self::brokenUse($s->join())) {
            $s->abort = true;
        }
        if ($s->abort) {
            $out->status = Outcome::FAILED;
            $out->msg = 'not fixed, the result would not lint (as upstream)';
            $out->hash = '';
            return $out;
        }
        $new = $s->join();
        if ($new === $orig) {
            return $out;
        }
        $out->status = Outcome::CHANGED;
        $out->applied = $applied;
        if ($this->diff) {
            $out->diff = Diff::unified($orig, $new, $label);
        }
        $this->fixed = $new;
        return $out;
    }

    /** A `use` statement with a name that ends in a backslash and is not followed by a group brace: PHP does not lint it. */
    private static function brokenUse(string $code): bool
    {
        $p = Source::parse($code);
        for ($i = 0; $i < $p->n; $i++) {
            if ($p->kind[$i] !== \__McTokId::T_USE || $p->ct[$i] !== 0) {
                continue;
            }
            for ($j = $i + 1; $j < $p->n && $p->kind[$j] !== 59; $j++) {
                $x = $p->text($j);
                if ($x !== '' && $x[\strlen($x) - 1] === '\\' && $j + 1 < $p->n && $p->gapBefore[$j + 1] !== '' && $p->kind[$j + 1] !== 123 && $p->ct[$j + 1] !== CT::GROUP_IMPORT_BRACE_OPEN) {
                    return true;
                }
            }
        }
        return false;
    }
}
