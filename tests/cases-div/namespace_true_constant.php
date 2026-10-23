<?php

// Known divergence (outside the parity matrix): `NAMESPACE\TRUE` with native_constant_invocation + constant_case: upstream re-lexes `namespaceTRUE` as separate tokens, native does not lower-case it.
// rules: {"native_constant_invocation":true,"constant_case":true}
namespace Foo;

$a = NAMESPACE\TRUE;
$b = TRUE;
