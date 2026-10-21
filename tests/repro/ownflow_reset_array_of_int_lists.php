<?php
// compile fails: `MIR.verify: ownflow: $uses read at line 19 in run past a representation mismatch (arr:arr:int vs mixdead): the name is live there`
// An `array<int, int[]>` local built with `$uses[$i] = [$i, $i + 1]`, passed to a function and then reset with `$uses = [];` in the same loop.
// Expected: compiles and prints `2`, `1` (php prints the same). Found porting ordered_traits (an `array<int, int[]>` of statement ranges); the rule keeps two int lists instead.
/** @param array<int, int[]> $a */
function take(array $a): void
{
    echo count($a), "\n";
}

/** @param int[] $k */
function run(array $k): void
{
    /** @var array<int, int[]> $uses */
    $uses = [];
    foreach ($k as $i) {
        if ($i === 0) {
            take($uses);
            $uses = [];
            continue;
        }
        $uses[$i] = [$i, $i + 1];
    }
}
run([1, 2, 0, 3, 0]);
