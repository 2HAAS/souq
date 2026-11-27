(function () {
	'use strict';

	function hideDuplicate(sequence) {
		sequence.setAttribute('aria-hidden', 'true');
		sequence.querySelectorAll('a').forEach(function (link) {
			link.setAttribute('tabindex', '-1');
		});
	}

	function initializeMarquee(viewport) {
		var track = viewport.querySelector('.souq-announcement__track');
		var source = track && track.querySelector('.souq-announcement__sequence');
		var sourceGroup = source && source.querySelector('.souq-announcement__group');

		if (!track || !sourceGroup) {
			return;
		}

		var speed = Math.max(15, parseInt(viewport.getAttribute('data-speed'), 10) || 40);
		var rebuildTimer;

		function build() {
			track.classList.remove('is-animated');
			track.replaceChildren();

			var sequence = document.createElement('div');
			sequence.className = 'souq-announcement__sequence';
			var firstGroup = sourceGroup.cloneNode(true);
			sequence.appendChild(firstGroup);
			track.appendChild(sequence);

			if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				return;
			}

			while (sequence.scrollWidth < viewport.clientWidth) {
				var filler = sourceGroup.cloneNode(true);
				hideDuplicate(filler);
				sequence.appendChild(filler);
			}

			var secondSequence = sequence.cloneNode(true);
			hideDuplicate(secondSequence);
			track.appendChild(secondSequence);

			var duration = sequence.scrollWidth / speed;
			if (duration > 0) {
				viewport.style.setProperty('--souq-announcement-duration', duration + 's');
				track.classList.add('is-animated');
			}
		}

		build();
		window.addEventListener('resize', function () {
			window.clearTimeout(rebuildTimer);
			rebuildTimer = window.setTimeout(build, 120);
		});
	}

	document.querySelectorAll('[data-souq-announcement-marquee]').forEach(initializeMarquee);
})();
