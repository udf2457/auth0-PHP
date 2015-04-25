<?php
/**
 * Created by PhpStorm.
 * User: germanlena
 * Date: 4/23/15
 * Time: 11:12 AM
 */

namespace Auth0\Tests;


use Auth0\SDK\API\Auth0Api;
use Auth0\SDK\API\Header\Authorization\AuthorizationBearer;

class UsersTest extends \PHPUnit_Framework_TestCase {

    protected $token = '';

    public function testList() {
        $auth0 = new Auth0Api([
            'domain' => 'https://login.auth0.com',
            'basePath' => '/api/v2',
        ]);

        $response = $auth0->get()
            ->users()
            ->withHeader(new AuthorizationBearer($this->token))
            ->withParam('per_page',1)
            ->call();

        var_dump($response);
    }

}