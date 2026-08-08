<?php

namespace Tests\Feature;

use Tests\TestCase;

final class RuntimeUiContractTest extends TestCase
{
    public function test_agent_activity_is_global_not_a_large_inline_chat_block(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $runtime = (string) file_get_contents(resource_path('js/runtime-ui.js'));

        self::assertStringContainsString('data-agent-activity-trigger', $layout);
        self::assertStringContainsString('Agent activity', $layout);
        self::assertStringContainsString('Agent activity', $runtime);
        self::assertStringNotContainsString('Live activity', $runtime);
        self::assertStringNotContainsString('const renderInline =', $runtime);
        self::assertStringNotContainsString('data-chat-inline-activity', $runtime);
        self::assertStringContainsString('data-runtime-approval-stack', $runtime);
    }

    public function test_runtime_activity_does_not_start_an_unconditional_detailed_poll(): void
    {
        $runtime = (string) file_get_contents(resource_path('js/runtime-ui.js'));

        self::assertStringNotContainsString("fetchActivity();\n    window.addEventListener('beforeunload'", $runtime);
        self::assertStringContainsString('document.hidden', $runtime);
        self::assertStringContainsString('data-agent-activity-trigger', $runtime);
    }

    public function test_runtime_ui_uses_enverif_theme_tokens_and_keeps_source_public_assets_identical(): void
    {
        $sourceCss = (string) file_get_contents(resource_path('css/runtime-ui.css'));
        $publicCss = (string) file_get_contents(public_path('assets/runtime-ui.css'));
        $sourceJs = (string) file_get_contents(resource_path('js/runtime-ui.js'));
        $publicJs = (string) file_get_contents(public_path('assets/runtime-ui.js'));

        foreach (['var(--surface,#fff)', 'var(--surface-2,#f8fafc)', 'var(--border,#e5e7eb)', 'var(--success,#22c55e)', 'var(--warning,#f59e0b)', 'var(--danger,#ef4444)'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $sourceCss);
        }
        foreach (['var(--panel', 'var(--panel-strong', 'var(--line', 'var(--text', 'var(--muted', 'var(--good', 'var(--warn', 'var(--bad', 'var(--accent'] as $required) {
            self::assertStringContainsString($required, $sourceCss);
        }

        self::assertSame($sourceCss, $publicCss);
        self::assertSame($sourceJs, $publicJs);
    }
}
