<?php

namespace Tests\Unit;

use Tests\TestCase;

class EssayGradingResponsiveTest extends TestCase
{
    public function test_essay_grading_workspace_is_constrained_on_mobile_viewports(): void
    {
        $css = file_get_contents(public_path('css/lms-ui.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.essay-grading-page,', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $css);
        $this->assertStringContainsString('max-height: 285px; overflow-x: hidden; overflow-y: auto', $css);
        $this->assertStringContainsString('.essay-score-field input { width: auto; min-width: 0; flex: 1 1 auto; }', $css);
        $this->assertStringContainsString('bottom: calc(8px + env(safe-area-inset-bottom))', $css);
        $this->assertStringContainsString('@media (max-width: 380px)', $css);
    }
}
