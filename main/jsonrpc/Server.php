<?php namespace jsonrpc;

use Exception;
use ReflectionMethod;

/**
 * This class build a JSON-RPC 2.0 Server
 * http://www.jsonrpc.org/specification
 * 
 * @author xx <freecoder.xx@gmail.com>
 */
class Server {
    
    const JSON_RPC_VERSION = '2.0';
    
    /** Error codes for pre-defined errors */
    const PARSE_ERROR_CODE = -32700;
    const INVALID_REQUEST_CODE = -32600;
    const METHOD_NOT_FOUND_CODE = -32601;
    const INVALID_PARAMS_CODE = -32602;
    const INTERNAL_ERROR_CODE = -32603;
    const SERVER_ERROR_CODE = -32000;
    
    /** Error codes for application-specific errors */
    const INVALID_REQUEST_TYPE_CODE = -33001;
    
    private static $errorMessages = [
        self::PARSE_ERROR_CODE =>  'Parse error',
        self::INVALID_REQUEST_CODE => 'Invalid Request',
        self::METHOD_NOT_FOUND_CODE => 'Method not found',
        self::INVALID_PARAMS_CODE => 'Invalid params',
        self::INTERNAL_ERROR_CODE => 'Internal error',
        self::SERVER_ERROR_CODE => 'Server error',
        
        self::INVALID_REQUEST_TYPE_CODE => 'Invalid request type',
    ];

    /**
     * Handling a request and binding it to a handler
     *
     * @param object $handler
     * 
     * @return boolean
     */
    public static function handle($handler) {
        
        // Check request type
        if (!self::isValidRequestType()) {
            self::sendResponse(self::getResponseObject(null,
                    self::getErrorObject(self::INVALID_REQUEST_TYPE_CODE, null,
                            'Invalid HTTP-method or Content-Type of the request')));
            return false;
        }
        
        // Get request data
        $request = json_decode(file_get_contents('php://input'), true);
        
        // Check JSON format
        if (!is_array($request)) {
            self::sendResponse(self::getResponseObject(null,
                    self::getErrorObject(self::PARSE_ERROR_CODE, null, json_last_error_msg())));
            return true;
        }

        // Handle batch request
        if (self::isJsonArray($request)) {
            $responseObject = self::executeBatch($handler, $request);
        } else {
            $responseObject = self::execute($handler, $request);
        }
        
        self::sendResponse($responseObject);
        return true;
    }
    
    /**
     * Execute method
     * 
     * @param object $handler
     * @param array $requestObject
     * 
     * @return array - response object or null for a notification
     */
    public static function execute($handler, $requestObject) {
        // Check JSON-RPC format
        if (!self::checkJsonRpcFormat($requestObject)) {
            return self::getResponseObject(null, self::getErrorObject(self::INVALID_REQUEST_CODE));
        }
        
        $id = isset($requestObject['id']) ? $requestObject['id'] : null;
        
        try {
            $requestMethod = $requestObject['method'];
            if (method_exists($handler, $requestMethod)) {
                $reflection = new ReflectionMethod($handler, $requestMethod);
                if ($reflection->isPublic()) {
                    
                    $params = [];
                    if (isset($requestObject['params'])) {
                        $methodParams = $reflection->getParameters();

                        if (!self::mapParameters($requestObject['params'], $methodParams, $params)) {
                            return self::getResponseObject(null,
                                    self::getErrorObject(self::INVALID_PARAMS_CODE), $id);
                        }
                    }

                    //$result = $reflection->invokeArgs($params);
                    $result = call_user_func_array(array($handler, $requestMethod), $params);
                    return self::getResponseObject($result, null, $id);
                }
            }
            return self::getResponseObject(null, self::getErrorObject(self::METHOD_NOT_FOUND_CODE), $id);
        } catch (Exception $e) {
            return self::getResponseObject(null,
                    self::getErrorObject(self::SERVER_ERROR_CODE, null, $e->getMessage()), $id);
        }
    }
    
