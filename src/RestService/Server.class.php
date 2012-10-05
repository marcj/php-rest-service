<?php

namespace RestService;

/**
 * RestService\Server - A REST server class for RESTful APIs.
 */

class Server {

    /**
     * Current routes.
     *
     * structure:
     *  array(
     *    '<uri>' => array('<methodName>', array(<requiredParams>), array(<optionalParams>));
     *  )
     *
     * <uri> with no starting or trailing slash!
     *
     * array(
     *   'book/(.*)/(.*)' => array('book')
     *   //calls book($method, $1, $2)
     *   
     *   'house/(.*)' => array('book', array('sort'))
     *   //calls book($method, $1, getArgv('sort'))
     *   
     *   'label/flatten' => array('getLabel', array('uri'))
     *   //Calls getLabel($method, getArgv('uri'))
     *
     *
     *   'get:foo/bar' => array('getLabel', array('uri'), array('optionalSort'))
     *   //Calls getLabel(getArgv('uri'), getArgv('optionalSort'))
     *   
     *   'post:foo/bar' => array('saveLabel', array('uri'))
     *   //Calls saveLabel(getArgv('uri'), getArgv('optionalSort'))
     * )
     *
     * @var array
     */
    private $routes = array();


    /**
     * Blacklisted http get arguments.
     *
     * @var array
     */
    private $blacklistedGetParameters = array('_method', '_suppress_status_code');


    /**
     * Current URL that triggers the controller.
     *
     * @var string
     */
    private $triggerUrl = '';


    /**
     * Contains the controller object.
     *
     * @var string
     */
    private $controller = '';


    /**
     * List of sub controllers.
     *
     * @var array
     */
    private $controllers = array();


    /**
     * Parent controller.
     *
     * @var \RestService\Server
     */
    private $parentController;


    /**
     * The client
     *
     * @var \RestService\Server
     */
    private $client;


    /**
     * From the rewrite rule: RewriteRule ^(.+)$ index.php?__url=$1&%{query_string}
     * @var string
     */
    private $rewrittenRuleKey = '__url';


    /**
     * List of excluded methods.
     *
     * @var array|string array('methodOne', 'methodTwo') or * for all methods
     */
    private $collectRoutesExclude = array('__construct');


    /**
     * List of possible methods.
     * @var array
     */
    public $methods = array('get', 'post', 'put', 'delete', 'head', 'options');


    /**
     * Check access function/method. Will be fired after the route has been found.
     * Arguments: (url, route)
     * 
     * @var callable
     */
    private $checkAccessFn;

    /**
     * Send exception function/method. Will be fired if a route-method throws a exception.
     * Please die/exit in your function then.
     * Arguments: (exception)
     * 
     * @var callable
     */
    private $sendExceptionFn;

    /**
     * If this is true, we send file, line and backtrace if an exception has been thrown.
     * 
     * @var boolean
     */
    private $debugMode = false;


    /**
     * Sets whether the service should serve route descriptions
     * through the OPTIONS method.
     * 
     * @var boolean
     */
    private $describeRoutes = true;


    /**
     * If this controller can not find a route,
     * we fire this method and send the result.
     * 
     * @var string
     */
    private $fallbackMethod = '';

    /**
     * If the lib should send HTTP status codes.
     * Some Client libs does not support this, you can deactivate it via
     * ->setHttpStatusCodes(false);
     * 
     * @var boolean
     */
    private $withStatusCode = true;

    /**
     * Constructor
     *
     * @param string        $pTriggerUrl
     * @param string|object $pControllerClass
     * @param string        $pRewrittenRuleKey From the rewrite rule: RewriteRule ^(.+)$ index.php?__url=$1&%{query_string}
     * @param \RestService\Server $pParentController
     */
    public function __construct($pTriggerUrl, $pControllerClass = null, $pRewrittenRuleKey = '__url',
                                $pParentController = null){

        $this->normalizeUrl($pTriggerUrl);
        $this->setRewrittenRuleKey($pRewrittenRuleKey);

        if ($pParentController){
            $this->parentController = $pParentController;
            $this->setClient($pParentController->getClient());

            if ($pParentController->getCheckAccess())
                $this->setCheckAccess($pParentController->getCheckAccess());

            if ($pParentController->getExceptionHandler())
                $this->setExceptionHandler($pParentController->getExceptionHandler());

            if ($pParentController->getDebugMode())
                $this->setDebugMode($pParentController->getDebugMode());

            if ($pParentController->getDescribeRoutes())
                $this->setDescribeRoutes($pParentController->getDescribeRoutes());

            $this->setHttpStatusCodes($pParentController->getHttpStatusCodes());

        } else {
            $this->setClient(new Client($this));
        }

        $this->setClass($pControllerClass);
        $this->setTriggerUrl($pTriggerUrl);
    }


