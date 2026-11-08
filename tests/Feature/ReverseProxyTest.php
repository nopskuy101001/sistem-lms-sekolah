<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VideoLesson;
use App\Notifications\LmsPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('reverse_proxy.trusted_hosts', [
            'localhost',
            '127.0.0.1',
            'lms.smkn1cerme.test',
            'tunnel.example.test',
        ]);
    }

    public function test_local_root_request_generates_http_urls_without_prefix(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'lms.smkn1cerme.test',
            'SERVER_NAME' => 'lms.smkn1cerme.test',
            'SERVER_PORT' => 80,
        ])->get('http://lms.smkn1cerme.test/login');

        $response->assertOk()
            ->assertSee('action="http://lms.smkn1cerme.test/login"', false)
            ->assertSee('href="http://lms.smkn1cerme.test/forgot-password"', false)
            ->assertSee('src="http://lms.smkn1cerme.test/media/logo-smkn1-cerme.png"', false);

        $cookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === 'lms_sekolah_session');

        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isSecure());
        $this->assertSame('/', $cookie->getPath());
        $this->assertNull($cookie->getDomain());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_trusted_proxy_generates_https_host_port_prefix_assets_and_forms(): void
    {
        $response = $this->fromTrustedProxy('/login', [
            'X-Forwarded-Host' => 'tunnel.example.test',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Prefix' => '/lms',
        ]);

        $response->assertOk()
            ->assertSee('action="https://tunnel.example.test/lms/login"', false)
            ->assertSee('href="https://tunnel.example.test/lms/forgot-password"', false)
            ->assertSee('href="https://tunnel.example.test/lms/css/lms-ui.css', false)
            ->assertSee('src="https://tunnel.example.test/lms/media/logo-smkn1-cerme.png"', false);

        $cookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === 'lms_sekolah_session');

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('/', $cookie->getPath());

        $video = new VideoLesson(['file_path' => 'video-lessons/example.mp4']);
        $this->assertSame(
            'https://tunnel.example.test/lms/storage/video-lessons/example.mp4',
            $video->playback_url
        );
        $this->assertSame(
            'https://tunnel.example.test/lms/video-lessons/42/answer',
            route('video-lessons.answer', 42)
        );
    }

    public function test_login_logout_session_and_redirects_keep_trusted_prefix(): void
    {
        $user = User::factory()->create([
            'role' => 'siswa',
            'nomor_induk' => '20269999',
            'password' => 'Password123!',
            'must_change_password' => false,
        ]);

        $headers = [
            'X-Forwarded-Host' => 'tunnel.example.test',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Prefix' => '/lms',
        ];

        $loginResponse = $this->withTrustedProxyHeaders($headers)
            ->post('http://127.0.0.1:8101/login', ['login' => $user->nomor_induk, 'password' => 'Password123!'])
            ->assertRedirect('https://tunnel.example.test/lms/dashboard');

        $sessionCookie = collect($loginResponse->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === 'lms_sekolah_session');

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());

        $this->assertAuthenticatedAs($user);

        $this->withTrustedProxyHeaders($headers)
            ->get('http://127.0.0.1:8101/dashboard')
            ->assertOk()
            ->assertSee('action="https://tunnel.example.test/lms/logout"', false)
            ->assertSee('href="https://tunnel.example.test/lms/dashboard"', false);

        $this->withTrustedProxyHeaders($headers)
            ->post('http://127.0.0.1:8101/logout')
            ->assertRedirect('https://tunnel.example.test/lms/login');

        $this->assertGuest();
    }

    public function test_password_reset_email_uses_validated_request_origin_and_prefix(): void
    {
        $user = User::factory()->create(['email' => 'student@example.test']);

        $this->fromTrustedProxy('/forgot-password', [
            'X-Forwarded-Host' => 'tunnel.example.test',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Prefix' => '/lms',
        ])->assertOk();

        $mail = (new LmsPasswordResetNotification('reset-token'))->toMail($user);

        $this->assertStringStartsWith(
            'https://tunnel.example.test/lms/reset-password/reset-token?',
            $mail->actionUrl
        );
    }

    public function test_password_reset_email_without_http_request_uses_canonical_app_url(): void
    {
        $user = User::factory()->create(['email' => 'student@example.test']);
        $mail = (new LmsPasswordResetNotification('reset-token'))->toMail($user);

        $this->assertStringStartsWith(
            rtrim((string) config('app.url'), '/').'/reset-password/reset-token?',
            $mail->actionUrl
        );
    }

    public function test_https_proxy_without_prefix_keeps_root_subdomain_compatibility(): void
    {
        $this->fromTrustedProxy('/login', [
            'X-Forwarded-Host' => 'tunnel.example.test',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '8443',
        ])->assertOk()
            ->assertSee('action="https://tunnel.example.test:8443/login"', false)
            ->assertDontSee('tunnel.example.test:8443/lms/', false);
    }

    public function test_forwarded_headers_from_untrusted_address_are_ignored(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
        ])->withHeaders([
            'X-Forwarded-Host' => 'tunnel.example.test',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Prefix' => '/lms',
        ])->get('/login')
            ->assertOk()
            ->assertSee('action="http://localhost/login"', false)
            ->assertDontSee('tunnel.example.test', false);
    }

    public function test_unknown_direct_and_forwarded_hosts_are_rejected(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_HOST' => 'attacker.example',
            'SERVER_NAME' => 'attacker.example',
            'SERVER_PORT' => 80,
        ])->get('http://attacker.example/login')->assertStatus(400);

        $this->fromTrustedProxy('/login', [
            'X-Forwarded-Host' => 'attacker.example',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Prefix' => '/lms',
        ])->assertStatus(400);
    }

    private function fromTrustedProxy(string $uri, array $headers)
    {
        return $this->withTrustedProxyHeaders($headers)->get('http://127.0.0.1:8101'.$uri);
    }

    private function withTrustedProxyHeaders(array $headers): static
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '127.0.0.1:8101',
            'SERVER_NAME' => '127.0.0.1',
            'SERVER_PORT' => 8101,
        ])->withHeaders($headers);
    }
}
