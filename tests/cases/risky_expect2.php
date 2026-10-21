<?php
class FooTest extends TestCase { function t() { $this->setExpectedException("A", "b", 1, 4); $this->setExpectedException("A", "b"); } }
