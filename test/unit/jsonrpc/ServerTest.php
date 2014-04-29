<?php namespace unit\jsonrpc;

use \PHPUnit_Framework_TestCase;
use jsonrpc\Server;

class ServerTest extends PHPUnit_Framework_TestCase {
    
    private $messages = [
        // call with positional parameters
        [
            'request' => '{"jsonrpc": "2.0", "method": "subtract", "params": [42, 23], "id": 1}',
            'response' => '{"jsonrpc": "2.0", "result": 19, "id": 1}'
        ],
        [
            'request' => '{"jsonrpc": "2.0", "method": "subtract", "params": [23, 42], "id": 2}',
            'response' => '{"jsonrpc": "2.0", "result": -19, "id": 2}'
        ],
        // call with named parameters
        [
            'request' => '{"jsonrpc": "2.0", "method": "subtract", "params": {"subtrahend": 23, "minuend": 42}, "id": 3}',
            'response' => '{"jsonrpc": "2.0", "result": 19, "id": 3}'
        ],
        [
            'request' => '{"jsonrpc": "2.0", "method": "subtract", "params": {"minuend": 42, "subtrahend": 23}, "id": 4}',
            'response' => '{"jsonrpc": "2.0", "result": 19, "id": 4}'
        ],
        // call with return zero 
        [
            'request' => '{"jsonrpc": "2.0", "method": "echoMethod", "params": [0], "id": 1}',
            'response' => '{"jsonrpc": "2.0", "result": 0, "id": 1}'
        ],
        [
            'request' => '{"jsonrpc": "2.0", "method": "echoMethod", "params": [false], "id": 1}',
            'response' => '{"jsonrpc": "2.0", "result": false, "id": 1}'
        ],
        // omitted params
        [
            'request' => '{"jsonrpc": "2.0", "method": "hello", "id": 1}',
            'response' => '{"jsonrpc": "2.0", "result": "hello", "id": 1}'
        ],
        /*
        [
            'request' => '',
            'response' => ''
        ],
        [
            'request' => '',
            'response' => ''
        ]
        */
    ];


    public function testExecute() {
        $handler = new TestObject();
        foreach ($this->messages as $message) {
            $testRequest = $message['request'];
            $testResponse = $message['response'];
            
            $request = json_decode($testRequest, true);
            
            $response = Server::execute($handler, $request);
            
            $testResponse = json_encode(json_decode($testResponse));
            $this->assertEquals(json_encode($response), $testResponse);
        }
    }
    
    public function testExecuteBatch() {
        $handler = new TestObject();
        
        $batchRequest = '[';
        $batchResponse = '[';
        foreach ($this->messages as $message) {
            $batchRequest .= $message['request'] . ',';
            $batchResponse .= $message['response'] . ',';
        }
        $batchRequest[strlen($batchRequest) - 1] = ']';
        $batchResponse[strlen($batchResponse) - 1] = ']';
        
        $request = json_decode($batchRequest, true);

        $response = Server::executeBatch($handler, $request);

        $testResponse = json_encode(json_decode($batchResponse));
        $this->assertEquals(json_encode($response), $testResponse);
    }
    
}