    /**
     * Factory.
     *
     * @param string $pTriggerUrl
     * @param string $pControllerClass
     * @param string $pRewrittenRuleKey From the rewrite rule: RewriteRule ^(.+)$ index.php?__url=$1&%{query_string}
     *
     * @return Server $this
     */
    public static function create($pTriggerUrl, $pControllerClass = '', $pRewrittenRuleKey = '__url'){
        $clazz = get_called_class();
        return new $clazz($pTriggerUrl, $pControllerClass, $pRewrittenRuleKey);
    }

    /** 
     * If the lib should send HTTP status codes.
     * Some Client libs does not support it.
     * 
     * @param boolean $pWithStatusCode
     * @return Server $this
     */
    public function setHttpStatusCodes($pWithStatusCode){
        $this->withStatusCode = $pWithStatusCode;
        return $this;
    }

    /**
     * 
     * @return boolean
     */
    public function getHttpStatusCodes(){
        return $this->withStatusCode;
    }

    /**
     * Returns the rewritten rule key.
     *
     * @return string
     */
    public function getRewrittenRuleKey(){
        return $this->rewrittenRuleKey;
    }


    /**
     * Sets the rewritten rule key.
     * @param string $pRewrittenRuleKey
     *
     * @return Server $this
     */
    public function setRewrittenRuleKey($pRewrittenRuleKey){
        $this->rewrittenRuleKey = $pRewrittenRuleKey;
        return $this;
    }

    /**
     * Set the check access function/method.
     * Will fired with arguments: (url, route)
     * 
     * @param callable $pFn 
     * @return Server $this
     */
    public function setCheckAccess($pFn){
        $this->checkAccessFn = $pFn;
        return $this;
    }

    /**
     * Getter for checkAccess
     * @return callable
     */
    public function getCheckAccess(){
        return $this->checkAccessFn;
    }

    /**
     * If this controller can not find a route,
     * we fire this method and send the result.
     * 
     * @param string $pFn Methodname of current attached class
     * @return Server $this
     */
    public function setFallbackMethod($pFn){
        $this->fallbackMethod = $pFn;
        return $this;
    }

    /**
     * Getter for fallbackMethod
     * @return string
     */
    public function fallbackMethod(){
        return $this->fallbackMethod;
    }


    /**
     * Sets whether the service should serve route descriptions
     * through the OPTIONS method.
     * 
     * @param boolean $pDescribeRoutes 
     * @return Server $this
     */
    public function setDescribeRoutes($pDescribeRoutes){
        $this->describeRoutes = $pDescribeRoutes;
    }

    /**
     * Getter for describeRoutes.
     * 
     * @return boolean
     */
    public function getDescribeRoutes(){
        return $this->describeRoutes;
    }

    /**
     * Send exception function/method. Will be fired if a route-method throws a exception.
     * Please die/exit in your function then.
     * Arguments: (exception)
     * 
     * @param callable $pFn 
     * @return Server $this
     */
    public function setExceptionHandler($pFn){
        $this->sendExceptionFn = $pFn;
        return $this;
    }

    /**
     * Getter for checkAccess
     * @return callable
     */
    public function getExceptionHandler(){
        return $this->sendExceptionFn;
    }

    /**
     * If this is true, we send file, line and backtrace if an exception has been thrown.
     * 
     * @param boolean $pDebugMode 
     * @return Server $this
     */
    public function setDebugMode($pDebugMode){
        $this->debugMode = $pDebugMode;
        return $this;
    }

