<?php

/**
 * Application starter
 */
error_reporting(E_ALL);

use unit\jsonrpc\TestObject;
use jsonrpc\Server;

class Application {

    public function __construct() {
    }

    public function run() {
        $this->runHandle();
    }
  
    private function runHandle() {
        $testHandler = new TestObject();
        Server::handle($testHandler) or print 'no request';
    }
}

// Return The Application
$app = new Application;
return $app;