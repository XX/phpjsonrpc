<?php namespace unit\jsonrpc;

class TestObject {
    
    public function echoMethod($param) {
        return $param;
    }
    
    public function subtract($minuend, $subtrahend) {
        return $minuend - $subtrahend;
    }
    
    public function hello() {
        return 'hello';
    }
    
}