(function () {
	'use strict';

	function getOrderKeyFromUrl() {
		return new URLSearchParams(window.location.search).get('art_lms_order');
	}

	function getInitialOrderContext() {
		var config = window.artLmsPaymentStatus;

		if (!config || !config.initialOrder || typeof config.initialOrder !== 'object') {
			return {};
		}

		return config.initialOrder;
	}

	function mergeDisplayData(pollData) {
		var merged = {};
		var initial = getInitialOrderContext();
		var poll = pollData || {};

		Object.keys(initial).forEach(function (key) {
			merged[key] = initial[key];
		});

		Object.keys(poll).forEach(function (key) {
			merged[key] = poll[key];
		});

		if (!merged.support_email && window.artLmsPaymentStatus && window.artLmsPaymentStatus.supportEmail) {
			merged.support_email = window.artLmsPaymentStatus.supportEmail;
		}

		return merged;
	}

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function splitParagraphs(text) {
		return String(text || '')
			.split(/\n\s*\n/)
			.map(function (paragraph) {
				return paragraph.trim();
			})
			.filter(function (paragraph) {
				return paragraph !== '';
			});
	}

	function applyDescriptionPlaceholders(paragraph, data) {
		var text = String(paragraph || '');
		var email = data.email || '';
		var supportEmail = data.support_email || window.artLmsPaymentStatus.supportEmail || '';

		if (text.indexOf('{email}') !== -1) {
			text = text.replace(
				/\{email\}/g,
				email ? '<strong>' + escapeHtml(email) + '</strong>' : ''
			);
		}

		if (text.indexOf('{support}') !== -1) {
			text = text.replace(
				/\{support\}/g,
				supportEmail
					? '<a href="mailto:' + escapeHtml(supportEmail) + '">' + escapeHtml(supportEmail) + '</a>'
					: ''
			);
		}

		text = text
			.replace(/\{order\}/g, escapeHtml(String(data.order_id || '—')))
			.replace(/\{product\}/g, escapeHtml(data.product_name || '—'))
			.replace(/\{amount\}/g, escapeHtml(data.amount || '—'));

		// Legacy placeholders from older saved texts.
		if (text.indexOf('%s') !== -1) {
			if (email && text.toLowerCase().indexOf('почт') !== -1) {
				text = text.replace('%s', '<strong>' + escapeHtml(email) + '</strong>');
			} else if (supportEmail) {
				text = text.replace(
					'%s',
					'<a href="mailto:' + escapeHtml(supportEmail) + '">' + escapeHtml(supportEmail) + '</a>'
				);
			}
		}

		text = text
			.replace(/%1\$s/g, escapeHtml(String(data.order_id || '—')))
			.replace(/%2\$s/g, escapeHtml(data.product_name || '—'))
			.replace(/%3\$s/g, escapeHtml(data.amount || '—'));

		return text;
	}

	function shouldHideEmailParagraph(paragraph, data) {
		if (data.purchase_email_enabled) {
			return false;
		}

		return paragraph.indexOf('{email}') !== -1 || paragraph.indexOf('%s') !== -1;
	}

	function renderDescriptionHtml(description, data, options) {
		options = options || {};
		var paragraphs = splitParagraphs(description);
		var html = '';

		paragraphs.forEach(function (paragraph) {
			if (options.hideEmailParagraphs && shouldHideEmailParagraph(paragraph, data)) {
				return;
			}

			var content = applyDescriptionPlaceholders(paragraph, data).trim();

			if (!content) {
				return;
			}

			html += '<p class="art-lms-payment-status__text">' + content + '</p>';
		});

		return html;
	}

	function buildActions(buttons) {
		if (!buttons.length) {
			return '';
		}

		var html = '<div class="art-lms-payment-status__actions">';

		buttons.forEach(function (button) {
			html += '<a class="art-lms-button" href="' + escapeHtml(button.url) + '">' + escapeHtml(button.label) + '</a>';
		});

		html += '</div>';
		return html;
	}

	function renderHtml(container, state, html) {
		container.className = 'art-lms-payment-status is-' + state;
		container.innerHTML = html;
	}

	function renderPending(container, data, strings) {
		var html = '';

		html += '<div class="art-lms-payment-status__spinner" aria-hidden="true"></div>';
		html += '<p class="art-lms-payment-status__title">' + escapeHtml(strings.pendingTitle) + '</p>';
		html += renderDescriptionHtml(strings.pendingDescription, data, {
			hideEmailParagraphs: !data.purchase_email_enabled,
		});

		renderHtml(container, 'pending', html);
	}

	function renderPaid(container, data, strings) {
		var html = '';
		var buttons = [];

		html += '<p class="art-lms-payment-status__title">' + escapeHtml(strings.paidTitle) + '</p>';
		html += renderDescriptionHtml(strings.paidDescription, data, {
			hideEmailParagraphs: !data.purchase_email_enabled,
		});

		if (data.account_url && strings.paidShowAccountButton) {
			buttons.push({
				url: data.account_url,
				label: strings.accountButton,
			});
		}

		html += buildActions(buttons);
		renderHtml(container, 'paid', html);
	}

	function renderFailed(container, data, strings) {
		var html = '';
		var buttons = [];

		html += '<p class="art-lms-payment-status__title">' + escapeHtml(strings.failedTitle) + '</p>';
		html += renderDescriptionHtml(strings.failedDescription, data);

		if (data.checkout_url) {
			buttons.push({
				url: data.checkout_url,
				label: strings.retryButton,
			});
		}

		html += buildActions(buttons);
		renderHtml(container, 'error', html);
	}

	function renderTimeout(container, data, strings) {
		var html = '';
		var buttons = [];

		html += '<p class="art-lms-payment-status__title">' + escapeHtml(strings.timeoutTitle) + '</p>';
		html += renderDescriptionHtml(strings.timeoutDescription, data);

		if (data.account_url) {
			buttons.push({
				url: data.account_url,
				label: strings.accountButton,
			});
		}

		html += buildActions(buttons);
		renderHtml(container, 'timeout', html);
	}

	function renderNotFound(container, strings) {
		var html = '';

		html += '<p class="art-lms-payment-status__title">' + escapeHtml(strings.notFoundTitle) + '</p>';
		html += renderDescriptionHtml(strings.notFoundDescription, {});

		renderHtml(container, 'error', html);
	}

	function renderMissingOrder(container, strings) {
		var html = '';
		var accountUrl = window.artLmsPaymentStatus.accountUrl || '';
		var buttons = [];
		var data = {
			support_email: window.artLmsPaymentStatus.supportEmail || '',
		};

		html += '<p class="art-lms-payment-status__title">' + escapeHtml(strings.missingOrderTitle) + '</p>';
		html += renderDescriptionHtml(strings.missingOrderDescription, data);

		if (accountUrl) {
			buttons.push({
				url: accountUrl,
				label: strings.accountButton,
			});
		}

		html += buildActions(buttons);
		renderHtml(container, 'error', html);
	}

	function pollOrderStatus(orderKey) {
		var container = document.getElementById('art-lms-payment-status');
		var config = window.artLmsPaymentStatus;

		if (!container || !config || !config.strings) {
			return;
		}

		if (!orderKey || container.getAttribute('data-missing-order') === '1') {
			renderMissingOrder(container, config.strings);
			return;
		}

		var strings = config.strings;
		var restUrl = config.restUrl + encodeURIComponent(orderKey);
		var pollInterval = config.pollInterval || 8000;
		var timeoutMs = config.timeoutMs || 600000;
		var startedAt = Date.now();
		var lastData = null;

		function scheduleNextCheck() {
			window.setTimeout(checkStatus, pollInterval);
		}

		function checkStatus() {
			if (Date.now() - startedAt >= timeoutMs) {
				renderTimeout(container, mergeDisplayData(lastData || {}), strings);
				return;
			}

			fetch(restUrl)
				.then(function (response) {
					if (response.status === 404) {
						renderNotFound(container, strings);
						return null;
					}

					return response.json();
				})
				.then(function (data) {
					if (!data) {
						return;
					}

					lastData = mergeDisplayData(data);

					if (data.paid) {
						renderPaid(container, lastData, strings);
						return;
					}

					if (data.failed) {
						renderFailed(container, lastData, strings);
						return;
					}

					renderPending(container, lastData, strings);
					scheduleNextCheck();
				})
				.catch(function () {
					renderPending(container, mergeDisplayData(lastData || { purchase_email_enabled: true }), strings);
					scheduleNextCheck();
				});
		}

		checkStatus();
	}

	document.addEventListener('DOMContentLoaded', function () {
		pollOrderStatus(getOrderKeyFromUrl());
	});
})();
