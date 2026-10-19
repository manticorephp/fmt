<?php
namespace A {
    use B\C;
    use B\D;
    new C();
}
namespace E {
    use B\C;
    use B\D;
    new D();
}