    /**
     * Getter for checkAccess
     * @return boolean
     */
    public function getDebugMode(){
        return $this->debugMode;
    }


    /**
     * Alias for getParent()
     *
     * @return Server
     */
    public function done(){
        return $this->getParent();
    }


    /**
     * Returns the parent controller
     *
     * @return Server $this
     */
    public function getParent(){
        return $this->parentController;
    }


    /**
     * Set the URL that triggers the controller.
     *
     * @param $pTriggerUrl
     * @return Server
     */
    public function setTriggerUrl($pTriggerUrl){
        $this->triggerUrl = $pTriggerUrl;
        return $this;
    }


    /**
     * Gets the current trigger url.
     *
     * @return string
     */
    public function getTriggerUrl(){
        return $this->triggerUrl;
    }


    /**
     * Sets the client.
     *
     * @param Client $pClient
     * @return Server $this
     */
    public function setClient($pClient){
        $this->client = $pClient;
        $this->client->setupFormats();

        return $this;
    }


    /**
     * Get the current client.
     *
     * @return Client
     */
    public function getClient(){
        return $this->client?$this->client:$this;
    }


    /**
     * Throws the given arguments/error codes as exception,
     * if no real client has been set.
     *
     * @param $pCode
     * @param $pMessage
     * @throws \Exception
     *
     */
    public function sendResponse($pCode, $pMessage){
        throw new Exception($pCode.': '.print_r($pMessage, true));
    }


    /**
     * Sends a 'Bad Request' response to the client.
     *
     * @param $pCode
     * @param $pMessage
     * @throws \Exception
     */
    public function sendBadRequest($pCode, $pMessage){
        if (is_object($pMessage) && $pMessage->xdebug_message) $pMessage = $pMessage->xdebug_message;
        $msg = array('error' => $pCode, 'message' => $pMessage);
        if (!$this->getClient()) throw new \Exception('client_not_found_in_ServerController');
        $this->getClient()->sendResponse('400', $msg);
    }


    /**
     * Sends a 'Internal Server Error' response to the client.
     * @param $pCode
     * @param $pMessage
     * @throws \Exception
     */
    public function sendError($pCode, $pMessage){
        if (is_object($pMessage) && $pMessage->xdebug_message) $pMessage = $pMessage->xdebug_message;
        $msg = array('error' => $pCode, 'message' => $pMessage);
        if (!$this->getClient()) throw new \Exception('client_not_found_in_ServerController');
        $this->getClient()->sendResponse('500', $msg);
    }

    /**
     * Sends a exception response to the client.
     * @param $pCode
     * @param $pMessage
     * @throws \Exception
     */
    public function sendException($pException){

        if ($this->sendExceptionFn){
            call_user_func_array($this->sendExceptionFn, array($pException));
        }
        
        $message = $pException->getMessage();
        if (is_object($message) && $message->xdebug_message) $message = $message->xdebug_message;

        $msg = array('error' => get_class($pException), 'message' => $message);

        if ($this->debugMode){
            $msg['file'] = $pException->getFile();
            $msg['line'] = $pException->getLine();
            $msg['trace'] = $pException->getTraceAsString();
        }

        if (!$this->getClient()) throw new \Exception('client_not_found_in_ServerController');
        $this->getClient()->sendResponse('500', $msg);
    
    }

    /**
     * Adds a new route for all http methods (get, post, put, delete, options, head).
     * 
     * @param string $pUri
     * @param string $pMethod
     * @param string $pHttpMethod
     * @return Server
     */
    public function addRoute($pUri, $pMethod, $pHttpMethod = '_all_'){
        $this->routes[$pUri][ $pHttpMethod ] = $pMethod;
        return $this;
    }


    /**
     * Same as addRoute, but limits to GET.
     *
     * @param string $pUri
     * @param string $pMethod
     * @return Server
     */
    public function addGetRoute($pUri, $pMethod){
        $this->addRoute($pUri, $pMethod, 'get');
        return $this;
    }


    /**
     * Same as addRoute, but limits to POST.
     *
     * @param string $pUri
     * @param string $pMethod
     * @return Server
     */
    public function addPostRoute($pUri, $pMethod){
        $this->addRoute($pUri, $pMethod, 'post');
        return $this;
    }


