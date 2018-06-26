<?php

class A{}

try {
	(new A())->hi();
} catch (Throwable $ex) {
	var_dump($ex);
}
