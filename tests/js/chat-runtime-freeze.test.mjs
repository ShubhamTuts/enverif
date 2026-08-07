import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const appSource = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const runtimeSource = readFileSync(new URL('../../resources/js/runtime-ui.js', import.meta.url), 'utf8');

test('chat polling does not rewrite an unchanged transcript', () => {
    assert.match(appSource, /let transcriptVersion\s*=/, 'chat must retain the last rendered transcript version');
    assert.match(
        appSource,
        /data\?\.transcript_version[\s\S]{0,300}transcriptVersion/,
        'status polling must compare the server transcript version before replacing transcript HTML',
    );
});

test('runtime projection repaint cannot observe and retrigger its own DOM writes', () => {
    assert.doesNotMatch(
        runtimeSource,
        /new MutationObserver\(\(\) => renderProjection\(\)\)\.observe\(chatScroll,\s*\{childList:true,\s*subtree:true\}\)/,
        'a subtree observer that calls renderProjection directly creates a self-triggering mutation loop',
    );
    assert.match(
        runtimeSource,
        /enverif:chat-transcript-rendered/,
        'runtime projection should repaint from an explicit transcript-rendered event instead',
    );
});