    /**
     * Same as addRoute, but limits to PUT.
     *
     * @param string $pUri
     * @param string $pMethod
     * @return Server
     */
    public function addPutRoute($pUri, $pMethod){
        $this->addRoute($pUri, $pMethod, 'put');
        return $this;
    }

    /**
     * Same as addRoute, but limits to HEAD.
     *
     * @param string $pUri
     * @param string $pMethod
     * @return Server
     */
    public function addHeadRoute($pUri, $pMethod){
        $this->addRoute($pUri, $pMethod, 'head');
        return $this;
    }


    /**
     * Same as addRoute, but limits to OPTIONS.
     *
     * @param string $pUri
     * @param string $pMethod
     * @param array  $pArguments Required arguments. Throws an exception if one of these is missing.
     * @param array  $pOptionalArguments
     * @return Server
     */
    public function addOptionsRoute($pUri, $pMethod){
        $this->addRoute($pUri, $pMethod, 'options');
        return $this;
    }


    /**
     * Same as addRoute, but limits to DELETE.
     *
     * @param string $pUri
     * @param string $pMethod
     * @param array  $pArguments Required arguments. Throws an exception if one of these is missing.
     * @param array  $pOptionalArguments
     * @return Server
     */
    public function addDeleteRoute($pUri, $pMethod){
        $this->addRoute($pUri, $pMethod, 'delete');
        return $this;
    }


    /**
     * Removes a route.
     *
     * @param string $pUri
     * @return Server
     */
    public function removeRoute($pUri){
        unset($this->routes[$pUri]);
        return $this;
    }


    /**
     * Sets the controller class.
     *
     * @param string|object $pClass
     */
    public function setClass($pClass){
        if (is_string($pClass)){
            $this->createControllerClass($pClass);
        } else if(is_object($pClass)){
            $this->controller = $pClass;
        } else {
            $this->controller = $this;
        }
    }


    /**
     * Setup the controller class.
     *
     * @param string $pClassName
     * @throws Exception
     */
    private function createControllerClass($pClassName){
        if ($pClassName != ''){
            try {
                $this->controller = new $pClassName();
                if (get_parent_class($this->controller) == 'RestService\Server'){
                    $this->controller->setClient($this->getClient());
                }
            } catch (Exception $e) {
                throw new Exception('Error during initialisation of '.$pClassName.': '.$e);
            }
        } else {
            $this->controller = $this;
        }
    }

    /**
     * Attach a sub controller.
     *
     * @param string $pTriggerUrl
     * @param mixed $pControllerClass A class name (autoloader required) or a instance of a class.
     * @param string $pRewrittenRuleKey From the rewrite rule: for __url it's 'RewriteRule ^(.+)$ index.php?__url=$1&%{query_string}'
     *
     * @return Server new created Server. Use done() to switch the context back to the parent.
     */
    public function addSubController($pTriggerUrl, $pControllerClass = '', $pRewrittenRuleKey = '__url'){

        $this->normalizeUrl($pTriggerUrl);

        $controller = new Server($this->triggerUrl.'/'.$pTriggerUrl, $pControllerClass,
            $pRewrittenRuleKey?$pRewrittenRuleKey:$this->rewrittenRuleKey, $this);

        $this->controllers[] = $controller;

        return $controller;
    }

    /**
     * Normalize $pUrl
     *
     * @param $pUrl Ref
     */
    public function normalizeUrl(&$pUrl){
        if (substr($pUrl, -1) == '/') $pUrl = substr($pUrl, 0, -1);
        if (substr($pUrl, 0, 1) == '/') $pUrl = substr($pUrl, 1);
    }


    /**
     * Sends data to the client with 200 http code.
     *
     * @param $pData
     */
    public function send($pData){
        $this->getClient()->sendResponse(200, array('data' => $pData));
    }

