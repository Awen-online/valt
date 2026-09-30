/**
 * Valt Feedback Survey
 *
 * Evaluates trigger conditions and runs the multi-step survey modal.
 * Config comes from wp_localize_script -> window.VALTSurveyConfig.
 * Ported from the Sync.Land survey; submits to valt/v1/survey and mirrors
 * survey_shown / survey_completed into awen-client analytics when present.
 */
(function () {
	'use strict';

	var config = window.VALTSurveyConfig || {};
	var modal = null;
	var currentStep = 1;
	var surveyData = {};
	var shown = false;

	function track(event, data) {
		if (window.awenAnalytics && typeof window.awenAnalytics.track === 'function') {
			try { window.awenAnalytics.track(event, data || {}); } catch (e) {}
		}
	}

	// --- Trigger evaluation --------------------------------------------------

	function isDismissed() {
		var d = localStorage.getItem('valt_survey_dismissed');
		if (!d) return false;
		return (Date.now() - parseInt(d, 10)) < (90 * 24 * 60 * 60 * 1000);
	}

	function markDismissed() {
		localStorage.setItem('valt_survey_dismissed', Date.now().toString());
	}

	// Counts VISITS (browser sessions), not page loads. Counting every page load fired the
	// survey on a new visitor's 3rd click.
	function checkVisitCount() {
		var threshold = config.visit_count || 3;
		var count = parseInt(localStorage.getItem('valt_visit_count') || '0', 10);
		try {
			if (!sessionStorage.getItem('valt_visit_counted')) {
				sessionStorage.setItem('valt_visit_counted', '1');
				count += 1;
				localStorage.setItem('valt_visit_count', count.toString());
			}
		} catch (e) { return false; }
		return count >= threshold;
	}

	function checkTimeOnSite() {
		var threshold = (config.time_on_site || 300) * 1000;
		setTimeout(function () {
			if (!isDismissed()) showSurvey('time_on_site');
		}, threshold);
	}

	function checkManualTrigger() {
		var t = document.getElementById('valt-survey-trigger');
		return t && t.getAttribute('data-trigger') === 'manual';
	}

	function checkPostMint() {
		return config.post_mint && window.location.search.indexOf('minted=success') !== -1;
	}

	function evaluateTriggers() {
		// The manual trigger ([valt_survey] on a dedicated /feedback page linked from
		// outreach) always opens, even if the passive modal was dismissed earlier.
		if (checkManualTrigger()) { showSurvey('manual'); return; }
		if (isDismissed()) return;
		if (checkPostMint()) { showSurvey('post_mint'); return; }
		if (checkVisitCount()) { showSurvey('visit_count'); return; }
		checkTimeOnSite();
	}

	// --- Modal ---------------------------------------------------------------

	function showSurvey(triggerType) {
		modal = document.getElementById('valt-survey-modal');
		if (!modal || shown) return;
		shown = true;
		surveyData.trigger_type = triggerType;
		surveyData.page_url = window.location.href;
		modal.style.display = 'flex';
		updateStepIndicator();
		track('survey_shown', { trigger: triggerType });
	}

	function hideSurvey() {
		if (modal) modal.style.display = 'none';
		markDismissed();
	}

	function goToStep(step) {
		if (!modal) return;
		modal.querySelectorAll('.valt-survey-step').forEach(function (s) { s.style.display = 'none'; });
		var target = modal.querySelector('[data-step="' + step + '"]');
		if (target) {
			target.style.display = 'block';
			currentStep = step;
			updateStepIndicator();
		}
	}

	function updateStepIndicator() {
		if (!modal) return;
		var indicator = modal.querySelector('.valt-survey-step-indicator');
		if (indicator) {
			indicator.textContent = (typeof currentStep === 'number') ? ('Step ' + currentStep + ' of 5') : '';
		}
	}

	// --- Submit --------------------------------------------------------------

	function submitSurvey() {
		fetch(config.api_url + '/survey', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce || '' },
			body: JSON.stringify(surveyData)
		}).catch(function () {});

		track('survey_completed', { trigger: surveyData.trigger_type, nps: surveyData.nps_score });
		goToStep('done');
		markDismissed();
		setTimeout(hideSurvey, 2500);
	}

	// --- Wiring --------------------------------------------------------------

	// --- Inline form (dedicated /feedback page) ------------------------------

	function initInlineForm(form) {
		var picked = { nps_score: null, fractional_sentiment: null };

		var npsBtns = form.querySelectorAll('.valt-nps-btn');
		npsBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				npsBtns.forEach(function (b) { b.classList.remove('active'); });
				this.classList.add('active');
				picked.nps_score = parseInt(this.getAttribute('data-score'), 10);
			});
		});

		// Fractional-ownership sentiment scale (1 = not for me … 5 = love it).
		var scaleBtns = form.querySelectorAll('.valt-scale-btn');
		scaleBtns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				scaleBtns.forEach(function (b) { b.classList.remove('active'); });
				this.classList.add('active');
				picked.fractional_sentiment = parseInt(this.getAttribute('data-sentiment'), 10);
			});
		});

		var errEl = form.querySelector('.valt-survey-inline-error');
		var submitBtn = form.querySelector('.valt-survey-submit');

		function showError(msg) { if (errEl) { errEl.textContent = msg; errEl.hidden = false; } }

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var hp = form.querySelector('input[name="website"]');
			var payload = { trigger_type: 'inline', page_url: window.location.href, website: hp ? hp.value : '' };
			if (picked.nps_score !== null) payload.nps_score = picked.nps_score;
			if (picked.fractional_sentiment !== null) payload.fractional_sentiment = picked.fractional_sentiment;

			var uc = [];
			form.querySelectorAll('input[name="use_case"]:checked').forEach(function (cb) { uc.push(cb.value); });
			if (uc.length) payload.use_case = uc;

			var cv = form.querySelector('input[name="collect_value"]:checked');
			if (cv) payload.collect_value = cv.value;

			var ie = form.querySelector('input[name="invest_effect"]:checked');
			if (ie) payload.invest_effect = ie.value;

			var fr = form.querySelector('textarea[name="feature_request"]');
			if (fr && fr.value.trim()) payload.feature_request = fr.value.trim();

			var hf = form.querySelector('input[name="how_found_us"]:checked');
			if (hf) payload.how_found_us = hf.value;

			if (!('nps_score' in payload || 'fractional_sentiment' in payload || 'use_case' in payload || 'collect_value' in payload || 'invest_effect' in payload || 'feature_request' in payload || 'how_found_us' in payload)) {
				showError('Please answer at least one question before sending.');
				return;
			}
			if (errEl) errEl.hidden = true;
			if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending…'; }

			fetch(config.api_url + '/survey', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce || '' },
				body: JSON.stringify(payload)
			}).then(function (r) {
				return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, body: j }; });
			}).then(function (res) {
				if (res.ok && res.body && res.body.success) {
					form.querySelectorAll('.valt-survey-field').forEach(function (el) { el.style.display = 'none'; });
					if (submitBtn) submitBtn.style.display = 'none';
					if (errEl) errEl.hidden = true;
					var thanks = form.querySelector('.valt-survey-inline-thanks');
					if (thanks) { thanks.hidden = false; thanks.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
					track('survey_completed', { trigger: 'inline', nps: payload.nps_score });
				} else {
					var code = res.body && res.body.error;
					showError(code === 'rate_limited' ? 'Thanks — looks like you already sent feedback recently.' : 'Something went wrong. Please try again.');
					if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send feedback'; }
				}
			}).catch(function () {
				showError('Network error. Please try again.');
				if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send feedback'; }
			});
		});

		track('survey_shown', { trigger: 'inline' });
	}

	function init() {
		// A dedicated /feedback page renders the survey inline; wire it and never
		// also pop the modal — a visitor should not be asked twice.
		var inlineForm = document.querySelector('.valt-survey-inline');
		if (inlineForm) { initInlineForm(inlineForm); return; }

		modal = document.getElementById('valt-survey-modal');
		if (!modal) return;

		var closeBtn = modal.querySelector('.valt-survey-close');
		if (closeBtn) closeBtn.addEventListener('click', hideSurvey);

		modal.addEventListener('click', function (e) { if (e.target === modal) hideSurvey(); });
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && modal.style.display !== 'none') hideSurvey();
		});

		var npsButtons = modal.querySelectorAll('.valt-nps-btn');
		npsButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				npsButtons.forEach(function (b) { b.classList.remove('active'); });
				this.classList.add('active');
				surveyData.nps_score = parseInt(this.getAttribute('data-score'), 10);
				setTimeout(function () { goToStep(2); }, 300);
			});
		});

		var starButtons = modal.querySelectorAll('.valt-star-btn');
		starButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var rating = parseInt(this.getAttribute('data-rating'), 10);
				surveyData.collect_ease = rating;
				starButtons.forEach(function (s) {
					s.classList.toggle('active', parseInt(s.getAttribute('data-rating'), 10) <= rating);
				});
				setTimeout(function () { goToStep(4); }, 300);
			});
		});

		modal.querySelectorAll('.valt-survey-next').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var step = this.closest('.valt-survey-step');
				var stepNum = parseInt(step.getAttribute('data-step'), 10);
				if (stepNum === 2) {
					surveyData.use_case = [];
					step.querySelectorAll('input[name="use_case"]:checked').forEach(function (cb) {
						surveyData.use_case.push(cb.value);
					});
				}
				if (stepNum === 4) {
					var ta = step.querySelector('textarea[name="feature_request"]');
					if (ta) surveyData.feature_request = ta.value;
				}
				goToStep(stepNum + 1);
			});
		});

		var submitBtn = modal.querySelector('.valt-survey-submit');
		if (submitBtn) {
			submitBtn.addEventListener('click', function () {
				var step = this.closest('.valt-survey-step');
				var sel = step.querySelector('input[name="how_found_us"]:checked');
				if (sel) surveyData.how_found_us = sel.value;
				submitSurvey();
			});
		}

		var dontShow = modal.querySelector('#valt-survey-dont-show');
		if (dontShow) {
			dontShow.addEventListener('change', function () { if (this.checked) markDismissed(); });
		}

		evaluateTriggers();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// A successful mint can dispatch this to trigger the post-mint survey.
	window.addEventListener('valt:minted', function () {
		if (config.post_mint && !isDismissed()) showSurvey('post_mint');
	});

})();
