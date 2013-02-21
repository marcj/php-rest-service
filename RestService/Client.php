<?php

namespace RestService;

class Client
{
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
    public $methods = array('get', 'post', 'put', 'delete', 'head', 'options', 'patch');

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
     * Custom set http method.
     *
     * @var string
     */
    private $method;

    /**
     * @param Server $pServerController
     */
    public function __construct($pServerController)
    {
        $this->controller = $pServerController;
        if (isset($_SERVER['PATH_INFO']))
            $this->setUrl($_SERVER['PATH_INFO']);

        $this->setupFormats();
    }

    /**
     * @param \RestService\Server $controller
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return \RestService\Server
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Sends the actual response.
     *
     * @param string $pHttpCode
     * @param $pMessage
     */
    public function sendResponse($pHttpCode = '200', $pMessage)
    {
        if ($this->controller->getHttpStatusCodes() && isset($_GET['_suppress_status_code']) &&
            !$_GET['_suppress_status_code'] && php_sapi_name() !== 'cli') {
            $httpMap = array(
                '200' => '200 OK',
                '500' => '500 Internal Server Error',
                '400' => '400 Bad Request',

            );
            header('HTTP/1.0 '.$httpMap[$pHttpCode]?$httpMap[$pHttpCode]:$pHttpCode);
        } elseif (php_sapi_name() !== 'cli') {
            header('HTTP/1.0 200 OK');
        }

        $pMessage = array_reverse($pMessage, true);
        $pMessage['status'] = $pHttpCode+0;
        $pMessage = array_reverse($pMessage, true);

        $method = $this->getOutputFormatMethod($this->getOutputFormat());
        echo $this->$method($pMessage);
        exit;

    }

    /**
     * @param  string $pFormat
     * @return string
     */
    public function getOutputFormatMethod($pFormat)
    {
        return $this->outputFormats[$pFormat];
    }

    /**
     * @return string
     */
    public function getOutputFormat()
    {
        return $this->outputFormat;
    }

    /**
     * Detect the method.
     *
     * @return string
     */
    public function getMethod()
    {
        if ($this->method) {
            return $this->method;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        if ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];

        if ($_GET['_method'])
            $method = $_GET['_method'];
        else if ($_POST['_method'])
            $method = $_POST['_method'];

        $method = strtolower($method);

        if (!in_array($method, $this->methods))
            $method = 'get';

        return $method;

    }

    /**
     * Sets a custom http method. It does then not check against
     * SERVER['REQUEST_METHOD'], $_GET['_method'] etc anymore.
     *
     * @param  string $pMethod
     * @return Client
     */
    public function setMethod($pMethod)
    {
        $this->method = $pMethod;

        return $this;
    }

    /**
     * Converts $pMessage to pretty json.
     *
     * @param $pMessage
     * @return string
     */
    public function asJSON($pMessage)
    {
        if (php_sapi_name() !== 'cli' )
            header('Content-Type: application/json; charset=utf-8');

        return $this->jsonFormat($pMessage);
    }

    /**
     * Indents a flat JSON string to make it more human-readable.
     *
     * Original at http://recursive-design.com/blog/2008/03/11/format-json-with-php/
     *
     * @param string $json The original JSON string to process.
     *
     * @return string Indented version of the original JSON string.
     */
    public function jsonFormat($json)
    {
        if (!is_string($json)) $json = json_encode($json);

        $result      = '';
        $pos         = 0;
        $strLen      = strlen($json);
        $indentStr   = '    ';
        $newLine     = "\n";
        $prevChar    = '';
        $outOfQuotes = true;

        for ($i=0; $i<=$strLen; $i++) {

            // Grab the next character in the string.
            $char = substr($json, $i, 1);

            // Are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = !$outOfQuotes;

                // If this character is the end of an element,
                // output a new line and indent the next line.
            } elseif (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;
                $pos --;
                for ($j=0; $j<$pos; $j++) {
                    $result .= $indentStr;
                }
            } elseif ($char == ':' && $outOfQuotes) {
                $char .= ' ';
            }

            // Add the character to the result string.
            $result .= $char;

            // If the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;
                if ($char == '{' || $char == '[') {
                    $pos ++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentStr;
                }
            }

            $prevChar = $char;
        }

        return $result;
    }

    /**
     * Converts $pMessage to xml.
     *
     * @param $pMessage
     * @return string
     */
    public function asXML($pMessage)
    {
        $xml = $this->toXml($pMessage);
        $xml = "<?xml version=\"1.0\"?>\n<response>\n$xml</response>\n";

        return $xml;

    }

    /**
     * @param  mixed  $pData
     * @param  string $pParentTagName
     * @param  int    $pDepth
     * @return string XML
     */
    public function toXml($pData, $pParentTagName = '', $pDepth = 1)
    {
        if (is_array($pData)) {
            $content = '';

            foreach ($pData as $key => $data) {
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
     * @param  string       $pCode
     * @param  string       $pMethod
     * @return ServerClient $this
     */
    public function addOutputFormat($pCode, $pMethod)
    {
        $this->outputFormats[$pCode] = $pMethod;

        return $this;
    }

    /**
     * Set the current output format.
     *
     * @param  string         $pFormat a key of $outputForms
     * @return RestController
     */
    public function setFormat($pFormat)
    {
        $this->outputFormat = $pFormat;

        return $this;
    }

    /**
     * Returns the url.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the url.
     *
     * @param  string $pUrl
     * @return Server $this
     */
    public function setUrl($pUrl)
    {
        $this->url = $pUrl;

        return $this;
    }

    /**
     * Setup formats.
     *
     * @return ServerClient
     */
    public function setupFormats()
    {
        //through HTTP_ACCEPT
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], '*/*') === false) {
            foreach ($this->outputFormats as $formatCode => $formatMethod) {
                if (strpos($_SERVER['HTTP_ACCEPT'], $formatCode) !== false) {
                    $this->outputFormat = $formatCode;
                    break;
                }
            }
        }

        //through uri suffix
        if (preg_match('/\.(\w+)$/i', $this->getUrl(), $matches)) {
            if ($this->outputFormats[$matches[1]]) {
                $this->outputFormat = $matches[1];
                $url = $this->getUrl();
                $this->setUrl(substr($url, 0, (strlen($this->outputFormat)*-1)-1));
            }
        }

        return $this;
    }

}
