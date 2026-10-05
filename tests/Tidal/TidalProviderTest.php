<?php

namespace SocialiteProviders\Tests\Tidal;

use GuzzleHttp\Psr7\Response;
use SocialiteProviders\Tests\TestCase;
use SocialiteProviders\Tidal\Provider;

class TidalProviderTest extends TestCase
{
    protected function provider(): string
    {
        return Provider::class;
    }

    public function test_redirect_targets_the_authorize_endpoint(): void
    {
        $request = $this->makeRequestWithSession();

        $url = $this->makeProvider($request)->stateless()->redirect()->getTargetUrl();

        $this->assertStringStartsWith('https://login.tidal.com/authorize?', $url);

        $params = $this->queryParams($url);

        $this->assertSame(static::CLIENT_ID, $params['client_id']);
        $this->assertSame(static::REDIRECT_URI, $params['redirect_uri']);
        $this->assertSame('code', $params['response_type']);
        $this->assertSame('user.read', $params['scope']);
        $this->assertSame('S256', $params['code_challenge_method']);
        $this->assertNotEmpty($params['code_challenge']);
    }

    public function test_user_is_mapped_from_the_api_response(): void
    {
        $responses = [
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'access_token' => 'access-token',
            ])),
            new Response(200, ['Content-Type' => 'application/vnd.api+json'], $this->fixture('user.json')),
        ];

        $request = $this->makeRequestWithSession(['code' => 'code', 'state' => 'state']);

        $user = $this->makeProvider($request, $responses)->stateless()->user();

        $this->assertSame('access-token', $user->token);
        $this->assertSame('12345', $user->getId());
        $this->assertSame('j@doe.com', $user->getNickname());
        $this->assertSame('j@doe.com', $user->getEmail());
        $this->assertNull($user->getName());
        $this->assertNull($user->getAvatar());
    }

    /**
     * Live accounts often omit firstName/lastName, but they are joined when present.
     */
    public function test_user_name_is_joined_from_first_and_last_name(): void
    {
        $document = $this->fixtureJson('user.json');
        $document['data']['attributes']['firstName'] = 'J.';
        $document['data']['attributes']['lastName'] = 'Doe';

        $responses = [
            new Response(200, [], (string) json_encode(['access_token' => 'access-token'])),
            new Response(200, [], (string) json_encode($document)),
        ];

        $request = $this->makeRequestWithSession(['code' => 'code', 'state' => 'state']);

        $user = $this->makeProvider($request, $responses)->stateless()->user();

        $this->assertSame('J. Doe', $user->getName());
    }
}
