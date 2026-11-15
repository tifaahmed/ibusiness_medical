<?php

namespace Tests\Unit;

use App\Services\Push\FcmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The FCM client, entirely behind Http::fake() — nothing here reaches Google, and no database is touched. */
class FcmClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        $path = sys_get_temp_dir().'/fcm-test-'.uniqid().'.json';
        file_put_contents($path, json_encode(['client_email' => 'x@y.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token']));
        config(['services.fcm.project_id' => 'demo', 'services.fcm.credentials' => $path]);
    }

    public function test_it_is_not_configured_without_a_project_id_and_a_credentials_file(): void
    {
        config(['services.fcm.project_id' => null]);
        $this->assertFalse((new FcmClient)->isConfigured());

        config(['services.fcm.project_id' => 'demo', 'services.fcm.credentials' => '/nope.json']);
        $this->assertFalse((new FcmClient)->isConfigured());
    }

    public function test_it_sends_with_a_bearer_token_and_sorts_the_answers(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok123']),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'ok'], 200)
                ->push(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                ->push(['error' => ['status' => 'INTERNAL']], 500),
        ]);

        $client = new FcmClient;
        $this->assertTrue($client->isConfigured());

        $result = $client->sendMany(['a', 'b', 'c'], 'Title', 'Body', ['url' => '/products']);

        $values = array_values($result);
        sort($values);
        $this->assertSame(['failed', 'ok', 'removed'], $values);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/projects/demo/messages:send')
            && $r->hasHeader('Authorization', 'Bearer tok123')
            && $r['message']['notification']['title'] === 'Title'
            && $r['message']['data']['url'] === '/products');
    }

    public function test_an_empty_audience_sends_nothing(): void
    {
        Http::fake();
        $this->assertSame([], (new FcmClient)->sendMany([], 't', 'b'));
        Http::assertNothingSent();
    }
}
