<?php

namespace Test\Synthetic;

use RestService\Server;

class RouteTest extends \PHPUnit\Framework\TestCase
{

    public function testAllRoutesClosures()
    {

        $restService = Server::create('/')
            ->setClient('RestService\\InternalClient')
            ->addGetRoute('test', function(){
                return 'getTest';
            })
            ->addPostRoute('test', function(){
                return 'postTest';
            })
            ->addPatchRoute('test', function(){
                return 'patchTest';
            })
            ->addPutRoute('test', function(){
                return 'putTest';
            })
            ->addOptionsRoute('test', function(){
                return 'optionsTest';
            })
            ->addDeleteRoute('test', function(){
                return 'deleteTest';
            })
            ->addHeadRoute('test', function(){
                return 'headTest';
            })
            ->addRoute('all-test', function(){
                return 'allTest';
            });

        foreach ($restService->getClient()->methods as $method) {

            $this->assertEquals('{
    "status": 200,
    "data": "'.$method.'Test"
}', $restService->simulateCall('/test', $method));

            $this->assertEquals('{
    "status": 200,
    "data": "allTest"
}', $restService->simulateCall('/all-test', $method));

        }

    }

}
