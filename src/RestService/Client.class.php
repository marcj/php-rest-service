<?php

namespace RestService;

class Client {

    /**
     * Current output format.
     *
     * @var string
     */
    private $outputFormat = 'json';


    /**
     * List of possible output formats.
     *
     * @var array
     */
    private $outputFormats = array(
        'json' => 'asJSON',
        'xml' => 'asXML'
    );


    /**
     * List of possible methods.
     * @var array
     */
    public $methods = array('get', 'post', 'put', 'delete', 'head', 'options');


    /**
     * Current URL.
     *
     * @var string
     */
    private $url;


    /**
     * @var Server
     *
     */
    private $controller;

    /**
     * @param Server $pServerController
     */
    public function __construct($pServerController){

        $this->controller = $pServerController;
        $this->setUrl($_GET[$this->controller->getRewrittenRuleKey()]);

        $this->setupFormats();
    }

    /**
     * Sends the actual response.
     *
     * @internal
     * @param string $pHttpCode
     * @param $pMessage
     */
    public function sendResponse($pHttpCode = '200', $pMessage){

        if ($this->controller->getHttpStatusCodes() && !$_GET['_suppress_status_code'] && php_sapi_name() !== 'cli'){
            $httpMap = array(
                '200' => '200 OK',
                '500' => '500 Internal Server Error',
                '400' => '400 Bad Request',

            );
            header('HTTP/1.0 '.$httpMap[$pHttpCode]?$httpMap[$pHttpCode]:$pHttpCode);
        }

        $pMessage = array_reverse($pMessage, true); 
        $pMessage['status'] = $pHttpCode;
        $pMessage = array_reverse($pMessage, true);

        $method = $this->outputFormats[$this->outputFormat];
        $this->$method($pMessage)."\n";
        exit;

    }

    /**
     * Detect the method.
     *
     * @return string
     */
    public function getMethod(){

        $method = $_SERVER['REQUEST_METHOD'];
        if ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];

        if ($_GET['_method'])
            $method = $_GET['_method'];

        $method = strtolower($method);

        if (!in_array($method, $this->methods))
            $method = 'get';

        return $method;

    }


    /**
     * Converts $pMessage to pretty json.
     *
     * @param $pMessage
     * @return string
     */
    public function asJSON($pMessage){

        if (php_sapi_name() !== 'cli' )
            header('Content-Type: application/json; charset=utf-8');

        print json_format(json_encode($pMessage));
    }


    /**
     * Converts $pMessage to xml.
     *
     * @param $pMessage
     * @return string
     */
    public function asXML($pMessage){

        $xml = $this->toXml($pMessage);
        $xml = "<?xml version=\"1.0\"?>\n<response>\n$xml</response>\n";
        print $xml;

    }

    /**
     * @param mixed $pData
     * @param int   $pDepth
     * @return string XML
     */
    public function toXml($pData, $pParentTagName = '', $pDepth = 1){

        if (is_array($pData)){
            $content = '';

            foreach ($pData as $key => $data){
                $key = is_numeric($key) ? $pParentTagName.'-item' : $key;
                $content .= str_repeat('  ', $pDepth)
                    .'<'.htmlspecialchars($key).'>'.
                        $this->toXml($data, $key, $pDepth+1)
                    .'</'.htmlspecialchars($key).">\n";
            }
            return $content;
        } else {
            return htmlspecialchars($pData);
        }

    }


    /**
     * Add a additional output format.
     *
     * @param string $pCode
     * @param string $pMethod
     * @return ServerClient $this
     */
    public function addOutputFormat($pCode, $pMethod){
        $this->outputFormats[$pCode] = $pMethod;
        return $this;
    }


    /**
     * Set the current output format.
     *
     * @param string $pFormat a key of $outputForms
     * @return RestController
     */
    public function setFormat($pFormat){
        $this->outputFormat = $pFormat;
        return $this;
    }


    /**
     * Returns the url.
     *
     * @return string
     */
    public function getUrl(){
        return $this->url;
    }


    /**
     * Set the url.
     *
     * @param string $pUrl
     * @return Server $this
     */
    public function setUrl($pUrl){
        $this->url = $pUrl;
        return $this;
    }

    /**
     * Setup formats.
     *
     * @return ServerClient
     */
    public function setupFormats(){

        //through HTTP_ACCEPT
        if (strpos($_SERVER['HTTP_ACCEPT'], '*/*') === false){
            foreach ($this->outputFormats as $formatCode => $formatMethod){
                if (strpos($_SERVER['HTTP_ACCEPT'], $formatCode) !== false){
                    $this->outputFormat = $formatCode;
                    break;
                }
            }
        }

        //through uri suffix
        if (preg_match('/\.(\w+)$/i', $this->getUrl(), $matches)) {
            if ($this->outputFormats[$matches[1]]){
                $this->outputFormat = $matches[1];
                $url = $this->getUrl();
                $this->setUrl(substr($url, 0, (strlen($this->outputFormat)*-1)-1));
            }
        }

        return $this;
    }

}