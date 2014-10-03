<?php

namespace RestService;

/**
 * This client does not send any HTTP data,
 * instead it just returns the value.
 *
 * Good for testing purposes.
 */
class InternalClient extends Client
{
    public function sendResponse($pMessage, $pHttpCode = '200')
    {
        $pMessage = array_reverse($pMessage, true);
        $pMessage['status'] = $pHttpCode+0;
        $pMessage = array_reverse($pMessage, true);

        $method = $this->getOutputFormatMethod($this->getOutputFormat());

        return $this->$method($pMessage);
    }

}
