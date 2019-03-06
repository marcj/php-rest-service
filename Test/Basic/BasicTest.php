<?php

namespace Test\Synthetic;

use RestService\Server;
use Test\Controller\MyRoutes;

class BasicTest extends \PHPUnit\Framework\TestCase
{
    public function testCustomUrl()
    {
        $restService = Server::create('/', new MyRoutes)
            ->setClient('RestService\\InternalClient')
            ->collectRoutes();

        $response = $restService->simulateCall('/test/test', 'get');
        $this->assertEquals('{
    "status": 200,
    "data": "test"
}', $response);

    }
}
