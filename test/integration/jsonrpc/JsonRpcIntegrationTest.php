<?php namespace integration\jsonrpc;

use \PHPUnit_Framework_TestCase;
use unit\jsonrpc\TestObject;
use jsonrpc\Client;

class JsonRpcIntegrationTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @var TestObject
     */
    private $testPort;

    public function setUp() {
        $this->testPort = new Client('http://127.0.0.1:8000', 5, true);
    }
    
    public function test() {
        $result = $this->testPort->echoMethod('test');
        $this->assertEquals('test', $result);
        
        $result = $this->testPort->subtract(3, 3);
        $this->assertEquals(0, $result);
    }
    
}