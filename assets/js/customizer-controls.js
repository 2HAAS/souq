(function () {
	'use strict';

	function createField(labelText, type, value, onChange) {
		var label = document.createElement('label');
		label.className = 'souq-announcement-field';

		var title = document.createElement('span');
		title.textContent = labelText;
		label.appendChild(title);

		var input = document.createElement('input');
		input.type = type;
		input.value = value || '';
		input.addEventListener('change', onChange);
		label.appendChild(input);

		return label;
	}

	function initializeControl(control) {
		if (control.getAttribute('data-initialized') === 'true') {
			return;
		}

		var settingId = control.getAttribute('data-setting-id');
		var setting = window.wp && wp.customize ? wp.customize(settingId) : null;
		var wrapper = control.closest('.customize-control');
		var addButton = wrapper && wrapper.querySelector('.souq-announcement-add');
		var strings = window.souqAnnouncementControlStrings || {};
		var initialValue;

		if (!addButton) {
			console.error('Souq announcement Add button is missing from its Customizer control.');
			return;
		}

		try {
			initialValue = JSON.parse(control.getAttribute('data-value') || '[]');
		} catch (error) {
			console.error('Souq announcement settings could not be read.', error);
			return;
		}

		if (setting && Array.isArray(setting.get())) {
			initialValue = setting.get();
		} else if (!setting) {
			console.error('Souq announcement Customizer setting is unavailable:', settingId);
			return;
		}

		control.setAttribute('data-initialized', 'true');

		function rowsValue() {
			return Array.prototype.map.call(control.querySelectorAll('.souq-announcement-row'), function (row) {
				return {
					text: row.querySelector('[data-field="text"]').value.trim(),
					url: row.querySelector('[data-field="url"]').value.trim()
				};
			});
		}

		function commit() {
			if (setting) {
				setting.set(rowsValue());
			}
		}

		function moveRow(row, direction) {
			var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
			if (!sibling) {
				return;
			}
			if (direction < 0) {
				control.insertBefore(row, sibling);
			} else {
				control.insertBefore(sibling, row);
			}
			commit();
		}

		function addRow(entry) {
			var row = document.createElement('div');
			row.className = 'souq-announcement-row';

			var textField = createField(strings.message || 'Message', 'text', entry.text, commit);
			textField.querySelector('input').setAttribute('data-field', 'text');
			var urlField = createField(strings.link || 'Link (optional)', 'url', entry.url, commit);
			urlField.querySelector('input').setAttribute('data-field', 'url');
			row.appendChild(textField);
			row.appendChild(urlField);

			var actions = document.createElement('div');
			actions.className = 'souq-announcement-actions';
			[
				{ label: strings.move_up || 'Move up', symbol: '\u2191', direction: -1 },
				{ label: strings.move_down || 'Move down', symbol: '\u2193', direction: 1 }
			].forEach(function (action) {
				var button = document.createElement('button');
				button.type = 'button';
				button.className = 'button button-small';
				button.setAttribute('aria-label', action.label);
				button.textContent = action.symbol;
				button.addEventListener('click', function () {
					moveRow(row, action.direction);
				});
				actions.appendChild(button);
			});

			var remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'button button-small';
			remove.textContent = strings.remove || 'Remove';
			remove.addEventListener('click', function () {
				row.remove();
				commit();
				updateAddButton();
			});
			actions.appendChild(remove);
			row.appendChild(actions);
			control.appendChild(row);
		}

		function updateAddButton() {
			if (addButton) {
				addButton.disabled = control.querySelectorAll('.souq-announcement-row').length >= 10;
				addButton.title = addButton.disabled ? (strings.limit_reached || '') : '';
			}
		}

		initialValue.slice(0, 10).forEach(function (entry) {
			if (entry && typeof entry === 'object') {
				addRow({
					text: typeof entry.text === 'string' ? entry.text : '',
					url: typeof entry.url === 'string' ? entry.url : ''
				});
			}
		});

		if (addButton) {
			addButton.addEventListener('click', function () {
				if (control.querySelectorAll('.souq-announcement-row').length >= 10) {
					return;
				}
				addRow({ text: '', url: '' });
				commit();
				updateAddButton();
				control.querySelector('.souq-announcement-row:last-child input').focus();
			});
			updateAddButton();
		}
	}

	function initializeControls(root) {
		if (root.matches && root.matches('.souq-announcement-repeater')) {
			initializeControl(root);
		}
		if (root.querySelectorAll) {
			root.querySelectorAll('.souq-announcement-repeater').forEach(initializeControl);
		}
	}

	if (window.wp && wp.customize) {
		initializeControls(document);
		wp.customize.bind('ready', function () {
			initializeControls(document);
		});

		if (window.MutationObserver && document.body) {
			var observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					mutation.addedNodes.forEach(function (node) {
						if (node.nodeType === 1) {
							initializeControls(node);
						}
					});
				});
			});
			observer.observe(document.body, { childList: true, subtree: true });
		}
	}
})();
