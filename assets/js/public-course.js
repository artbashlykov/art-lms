(function () {
	'use strict';

	function parseLessonHash() {
		var hash = (window.location.hash || '').replace(/^#/, '');

		if (!hash || hash === 'toc') {
			return { view: 'toc', index: 0 };
		}

		var match = /^lesson-(\d+)$/i.exec(hash);

		if (!match) {
			return { view: 'toc', index: 0 };
		}

		return {
			view: 'lesson',
			index: Math.max(0, parseInt(match[1], 10) - 1),
		};
	}

	function setHash(view, index) {
		var next = view === 'lesson' ? '#lesson-' + (index + 1) : '#toc';

		if (window.location.hash === next) {
			return;
		}

		if (window.history && window.history.replaceState) {
			window.history.replaceState(null, '', next);
		} else {
			window.location.hash = next;
		}
	}

	function initCourse(root) {
		var total = parseInt(root.getAttribute('data-total'), 10) || 0;
		var tocScreen = root.querySelector('[data-screen="toc"]');
		var lessonScreens = Array.prototype.slice.call(
			root.querySelectorAll('[data-screen="lesson"]')
		);

		if (!tocScreen || !lessonScreens.length || total < 1) {
			return;
		}

		function clampIndex(index) {
			if (index < 0) {
				return 0;
			}

			if (index > total - 1) {
				return total - 1;
			}

			return index;
		}

		function showToc() {
			root.setAttribute('data-view', 'toc');
			root.setAttribute('data-lesson', '0');
			tocScreen.hidden = false;

			lessonScreens.forEach(function (screen) {
				screen.hidden = true;
			});

			setHash('toc', 0);
			tocScreen.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		function refreshEmbeds(container) {
			if (!container) {
				return;
			}

			Array.prototype.forEach.call(container.querySelectorAll('iframe'), function (iframe) {
				var src = iframe.getAttribute('src');

				if (src) {
					iframe.setAttribute('src', src);
				}
			});
		}

		function showLesson(index) {
			index = clampIndex(index);
			root.setAttribute('data-view', 'lesson');
			root.setAttribute('data-lesson', String(index));
			tocScreen.hidden = true;

			lessonScreens.forEach(function (screen) {
				var screenIndex = parseInt(screen.getAttribute('data-lesson-index'), 10);
				screen.hidden = screenIndex !== index;
			});

			setHash('lesson', index);

			var active = root.querySelector(
				'[data-screen="lesson"][data-lesson-index="' + index + '"]'
			);

			if (active) {
				refreshEmbeds(active);
				active.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		function onAction(action, currentIndex) {
			if (action === 'toc') {
				showToc();
				return;
			}

			if (action === 'prev') {
				if (currentIndex <= 0) {
					showToc();
					return;
				}

				showLesson(currentIndex - 1);
				return;
			}

			if (action === 'next') {
				if (currentIndex >= total - 1) {
					return;
				}

				showLesson(currentIndex + 1);
			}
		}

		root.addEventListener('click', function (event) {
			var openBtn = event.target.closest('[data-art-lms-open-lesson]');

			if (openBtn && root.contains(openBtn)) {
				event.preventDefault();
				showLesson(parseInt(openBtn.getAttribute('data-art-lms-open-lesson'), 10) || 0);
				return;
			}

			var actionBtn = event.target.closest('[data-art-lms-course-action]');

			if (!actionBtn || !root.contains(actionBtn)) {
				return;
			}

			event.preventDefault();

			var lessonScreen = actionBtn.closest('[data-screen="lesson"]');
			var currentIndex = lessonScreen
				? parseInt(lessonScreen.getAttribute('data-lesson-index'), 10) || 0
				: 0;

			onAction(actionBtn.getAttribute('data-art-lms-course-action'), currentIndex);
		});

		window.addEventListener('hashchange', function () {
			var state = parseLessonHash();

			if (state.view === 'lesson') {
				showLesson(state.index);
			} else {
				showToc();
			}
		});

		var initial = parseLessonHash();

		if (initial.view === 'lesson') {
			showLesson(initial.index);
		} else {
			showToc();
		}
	}

	function boot() {
		var courses = document.querySelectorAll('[data-art-lms-course="1"]');

		Array.prototype.forEach.call(courses, initCourse);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
