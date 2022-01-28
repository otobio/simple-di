<?php

class NoConstructor {
}

class A {
	public $b;

	public function __construct(B $b) {
		$this->b = $b;
	}
}

class B {
	public $c;

	public function __construct(C $c) {
		$this->c = $c;
	}
}

class C {
	public $d;
	public $e;

	public function __construct(D $d, E $e) {
		$this->d = $d;
		$this->e = $e;
	}
}


class D {

}

class E {
	public $f;
	public function __construct(F $f) {
		$this->f = $f;
	}
}

class F {}

interface InterfaceTest {}

class InterfaceTestClass implements interfaceTest {

}

class OptionalInterface {
	public $obj;

	public function __construct(InterfaceTest $obj = null) {
		$this->obj = $obj;
	}
}

class RequiresConstructorArgsB {
	public $a;
	public $foo;
	public $bar;

	public function __construct(A $a, $foo, $bar) {
		$this->a = $a;
		$this->foo = $foo;
		$this->bar = $bar;
	}
}
