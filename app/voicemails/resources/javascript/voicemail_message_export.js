function voicemail_export_init(config) {
	const button = document.getElementById('btn_export_wavs');
	const banner = document.getElementById('voicemail_export_banner');
	const status = document.getElementById('voicemail_export_status');
	const progress = document.getElementById('voicemail_export_progress');
	const link = document.getElementById('voicemail_export_download');
	const hasMessages = !button.disabled;
	let timer;

	function url(action) {
		const result = new URL(config.endpoint, window.location.href);
		result.searchParams.set('export_action', action);
		result.searchParams.set('id', config.scope);
		return result;
	}

	function error(message) {
		clearTimeout(timer);
		banner.hidden = false;
		status.textContent = message;
		progress.hidden = true;
		link.hidden = true;
		button.disabled = !hasMessages;
	}

	async function request(action) {
		const options = {credentials: 'same-origin'};
		if (action === 'start') {
			options.method = 'POST';
			options.body = new FormData();
			options.body.append(config.token.name, config.token.hash);
		}
		const response = await fetch(url(action), options);
		if (!response.ok) {
			const contentType = response.headers.get('Content-Type') || '';
			const data = contentType.includes('application/json') ? await response.json() : null;
			throw new Error(data && data.error ? data.error : config.text.failed);
		}
		if (!(response.headers.get('Content-Type') || '').includes('application/json')) {
			throw new Error(config.text.failed);
		}
		return response.json();
	}

	function render(state) {
		if (!state) {
			banner.hidden = true;
			button.disabled = !hasMessages;
			return;
		}
		banner.hidden = false;
		link.hidden = true;
		const active = ['queued', 'preparing', 'finalizing'].includes(state.stage);
		button.disabled = active || !hasMessages;
		progress.hidden = !active;
		progress.max = Math.max(state.total, 1);
		progress.value = state.processed;
		if (state.stage === 'failed') {
			error(config.text.failed + ' ' + config.text.skipped.replace('{count}', state.skipped));
			return;
		}
		status.textContent = config.text[state.stage]
			.replace('{processed}', state.processed)
			.replace('{total}', state.total)
			.replace('{added}', state.added);
		if (state.skipped) {
			status.textContent += ' ' + config.text.skipped.replace('{count}', state.skipped);
		}
		if (state.stage === 'finalizing') {
			// File preparation is complete, but ZIP finalization has no file-count progress.
			progress.removeAttribute('value');
		}
		if (state.stage === 'ready') {
			const download = url('download');
			download.searchParams.set('export_id', state.id);
			link.href = download.toString();
			link.hidden = false;
		}
		if (active) {
			timer = setTimeout(poll, 2000);
		}
	}

	async function poll() {
		try {
			render((await request('status')).state);
		}
		catch (e) {
			error(e.message);
		}
	}

	button.addEventListener('click', async function () {
		clearTimeout(timer);
		button.disabled = true;
		banner.hidden = false;
		link.hidden = true;
		progress.hidden = true;
		status.textContent = config.text.queued;
		try {
			render((await request('start')).state);
		}
		catch (e) {
			error(e.message);
		}
	});

	poll();
}
