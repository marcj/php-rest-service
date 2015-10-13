<?php

namespace Test\Synthetic;

use RestService\Server;
use Test\Controller\MyRoutes;

class UnicodeTest extends \PHPUnit_Framework_TestCase
{
	private function assertEncoding($restService, $test_string){
		$response = $restService->simulateCall('/test/echo?test_string=' + rawurlencode($test_string), 'get');
		$this->assertEquals('{
    "status": 200,
    "data": "' + $test_string + '"
}', $response);
	}
	
    public function testUnicode()
    {
        $restService = Server::create('/', new MyRoutes)
            ->setClient('RestService\\InternalClient')
            ->collectRoutes();

        $this->assertEncoding($restService, 'test');
    }
}