    /**
     * Setup automatic routes.
     *
     * @return Server
     */
    public function collectRoutes(){

        if ($this->collectRoutesExclude == '*') return $this;

        $methods = get_class_methods($this->controller);
        foreach ($methods as $method){
            if (in_array($method, $this->collectRoutesExclude)) continue;

            $uri = strtolower(preg_replace('/([a-z])([A-Z])/', '$1/$2', $method));
            $r = new ReflectionMethod($this->controller, $method);
            if ($r->isPrivate()) continue;

            $params = $r->getParameters();
            $optionalArguments = array();
            $arguments = array();
            foreach ($params as $param){
                $name = lcfirst(substr($param->getName(), 1));
                if ($param->isOptional())
                    $optionalArguments[] = $name;
                else
                    $arguments[] = $name;
            }
            $this->routes[$uri] = array(
                $method,
                count($arguments)==0?null:$arguments.
                    count($optionalArguments)==0?null:$optionalArguments
            );
        }

        return $this;
    }


    /**
     * Fire the magic!
     *
     * Searches the method and sends the data to the client.
     *
     * @return mixed
     */
    public function run(){

        //check sub controller
        foreach ($this->controllers as $controller)
            $controller->run();

        //check if its in our area
        if (strpos($this->getClient()->getUrl().'/', $this->triggerUrl.'/') !== 0) return;

        $uri = substr($this->getClient()->getUrl(), strlen($this->triggerUrl));

        if (!$uri) $uri = '';

        $this->normalizeUrl($uri);

        $route = false;
        $arguments = array();
        $requiredMethod = $this->getClient()->getMethod();

        //does the requested uri exist?
        list($methodName, $regexArguments, $method, $routeUri) = $this->findRoute($uri, $requiredMethod);

        if ((!$methodName || $method != 'options') && $requiredMethod == 'options'){
            $description = $this->describe($uri);
            $this->send($description);
        }

        if (!$methodName){
            if (!$this->getParent()){
                if ($this->fallbackMethod){
                    $m = $this->fallbackMethod;
                    $this->send($this->controller->$m());
                } else {
                    $this->sendBadRequest('RouteNotFoundException', "There is no route for '$uri'.");
                }
            } else {
                return false;
            }
        }

        if ($method == '_all_')
            $arguments[] = $method;

        if (is_array($regexArguments)){
            $arguments = array_merge($arguments, $regexArguments);
        }

        //open class and scan method
        $ref = new \ReflectionClass($this->controller);
        if (!method_exists($this->controller, $methodName)){
            $this->sendBadRequest('MethodNotFoundException', "There is no method '$methodName' in ".get_class($this->controller).".");
        }
        $method = $ref->getMethod($methodName);
        $params = $method->getParameters();

        if ($method == '_all_'){
            //first parameter is $pMethod
            array_shift($params);
        }

        //remove regex arguments
        for ($i=0; $i<count($regexArguments); $i++){
            array_shift($params);
        }

        foreach ($params as $param){
            $name = $this->argumentName($param->getName());

            if ($name == '_'){
                $thisArgs = array();
                foreach ($_GET as $k => $v){
                    if (substr($k, 0, 1) == '_' && $k != $this->getRewrittenRuleKey())
                        $thisArgs[$k] = $v;
                }
                $arguments[] = $thisArgs;
            } else {

                if (!$param->isOptional() && $_GET[$name] === null && $_POST[$name] === null){
                    $this->sendBadRequest('MissingRequiredArgumentException', tf("Argument '%s' is missing.", $name));
                }

                $arguments[] = $_GET[$name]?$_GET[$name]:$_POST[$name];
            }
        }

        if ($this->checkAccessFn){
            $args[] = $this->getClient()->getUrl();
            $args[] = $route;
            $args[] = $arguments;
            try {
                call_user_func_array($this->checkAccessFn, $args);
            } catch(\Exception $e){
                $this->sendException($e);
            }
        }

        //fire method
        $object = $this->controller;
        $this->fireMethod($object, $methodName, $arguments);

    }

    public function fireMethod($pObject, $pMethod, $pArguments){

        if (!method_exists($pObject, $pMethod)){
            $this->sendError('rest_method_not_found', tf('Method %s in class %s not found.', $pMethod, get_class($pObject)));
        }

        try {
            $this->send(call_user_func_array(array($pObject, $pMethod), $pArguments));
        } catch(\Exception $e){
            $this->sendException($e);
        }
    }

