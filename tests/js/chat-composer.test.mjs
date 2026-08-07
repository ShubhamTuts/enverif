import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');

test('chat captures selected controls before busy state disables the agent picker', () => {
    const submitStart = source.indexOf("form?.addEventListener('submit'");
    assert.notEqual(submitStart, -1, 'chat submit handler must exist');

    const submitHandler = source.slice(submitStart, submitStart + 5000);
    const formDataPosition = submitHandler.indexOf('new FormData(form)');
    const busyPosition = submitHandler.indexOf('setBusy(true)');

    assert.notEqual(formDataPosition, -1, 'chat submit handler must create FormData');
    assert.notEqual(busyPosition, -1, 'chat submit handler must enter busy state');
    assert.ok(
        formDataPosition < busyPosition,
        'FormData must be captured before setBusy(true) disables the selected agent control',
    );
});
