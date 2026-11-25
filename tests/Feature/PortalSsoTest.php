<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Opening the studio from the platforms portal: a valid one-time link signs in; anything else is refused. */
class PortalSsoTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = str_repeat('s', 40);
        config(['studio.password' => 'first-pass-1', 'studio.portal_secret' => $this->secret, 'studio.portal_app' => 'profile']);
    }

    private function token(array $c = [], ?string $secret = null, string $app = 'profile', int $ttl = 60): string
    {
        $now = time();
        $body = rtrim(strtr(base64_encode(json_encode($c + ['app' => $app, 'iat' => $now, 'exp' => $now + $ttl, 'jti' => bin2hex(random_bytes(12))])), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', 'v1.'.$body, $secret ?? $this->secret, true)), '+/', '-_'), '=');

        return "v1.$body.$sig";
    }

    public function test_valid_link_signs_in_once(): void
    {
        $this->get('/')->assertRedirect('/login');
        $t = $this->token(['username' => 'salman']);
        $this->get('/sso?t='.urlencode($t).'&next=/')->assertRedirect('/');
        $this->get('/')->assertOk();
        $this->post('/logout');
        $this->get('/sso?t='.urlencode($t))->assertStatus(400);
        $this->get('/')->assertRedirect('/login');
    }

    public function test_bad_links_are_refused(): void
    {
        $this->get('/sso?t='.urlencode($this->token([], str_repeat('x', 40))))->assertStatus(400);
        $this->get('/sso?t='.urlencode($this->token([], null, 'giftprofile')))->assertStatus(400);
        $this->get('/sso?t='.urlencode($this->token([], null, 'profile', -5)))->assertStatus(400);
        $this->get('/sso?t=v1.x.y')->assertStatus(400);
        $this->get('/')->assertRedirect('/login');
    }

    public function test_next_stays_on_this_site(): void
    {
        $this->get('/sso?t='.urlencode($this->token()).'&next=//evil.example')->assertRedirect('/');
    }
}