    /**
     * Describe a route or the whole controller with all routes.
     * 
     * @param  string $pUri
     * @return array
     */
    public function describe($pUri = null, $pOnlyRoutes = false){

        $definition = array();

        if (!$pOnlyRoutes){
            $definition['parameters'] = array(
                '_method' => array('description' => 'Can be used as HTTP METHOD if the client does not support HTTP methods.', 'type' => 'string',
                            'values' => 'GET, POST, PUT, DELETE, HEAD, OPTIONS'),
                '_suppress_status_code' => array('description' => 'Suppress the HTTP status code.', 'type' => 'boolean', 'values' => '1, 0')
            );
        }

        $definition['controller'] = array(
            'entryPoint' => $this->getTriggerUrl()
        );

        foreach ($this->routes as $routeUri => $routeMethods){

            if (!$pUri || ($pUri && preg_match('|^'.$routeUri.'$|', $pUri, $matches))){

                if ($matches){
                    array_shift($matches);
                }
                $def = array();
                $def['uri'] = $this->getTriggerUrl().'/'.$routeUri;
                
                foreach ($routeMethods as $method => $phpMethod){
    
                    $ref = new \ReflectionClass($this->controller);
                    $refMethod = $ref->getMethod($phpMethod);

                    $def['methods'][strtoupper($method)] = $this->getMethodMetaData($refMethod, $matches);
                    
                }
                $definition['controller']['routes'][$routeUri] = $def;
            }
        }

        if (!$pUri){
            foreach ($this->controllers as $controller){
                $definition['subController'][$controller->getTriggerUrl()] = $controller->describe(false, true);
            }
        } 

        return $definition; 
    }

    /**
     * Fetches all meta data informations as params, return type etc.
     * 
     * @param  \ReflectionMethod $pMethod
     * @param  array             $pRegMatches
     * @return array
     */
    public function getMethodMetaData(\ReflectionMethod $pMethod, $pRegMatches){

        $file = $pMethod->getFileName();
        $startLine = $pMethod->getStartLine();

        $fh = fopen($file, 'r');
        if (!$fh) return false;

        $lineNr = 1;
        $lines = array();
        while (($buffer = fgets($fh)) !== false) {
            if ($lineNr == $startLine) break;
            $lines[$lineNr] = $buffer;
            $lineNr++;
        }
        fclose($fh);

        $phpDoc = '';
        $blockStarted = false;
        while($line = array_pop($lines)){


            if ($blockStarted){
                $phpDoc = $line.$phpDoc;

                //if start comment block: /*
                if (preg_match('/\s*\t*\/\*/', $line)){
                    break;
                }
                continue;
            } else {
                //we are not in a comment block.
                //if class def, array def or close bracked from fn comes above
                //then we dont have phpdoc
                if (preg_match('/^\s*\t*[a-zA-Z_&\s]*(\$|{|})/', $line)){
                    break;
                } 
            }

            $trimmed = trim($line);
            if ($trimmed == '') continue;            

            //if end comment block: */
            if (preg_match('/\*\//', $line)){
                $phpDoc = $line.$phpDoc;
                $blockStarted = true;
                //one line php doc?
                if (preg_match('/\s*\t*\/\*/', $line)){
                    break;
                }
            }
        }

        $phpDoc = $this->parsePhpDoc($phpDoc);

        
        $refParams = $pMethod->getParameters();
        $params = array();

        if (!$phpDoc['param'])
            $fillPhpDocParam = true;

        foreach ($refParams as $param){
            $params[$param->getName()] = $param;
            if ($fillPhpDocParam){
                $phpDoc['param'][] = array(
                    'name' => $param->getName(),
                    'type' => $param->isArray()?'array':'mixed'
                );
            }
        }

        $parameters = array();

        if (is_string(key($phpDoc['param'])))
            $phpDoc['param'] = array($phpDoc['param']);

        $c = 0;
        foreach ($phpDoc['param'] as $phpDocParam){

            $param = $params[$phpDocParam['name']];
            if (!$param) continue;
            $parameter = array(
                'type' => $phpDocParam['type']
            );

            if (is_array($pRegMatches) && $pRegMatches[$c]){
                $parameter['fromRegex'] = '$'.($c+1);
            }

            $parameter['required'] = !$param->isOptional();
            
            if ($param->isDefaultValueAvailable()){
                $parameter['default'] = str_replace(array("\n", ' '), '', var_export($param->getDefaultValue(), true));
            }
            $parameters[$this->argumentName($phpDocParam['name'])] = $parameter;
            $c++;
        }

        if (!$phpDoc['return'])
            $phpDoc['return'] = array('type' => 'mixed');

        return array(
            'description' => $phpDoc['description'],
            'parameters' => $parameters,
            'return' => $phpDoc['return']
        );
    }

