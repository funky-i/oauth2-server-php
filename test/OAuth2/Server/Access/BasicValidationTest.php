<?php

class OAuth2_Server_Access_BasicValidationTest extends PHPUnit_Framework_TestCase
{
    public function testNoAccessToken()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'The access token was not found');
    }

    public function testMalformedHeader()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['AUTHORIZATION'] = 'tH1s i5 B0gU5';
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'Malformed auth header');
    }

    public function testMultipleTokensSubmitted()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->request['access_token'] = 'TEST';
        $request->query['access_token'] = 'TEST';
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'Only one method may be used to authenticate at a time (Auth header, GET or POST)');
    }

    public function testInvalidRequestMethod()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['REQUEST_METHOD'] = 'GET';
        $request->request['access_token'] = 'TEST';
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'When putting the token in the body, the method must be POST');
    }

    public function testInvalidContentType()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['REQUEST_METHOD'] = 'POST';
        $request->server['CONTENT_TYPE'] = 'application/json';
        $request->request['access_token'] = 'TEST';
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getParameter('error'), 'invalid_request');
        $this->assertEquals($response->getParameter('error_description'), 'The content type for POST requests must be "application/x-www-form-urlencoded"');
    }

    public function testInvalidToken()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['AUTHORIZATION'] = 'Bearer TESTTOKEN';
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 401);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'The access token provided is invalid');
    }

    public function testExpiredToken()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['AUTHORIZATION'] = 'Bearer accesstoken-expired';
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 401);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'The access token provided has expired');
    }

    public function testOutOfScopeToken()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['AUTHORIZATION'] = 'Bearer accesstoken-scope';
        $request->query['scope'] = 'outofscope';
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 401);
        $this->assertEquals($response->getParameter('error'), 'insufficient_scope');
        $this->assertEquals($response->getParameter('error_description'), 'The request requires higher privileges than provided by the access token');
    }

    public function testMalformedToken()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['AUTHORIZATION'] = 'Bearer accesstoken-malformed';
        $allow = $server->verifyAccessRequest($request);
        $this->assertFalse($allow);

        $response = $server->getResponse();
        $this->assertEquals($response->getStatusCode(), 401);
        $this->assertEquals($response->getParameter('error'), 'invalid_grant');
        $this->assertEquals($response->getParameter('error_description'), 'Malformed token (missing "expires" or "client_id")');
    }

    public function testValidToken()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['AUTHORIZATION'] = 'Bearer accesstoken-scope';
        $allow = $server->verifyAccessRequest($request);
        $this->assertTrue($allow);
    }

    public function testValidTokenWithScopeParam()
    {
        $server = $this->getTestServer();
        $request = OAuth2_Request::createFromGlobals();
        $request->server['AUTHORIZATION'] = 'Bearer accesstoken-scope';
        $request->query['scope'] = 'testscope';
        $allow = $server->verifyAccessRequest($request);
        $this->assertTrue($allow);
    }

    private function getTestServer($config = array())
    {
        $storage = new OAuth2_Storage_Memory(json_decode(file_get_contents(dirname(__FILE__).'/../../../config/storage.json'), true));
        $server = new OAuth2_Server($storage, $config);

        // Add the two types supported for authorization grant
        $server->addGrantType(new OAuth2_GrantType_AuthorizationCode($storage));

        return $server;
    }
}