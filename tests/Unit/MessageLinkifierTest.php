<?php

namespace Tests\Unit;

use App\Support\MessageLinkifier;
use PHPUnit\Framework\TestCase;

class MessageLinkifierTest extends TestCase
{
    public function test_it_converts_supported_urls_and_keeps_punctuation_outside_the_link(): void
    {
        $html = (string) MessageLinkifier::render(
            'Materi: www.example.com/modul?kelas=XI&bab=1. Lanjut https://sekolah.test/info!',
        );

        $this->assertStringContainsString(
            'href="https://www.example.com/modul?kelas=XI&amp;bab=1"',
            $html,
        );
        $this->assertStringContainsString(
            'href="https://sekolah.test/info"',
            $html,
        );
        $this->assertStringContainsString('</a>.', $html);
        $this->assertStringContainsString('</a>!', $html);
    }

    public function test_it_escapes_message_html_instead_of_executing_it(): void
    {
        $html = (string) MessageLinkifier::render(
            '<script>alert("x")</script> https://example.com',
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString(
            '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
            $html,
        );
        $this->assertStringContainsString(
            'target="_blank" rel="noopener noreferrer nofollow"',
            $html,
        );
    }
}
