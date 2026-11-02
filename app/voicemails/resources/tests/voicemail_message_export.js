const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const elements = {};
for (const name of ['btn_export_wavs', 'voicemail_export_banner', 'voicemail_export_status',
	'voicemail_export_progress', 'voicemail_export_download']) {
	elements[name] = {
		hidden: true,
		addEventListener(event, callback) { this[event] = callback; },
		removeAttribute(name) { delete this[name]; }
	};
}
const responses = [];
const requests = [];
let timer;
const context = {
	document: {getElementById: name => elements[name]},
	window: {location: {href: 'https://pbx.example/app/voicemails/voicemail_messages.php?id=1000'}},
	URL, FormData,
	setTimeout(callback) { timer = callback; return 1; },
	clearTimeout() { timer = undefined; },
	async fetch(url, options) {
		requests.push({url, options});
		const response = responses.shift();
		if (response instanceof Error) { throw response; }
		return {
			ok: !response.error,
			headers: {get: () => 'application/json'},
			json: async () => response
		};
	}
};
const config = {
	endpoint: 'voicemail_messages_export.php', scope: '1000',
	token: {name: 'csrf-name', hash: 'csrf-value'},
	text: {
		queued: 'Starting',
		preparing: 'Preparing {processed} of {total}',
		finalizing: 'Finalizing ZIP...',
		ready: 'Ready: {added} WAV files',
		failed: 'Export failed',
		skipped: '{count} skipped'
	}
};
const tick = () => new Promise(resolve => setImmediate(resolve));

async function main() {
	vm.createContext(context);
	vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'javascript', 'voicemail_message_export.js'), 'utf8'), context);
	responses.push({state: null});
	context.voicemail_export_init(config);
	await tick();
	assert.equal(elements.voicemail_export_banner.hidden, true);

	responses.push({state: {stage: 'preparing', total: 208, processed: 100, added: 99, skipped: 1}});
	await elements.btn_export_wavs.click();
	assert.equal(elements.btn_export_wavs.disabled, true);
	assert.equal(elements.voicemail_export_status.textContent, 'Preparing 100 of 208 1 skipped');
	assert.equal(elements.voicemail_export_progress.value, 100);
	assert.equal(requests[1].options.method, 'POST');
	assert.equal(requests[1].options.body.get('csrf-name'), 'csrf-value');

	responses.push({state: {stage: 'finalizing', total: 208, processed: 208, added: 205, skipped: 3}});
	await timer();
	assert.equal(elements.voicemail_export_status.textContent, 'Finalizing ZIP... 3 skipped');
	assert.equal('value' in elements.voicemail_export_progress, false);
	assert.equal(elements.voicemail_export_download.hidden, true);
	assert.equal(elements.btn_export_wavs.disabled, true);

	responses.push({state: {stage: 'ready', total: 208, processed: 208, added: 205, skipped: 3, id: 'job-id'}});
	await timer();
	assert.equal(elements.voicemail_export_status.textContent, 'Ready: 205 WAV files 3 skipped');
	assert.equal(elements.voicemail_export_download.hidden, false);
	assert.equal(elements.btn_export_wavs.disabled, false);
	assert.equal(elements.voicemail_export_progress.hidden, true);
	const download = new URL(elements.voicemail_export_download.href);
	assert.equal(download.searchParams.get('export_id'), 'job-id');
	assert.equal(download.searchParams.get('id'), '1000');
	assert.equal(requests.filter(request => request.url.searchParams.get('export_action') === 'download').length, 0);

	responses.push({error: 'CSRF rejected'});
	await elements.btn_export_wavs.click();
	assert.equal(elements.voicemail_export_status.textContent, 'CSRF rejected');
	assert.equal(elements.voicemail_export_download.hidden, true);
	assert.equal(elements.btn_export_wavs.disabled, false);

	responses.push(new Error('Network failed'));
	await elements.btn_export_wavs.click();
	assert.equal(elements.voicemail_export_status.textContent, 'Network failed');
	assert.equal(elements.btn_export_wavs.disabled, false);

	responses.push({state: {stage: 'ready', total: 2, processed: 2, added: 2, skipped: 0, id: 'resumed-id'}});
	elements.btn_export_wavs.disabled = false;
	context.voicemail_export_init(config);
	await tick();
	assert.equal(new URL(elements.voicemail_export_download.href).searchParams.get('export_id'), 'resumed-id');
	elements.btn_export_wavs.disabled = true;
	responses.push({state: {stage: 'ready', total: 2, processed: 2, added: 2, skipped: 0, id: 'empty-mailbox-id'}});
	context.voicemail_export_init(config);
	await tick();
	assert.equal(elements.btn_export_wavs.disabled, true);
	assert.equal(elements.voicemail_export_download.hidden, false);
	console.log('PASS: real progress, indeterminate finalizing, one explicit download, CSRF, errors and reload resume.');
}

main().catch(error => {
	console.error(error);
	process.exitCode = 1;
});
