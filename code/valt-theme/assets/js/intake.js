/**
 * Valt Artist Intake — inline form submit.
 * Progressive enhancement: posts JSON to valt/v1/artist-intake, shows a status
 * message, and mirrors a completion event into awen-client analytics if present.
 */
(function () {
	'use strict';

	function track(event, data) {
		if (window.awenAnalytics && typeof window.awenAnalytics.track === 'function') {
			try { window.awenAnalytics.track(event, data || {}); } catch (e) {}
		}
	}

	// Mirror the conversion into GA4 (recommended "generate_lead" event) when gtag is present.
	function gaEvent(name, params) {
		if (typeof window.gtag === 'function') {
			try { window.gtag('event', name, params || {}); } catch (e) {}
		}
	}

	function init(form) {
		var endpoint = form.getAttribute('data-endpoint');
		var nonce = form.getAttribute('data-nonce') || '';
		var msg = form.querySelector('.valt-intake__msg');
		var btn = form.querySelector('.valt-intake__submit');

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (btn.disabled) return;

			var data = {};
			form.querySelectorAll('input, textarea, select').forEach(function (el) {
				if (!el.name) return;
				if (el.type === 'checkbox') { data[el.name] = el.checked ? 1 : ''; }
				else { data[el.name] = el.value; }
			});
			data.page_url = window.location.href;

			btn.disabled = true;
			var label = btn.textContent;
			btn.textContent = 'Sending…';
			setStatus('', '');

			fetch(endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
				body: JSON.stringify(data)
			})
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (res) {
				if (res.ok && res.j && res.j.success) {
					track('artist_intake_submitted', { source: data.source || '' });
					gaEvent('generate_lead', { event_category: 'artist_intake', event_label: data.source || 'for-artists', value: 1 });
					form.querySelectorAll('.valt-intake__row, .valt-intake__field, .valt-intake__consent, .valt-intake__submit').forEach(function (el) { el.style.display = 'none'; });
					setStatus(res.j.message || 'Thanks — you’re on the list.', 'ok');
				} else {
					btn.disabled = false;
					btn.textContent = label;
					setStatus((res.j && res.j.message) || 'Something went wrong — please try again.', 'err');
				}
			})
			.catch(function () {
				btn.disabled = false;
				btn.textContent = label;
				setStatus('Network error — please try again.', 'err');
			});
		});

		function setStatus(text, kind) {
			msg.textContent = text;
			msg.className = 'valt-intake__msg' + (kind ? ' is-' + kind : '');
		}
	}

	function boot() {
		document.querySelectorAll('form.valt-intake').forEach(init);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
