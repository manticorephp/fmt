<?php
class Hooks
{
    public string $a { get => 'x'; }
    public string $b {
        get { return 'b'; }
        set { $this->b = $value; }
    }
    public string $c = 'x' { get => $this->c; }
    public array $d = ['a'] {
        get { return $this->d; } }
    public string $e {
        #[Attr]
        get { return 'e'; }
        set(string $v) { $this->e = $v; }
    }
}
