<?php
// Mock Brain\Monkey for tests that expect it
namespace Brain\Monkey {
    function setUp() {
        // Mock implementation - does nothing
    }
    
    function tearDown() {
        // Mock implementation - does nothing
    }
}

namespace Brain\Monkey\Functions {
    function expect($function) {
        return new class {
            public function andReturn($value) { return $this; }
            public function once() { return $this; }
            public function twice() { return $this; }
            public function times($n) { return $this; }
            public function never() { return $this; }
            public function with(...$args) { return $this; }
            public function withArgs($args) { return $this; }
            public function andReturnValues($values) { return $this; }
            public function andReturnUsing($callback) { return $this; }
            public function justReturn($value) { return $this; }
            public function returnArg($index = 0) { return $this; }
            public function atLeast($n) { return $this; }
            public function atMost($n) { return $this; }
            public function zeroOrMoreTimes() { return $this; }
            public function ordered() { return $this; }
            public function alias($alias) { return $this; }
            public function shouldReceive($method) { return $this; }
            public function shouldNotReceive($method) { return $this; }
        };
    }
    
    function when($function) {
        return expect($function);
    }
}