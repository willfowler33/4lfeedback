/* global FourLFeedback */
(function () {
	'use strict';

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function initForm(form) {
		var quadEls = form.querySelectorAll('.fourl-quad');
		var state = { loved: [], loathed: [], longed: [], learned: [] };
		var idCounter = 0;
		var messageEl = form.querySelector('[data-message]');
		var submitBtn = form.querySelector('.fourl-submit-btn');

		quadEls.forEach(function (quadEl) {
			var key = quadEl.getAttribute('data-key');
			if (!state[key]) state[key] = [];
			var input = quadEl.querySelector('[data-add-input]');
			var addBtn = quadEl.querySelector('[data-add-btn]');

			function add() {
				var val = input.value.trim();
				if (!val) return;
				state[key].push({ id: ++idCounter, text: val, starred: false });
				input.value = '';
				render(quadEl, key);
			}

			addBtn.addEventListener('click', add);
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					add();
				}
			});
		});

		function render(quadEl, key) {
			var itemsEl = quadEl.querySelector('[data-items]');
			var countEl = quadEl.querySelector('[data-count]');
			var items = state[key];
			countEl.textContent = items.length;
			if (!items.length) {
				itemsEl.innerHTML = '';
				return;
			}
			itemsEl.innerHTML = items
				.map(function (i) {
					return (
						'<li class="' + (i.starred ? 'fourl-starred' : '') + '" data-id="' + i.id + '">' +
						'<button type="button" class="fourl-star-btn" data-star="' + i.id + '" aria-label="Star">' +
						(i.starred ? '★' : '☆') +
						'</button>' +
						'<span class="fourl-item-text">' + escapeHtml(i.text) + '</span>' +
						'<button type="button" class="fourl-del-btn" data-del="' + i.id + '" aria-label="Delete">×</button>' +
						'</li>'
					);
				})
				.join('');
			itemsEl.querySelectorAll('[data-star]').forEach(function (b) {
				b.addEventListener('click', function () {
					var id = parseInt(b.getAttribute('data-star'), 10);
					var item = state[key].find(function (x) { return x.id === id; });
					if (item) item.starred = !item.starred;
					render(quadEl, key);
				});
			});
			itemsEl.querySelectorAll('[data-del]').forEach(function (b) {
				b.addEventListener('click', function () {
					var id = parseInt(b.getAttribute('data-del'), 10);
					state[key] = state[key].filter(function (x) { return x.id !== id; });
					render(quadEl, key);
				});
			});
		}

		function setMessage(text, kind) {
			if (!messageEl) return;
			messageEl.textContent = text || '';
			messageEl.classList.remove('is-success', 'is-error');
			if (kind === 'success') messageEl.classList.add('is-success');
			if (kind === 'error') messageEl.classList.add('is-error');
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();

			// Pull any unsubmitted text in the input boxes into state before sending.
			quadEls.forEach(function (quadEl) {
				var key = quadEl.getAttribute('data-key');
				var input = quadEl.querySelector('[data-add-input]');
				if (input && input.value.trim()) {
					state[key].push({ id: ++idCounter, text: input.value.trim(), starred: false });
					input.value = '';
					render(quadEl, key);
				}
			});

			var totalCount = state.loved.length + state.loathed.length + state.longed.length + state.learned.length;
			if (totalCount === 0) {
				setMessage(FourLFeedback.i18n.empty, 'error');
				return;
			}

			var titleEl = form.querySelector('input[name="title"]');
			var nameEl  = form.querySelector('input[name="submitter_name"]');
			var emailEl = form.querySelector('input[name="submitter_email"]');
			var hpEl    = form.querySelector('input[name="fourl_hp"]');

			var payload = new FormData();
			payload.append('action', 'fourl_feedback_submit');
			payload.append('nonce', FourLFeedback.nonce);
			payload.append('title', titleEl ? titleEl.value : '');
			payload.append('submitter_name', nameEl ? nameEl.value : '');
			payload.append('submitter_email', emailEl ? emailEl.value : '');
			payload.append('fourl_hp', hpEl ? hpEl.value : '');
			payload.append('items', JSON.stringify(state));

			submitBtn.disabled = true;
			setMessage(FourLFeedback.i18n.submitting);

			fetch(FourLFeedback.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: payload,
			})
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					submitBtn.disabled = false;
					if (resp && resp.success) {
						setMessage((resp.data && resp.data.message) || FourLFeedback.i18n.thanks, 'success');
						form.reset();
						state.loved = []; state.loathed = []; state.longed = []; state.learned = [];
						quadEls.forEach(function (quadEl) {
							render(quadEl, quadEl.getAttribute('data-key'));
						});
					} else {
						var msg = (resp && resp.data && resp.data.message) ? resp.data.message : FourLFeedback.i18n.error;
						setMessage(msg, 'error');
					}
				})
				.catch(function () {
					submitBtn.disabled = false;
					setMessage(FourLFeedback.i18n.error, 'error');
				});
		});
	}

	function init() {
		document.querySelectorAll('.fourl-feedback-form').forEach(initForm);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
