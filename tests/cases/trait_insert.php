<?php
class A
{
    use B, C;
    use D,E , F;
    use G,
        H;
    use I, J { J::x insteadof I; }
    /* c */use K,
    L;
    use M, /* c */ N, // d
        O;
}
trait T { use P, Q; }
