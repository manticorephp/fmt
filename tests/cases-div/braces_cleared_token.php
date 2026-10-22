<?php

// Known divergence (outside the parity matrix): `braces` runs its eight fixers on ONE token list without clearEmptyTokens between them. braces_position (same_line) moves the `{` across
// the `//` comment and leaves an emptied token between the whitespace and the docblock; statement_indentation (bracesFixerCompatibility) then sees no comment right behind the
// whitespace and does not re-indent the docblock's inner lines (upstream keeps `     * x`, native re-indents to 7 spaces).
// rules: {"braces":{"position_after_functions_and_oop_constructs":"same"}}
interface RuntimeHost // c
  { /**
     * x
     */
    public function a(): int ;
}
