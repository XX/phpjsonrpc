<?php namespace jsonrpc;

use Exception;

/**
 * Class for a generic JSON-RPC 2.0 clients
 * http://www.jsonrpc.org/specification
 *
 * @author xx <freecoder.xx@gmail.com>
 */
class Client {
    
    const JSON_RPC_VERSION = '2.0';

    /**
     * The server URL
     *
     * @var string
     */
    private $url;

    /**
     * The request id or null for notification
     * or false to use a generated random id.
     *
     * @var mixin
     */
    public $id = false;

    private $timeout;
    
    private $debug;
    
    private $username;
    
    private $password;

    private $headers = [
        'Connection: close',
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    /**
     * Takes the connection parameters
     *
     * @param string $url
     * @param boolean $debug
     */
    public function __construct($url, $timeout = 5, $debug = false, $headers = []) {
        $this->url = $url;
        $this->timeout = $timeout;
        $this->debug = $debug;
        $this->headers = array_merge($this->headers, $headers);
    }

    /**
     * Take call
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    public function __call($method, $params) {
        return $this->execute($method, $params);
    }
    
    /**
     * Set username and password
     * 
     * @param string $username
     * @param string $password
     */
    public function authentication($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Execute the method
     *
     * @param string $method
     * @param array $params
     * 
     * @return array
     */
    public function execute($method, $params = []) {
        $id = $this->id === false ? mt_rand() : $this->id;

        if (!is_scalar($method)) {
            throw new Exception('Method name has no scalar value');
        }

        // Prepares the request
        $request = [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'method' => $method,
            'id' => $id
        ];
        if (!empty($params)) {
            $request['params'] = $params;
        }

        $response = $this->doRequest($request);
        
        if (empty($response)) {
            throw new Exception('JSON-RPC Error: response is empty');
        } else if (isset($response['id']) && $response['id'] == $id && array_key_exists('result', $response)) {
            return $response['result'];
        } else if (isset($response['error'])) {
            $error = $response['error'];
            if ($this->debug) {
                print_r($error);
            }
            $data = isset($error['data']) ? $error['data'] : '';
            throw new Exception('JSON-RPC Error: [' . $error['message'] . '] ' . $data);
        } else {
            throw new Exception('JSON-RPC Error: response have unknown format, response = ' . print_r($response));
        }

        return null;
    }
    
    /**
     * Performs a jsonRCP request and gets the results as an array
     * 
     * @param array $request
     * 
     * @return array
     */
    private function doRequest($request) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JSON-RPC PHP Client');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

        // Disable verify SSL cert
        // http://unitstep.net/blog/2009/05/05/using-curl-in-php-to-access-https-ssltls-protected-sites/
        // http://blog.yousoft.ru/2010/03/26/ispolzuem-curl-php-chast-3-protokol-https-ssl-soedinenie/
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        if ($this->username && $this->password) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username.':'.$this->password);
        }
        
        $result = curl_exec($ch);
        $response = json_decode($result, true);

        curl_close($ch);

        return is_array($response) ? $response : array();
    }
}
