<?php

namespace Test\Synthetic;

use RestService\Server;
use Test\Controller\MyRoutes;

class UnicodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Server
     */
    private $restService;

    public function setUp()
    {
        $this->restService = Server::create('/', new MyRoutes)
            ->setClient('RestService\\InternalClient')
            ->collectRoutes();
    }
	
	private function assertEcho($test_string){
		$response = $this->restService->simulateCall('/echo?test_string=' + rawurlencode($test_string), 'post');
		$this->assertEquals('{
    "status": 200,
    "data": "' + $test_string + '"
}', $response);
	}
	
    public function testUnicode()
    {
        $this->assertEcho('test');
    }
}