    public static function executeBatch($handler, $requestObject) {
        $responses = array();
        
        foreach ($requestObject as $requestItem) {
            if (!is_array($requestItem)) {
                $responses[] = self::getResponseObject(null,
                        self::getErrorObject(self::INVALID_REQUEST_CODE));
            } else {
                $responseObject = self::execute($handler, $requestItem);
                empty($responseObject) ? null : $responses[] = $responseObject;
            }
        }
        
        return $responses;
    }
    
    private static function mapParameters($requestParams, array $methodParams, array &$params) {
        // Positional parameters
        if (self::isJsonArray($requestParams)) {
            if (count($requestParams) !== count($methodParams)) {
                return false;
            }
            
            $params = $requestParams;
            return true;
        }

        // Named parameters
        foreach ($methodParams as $parameter) {
            $name = $parameter->getName();

            if (isset($requestParams[$name])) {
                $params[$name] = $requestParams[$name];
            } else if ($parameter->isDefaultValueAvailable()) {
                continue;
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Validate JSON-RCP request type
     * 
     * @return boolean - true if a request type is valid, false otherwise
     */
    private static function isValidRequestType() {
        return (filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'POST' &&
                filter_input(INPUT_SERVER, 'HTTP_CONTENT_TYPE') === 'application/json');
    }
    
    /**
     * Сheck JSON-RCP format of request object
     * 
     * @return boolean - true if a request object have JSON-RPC format, false otherwise
     */
    private static function checkJsonRpcFormat($requestObject) {
        return (isset($requestObject['jsonrpc']) &&
                isset($requestObject['method']) &&
                is_string($requestObject['method']) &&
                $requestObject['jsonrpc'] === self::JSON_RPC_VERSION &&
                (!isset($requestObject['params']) || is_array($requestObject['params'])));
    }
    
    private static function isJsonArray($var) {
        return (array_keys($var) === range(0, count($var) - 1));
    }

    /**
     * Sending the response object or not for a notification
     * 
     * @param array $responseObject - response object
     * @param bool $batchMode - true if a response object ....
     */
    private static function sendResponse($responseObject, $batchMode = false) {
        if (!empty($responseObject)) {
            if ($batchMode) {
                $response = '[' . implode(',',
                                    array_map(function ($item) {
                                                return json_encode($item);
                                            }, $responseObject)
                                    ) . ']';
            } else {
                $response = json_encode($responseObject);
            }
            // Sending the response
            header('Content-Type: application/json');
            echo $response;
        } else {
            // Notifications don't want response!
        }
    }
    
    /**
     * Gives the response
     * 
     * @param mixin $result - result value of the method invoked on the Server or null, if there
     * was an error invoking the method
     * @param array $error - error object or null, if there was no error triggered during invocation
     * @param int $id - a context identifier, established by Client, or null, if there was an
     * error in detecting the id in the Request object (e.g. Parse error/Invalid Request)
     * 
     * @return array - response object or null for a notification
     */
    private static function getResponseObject($result = null, $error = null, $id = null) {
        if (!empty($id) || !empty($error)) {
            $response = [
                'jsonrpc' => self::JSON_RPC_VERSION
            ];
            empty($error) ?
                $response['result'] = $result :
                $response['error'] = $error;
            $response['id'] = $id;
            return $response;
        } else {
            // Notifications don't want response!
            return null;
        }
    }
    
    /**
     * Forming error object by error code.
     * 
     * @param int $code - error code
     * @param string $message - error message (for the pre-defined errors must not be specified)
     * @param string $data - error data (must not be specified)
     * 
     * @return array - error data
     */
    private static function getErrorObject($code, $message = null, $data = null) {
        if (empty($message)) {
            $key = array_key_exists($code, self::$errorMessages) ? $code : self::SERVER_ERROR_CODE;
            $message = self::$errorMessages[$key];
        }
        
        $error = [
            'code' => (int) $code,
            'message' => $message
        ];
        empty($data) ? null : $error['data'] = $data;
        
        return $error;
    }
    
}
