<?php

namespace Tests\Unit;

use App\Support\TrustedHostRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class TrustedHostRegistryTest extends TestCase
{
    public function test_it_normalizes_exact_hosts_and_rejects_provider_wildcards(): void
    {
        $registry = app(TrustedHostRegistry::class);

        $this->assertSame(
            'random-name.trycloudflare.com',
            $registry->normalize('https://Random-Name.TryCloudflare.com/lms/login')
        );
        $this->assertSame('example.test', $registry->normalize('EXAMPLE.TEST:8443'));

        $this->expectException(InvalidArgumentException::class);
        $registry->normalize('*.trycloudflare.com');
    }

    public function test_runtime_registry_adds_and_removes_an_exact_host(): void
    {
        $path = storage_path('framework/testing/trusted-hosts-'.uniqid().'.json');
        config()->set('reverse_proxy.host_registry', $path);
        config()->set('reverse_proxy.trusted_hosts', ['lms.smkn1cerme.test']);

        $registry = app(TrustedHostRegistry::class);

        try {
            $registry->register('https://ephemeral.trycloudflare.com/lms');

            $this->assertContains('ephemeral.trycloudflare.com', $registry->hosts());
            $this->assertContains('^ephemeral\.trycloudflare\.com$', $registry->patterns());

            $registry->remove('ephemeral.trycloudflare.com');

            $this->assertNotContains('ephemeral.trycloudflare.com', $registry->registeredHosts());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