    /**
     * Parse phpDoc string and returns an array.
     * 
     * @param  string $pString
     * @return array
     */
    public function parsePhpDoc($pString){

        preg_match('#^/\*\*(.*)\*/#s', trim($pString), $comment);

        $comment = trim($comment[1]);

        preg_match_all('/^\s*\*(.*)/m', $comment, $lines);
        $lines = $lines[1];

        $tags = array();
        $currentTag = '';
        $currentData = '';

        foreach ($lines as $line){
            $line = trim($line);

            if (substr($line, 0, 1) == '@'){

                if ($currentTag)
                    $tags[$currentTag][] = $currentData;
                else
                    $tags['description'] = $currentData;

                $currentData = '';
                preg_match('/@([a-zA-Z_]*)/', $line, $match);
                $currentTag = $match[1];
            }

            $currentData = trim($currentData.' '.$line);

        }
        if ($currentTag)
            $tags[$currentTag][] = $currentData;
        else
            $tags['description'] = $currentData;


        //parse tags
        $regex = array(
            'param' => array('/^@param\s*\t*([a-zA-Z_\\\[\]]*)\s*\t*\$([a-zA-Z_]*)\s*\t*(.*)/', array('type', 'name', 'description')),
            'return' => array('/^@return\s*\t*([a-zA-Z_\\\[\]]*)\s*\t*(.*)/', array('type', 'description')),
        );
        foreach ($tags as $tag => &$data){
            if ($tag == 'description') continue;
            foreach ($data as &$item){
                if ($regex[$tag]){
                    preg_match($regex[$tag][0], $item, $match);
                    $item = array();
                    $c = count($match);
                    for($i =1; $i < $c; $i++){
                        if ($regex[$tag][1][$i-1]){
                            $item[$regex[$tag][1][$i-1]] = $match[$i];

                        }
                    }
                }
            }
            if (count($data) == 1)
                $data = $data[0];

        }
        
        return $tags;
    }

    /**
     * If the name is a camelcased one whereas the first char is lowercased,
     * then we remove the first char and set first char to lower case.
     * 
     * @param  string $pName
     * @return string
     */
    public function argumentName($pName){
        if (ctype_lower(substr($pName, 0, 1)) && ctype_upper(substr($pName, 1, 1))){
            return strtolower(substr($pName, 1, 1)).substr($pName, 2);
        } return $pName;
    }

    /**
     * Find and return the route for $pUri.
     *
     * @param string $pUri
     * @param string $pMethod limit to method.
     * @return array|boolean
     */
    public function findRoute($pUri, $pMethod = '_all_'){

        if ($method = $this->routes[$pUri][$pMethod]){
            return array($method, null, $pMethod, $pUri);
        } else if ($pMethod != '_all_' && $method = $this->routes[$pUri]['_all_']){
            return array($method, null, $pMethod, $pUri);
        } else {
            //maybe we have a regex uri
            foreach ($this->routes as $routeUri => $routeMethods){

                if (preg_match('|^'.$routeUri.'$|', $pUri, $matches)){

                    if (!$routeMethods[$pMethod]){
                        if ($routeMethods['_all_'])
                            $pMethod = '_all_';
                        else
                            continue;
                    }

                    array_shift($matches);
                    foreach ($matches as $match){
                        $arguments[] = $match;
                    }

                    return array($routeMethods[$pMethod], $arguments, $pMethod, $routeUri);
                }

            }
        }

        return false;
    }

}