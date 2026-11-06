document.addEventListener('DOMContentLoaded', function () {
	const list = document.querySelector('[data-wa-snippet-list]');
	const template = document.querySelector('[data-wa-snippet-template]');
	const addButton = document.querySelector('[data-wa-snippet-add]');

	if (!list || !template || !addButton || !window.waSuitCodeSnippets) {
		return;
	}

	const updateLineNumbers = function (editor) {
		const input = editor.querySelector('[data-wa-code-input]');
		const lines = editor.querySelector('[data-wa-code-lines]');
		if (!input || !lines) {
			return;
		}

		const lineCount = input.value.split('\n').length;
		lines.textContent = Array.from({ length: lineCount }, function (_, index) {
			return index + 1;
		}).join('\n');
		lines.scrollTop = input.scrollTop;
	};

	const updateSnippet = function (snippet) {
		const targets = Array.from(snippet.querySelectorAll('[data-wa-display-target]'));
		const url = snippet.querySelector('[data-wa-snippet-url]');
		const language = snippet.querySelector('[data-wa-snippet-language]');
		const location = snippet.querySelector('[data-wa-snippet-location]');
		const locationWrap = snippet.querySelector('[data-wa-snippet-location-wrap]');
		const shortcodeOnly = snippet.querySelector('[data-wa-shortcode-only]');
		const generatedShortcode = snippet.querySelector('[data-wa-generated-shortcode]');
		const phpNote = snippet.querySelector('[data-wa-snippet-php-note]');
		const shortcodeNote = snippet.querySelector('[data-wa-snippet-shortcode-note]');
		const phpDeviceNote = snippet.querySelector('[data-wa-snippet-php-device-note]');
		const titleInput = snippet.querySelector('[data-wa-snippet-title-input]');
		const title = snippet.querySelector('[data-wa-snippet-title]');
		const summary = snippet.querySelector('[data-wa-snippet-summary]');
		const urlValues = Array.from(snippet.querySelectorAll('[data-wa-url-value]'))
			.map(function (input) { return input.value.trim(); })
			.filter(Boolean);
		let selectedTargets = targets.filter(function (target) { return target.checked; });
		if (!selectedTargets.length && (!shortcodeOnly || !shortcodeOnly.checked)) {
			const allTarget = targets.find(function (target) { return target.value === 'all'; });
			if (allTarget) {
				allTarget.checked = true;
				selectedTargets = [allTarget];
			}
		}
		const devices = Array.from(snippet.querySelectorAll('[data-wa-snippet-device]'));
		const allDevices = devices.find(function (device) {
			return device.value === 'all';
		});
		const targetsFieldset = snippet.querySelector('[data-wa-snippet-targets]');
		const shortcodeMode = Boolean(shortcodeOnly && shortcodeOnly.checked);
		const isPhp = language && language.value === 'php';
		const isHtmlInHead = !shortcodeMode && language && language.value === 'html' && location && location.value === 'head';
		const isShortcode = language && language.value === 'shortcode';
		const headOption = location && location.querySelector('option[value="head"]');

		if (targetsFieldset) {
			targetsFieldset.hidden = shortcodeMode;
		}
		if (locationWrap) {
			locationWrap.hidden = shortcodeMode;
		}
		if (generatedShortcode) {
			generatedShortcode.hidden = !shortcodeMode;
		}
		if (headOption) {
			headOption.disabled = Boolean(isShortcode);
		}
		if (isShortcode && location && location.value === 'head') {
			location.value = 'body_open';
		}

		if (url) {
			url.hidden = shortcodeMode || !selectedTargets.some(function (target) { return target.value === 'url_contains'; });
		}

		if (phpNote) {
			phpNote.hidden = !isPhp;
		}
		if (shortcodeNote) {
			shortcodeNote.hidden = !isShortcode;
		}

		if ((isPhp || isHtmlInHead) && allDevices) {
			allDevices.checked = true;
			devices.forEach(function (device) {
				if (device !== allDevices) {
					device.checked = false;
				}
			});
		}

		devices.forEach(function (device) {
			device.disabled = (isPhp || isHtmlInHead) && device !== allDevices;
		});

		if (phpDeviceNote) {
			phpDeviceNote.hidden = !isPhp && !isHtmlInHead;
			phpDeviceNote.textContent = isPhp
				? 'PHP executa no servidor e não pode ser limitado por dispositivo.'
				: 'HTML no head não pode ser ocultado por dispositivo. Escolha body ou footer para usar esse filtro.';
		}

		if (titleInput && title) {
			title.textContent = titleInput.value.trim() || 'Novo código';
			const toggle = snippet.querySelector('[data-wa-snippet-enabled]');
			if (toggle) {
				toggle.setAttribute('aria-label', 'Ativar ' + (titleInput.value.trim() || 'Novo código'));
			}
		}

		if (summary) {
			const targetLabels = selectedTargets.map(function (target) {
				return target.parentElement.textContent.trim();
			});
			let targetDetail = targetLabels.join(', ') || 'Selecione onde exibir';
			if (selectedTargets.some(function (target) { return target.value === 'url_contains'; })) {
				targetDetail += ': ' + (urlValues.length ? urlValues.join(', ') : '...');
			}
			if (shortcodeOnly && shortcodeOnly.checked) {
				targetDetail = 'Somente shortcode';
			}
			const selectedDevices = devices
				.filter(function (device) { return device.checked; })
				.map(function (device) { return device.parentElement.textContent.trim(); });

			summary.textContent = targetDetail + ' · ' + (selectedDevices.join(', ') || 'Selecione dispositivo');
		}
	};

	const bindSnippet = function (snippet, collapse) {
		const targets = Array.from(snippet.querySelectorAll('[data-wa-display-target]'));
		const language = snippet.querySelector('[data-wa-snippet-language]');
		const location = snippet.querySelector('[data-wa-snippet-location]');
		const titleInput = snippet.querySelector('[data-wa-snippet-title-input]');
		const shortcodeOnly = snippet.querySelector('[data-wa-shortcode-only]');
		const devices = Array.from(snippet.querySelectorAll('[data-wa-snippet-device]'));
		const toggle = snippet.querySelector('[data-wa-snippet-enabled]');
		const toggleLabel = snippet.querySelector('[data-wa-snippet-enabled-label]');
		const codeEditor = snippet.querySelector('[data-wa-code-editor]');
		const codeInput = codeEditor && codeEditor.querySelector('[data-wa-code-input]');
		const removeButton = snippet.querySelector('[data-wa-snippet-remove]');
		const duplicateButton = snippet.querySelector('.wa-code-snippet__duplicate');
		const urlFields = snippet.querySelector('[data-wa-url-fields]');
		const addUrlButton = snippet.querySelector('[data-wa-url-add]');

		if (duplicateButton) {
			duplicateButton.addEventListener('click', function (event) {
				event.stopPropagation();
			});
		}

		[language, location, titleInput, shortcodeOnly].forEach(function (control) {
			if (control) {
				control.addEventListener('input', function () { updateSnippet(snippet); });
				control.addEventListener('change', function () { updateSnippet(snippet); });
			}
		});
		targets.forEach(function (target) {
			target.addEventListener('change', function () {
				const allTarget = targets.find(function (item) { return item.value === 'all'; });
				if (target.value === 'all' && target.checked) {
					targets.forEach(function (item) {
						if (item !== target) {
							item.checked = false;
						}
					});
				} else if (target.checked && allTarget) {
					allTarget.checked = false;
				}
				if (!targets.some(function (item) { return item.checked; }) && allTarget) {
					allTarget.checked = true;
				}
				updateSnippet(snippet);
			});
		});

		if (urlFields) {
			urlFields.addEventListener('input', function () {
				updateSnippet(snippet);
			});
			urlFields.addEventListener('click', function (event) {
				const eventTarget = event.target instanceof Element ? event.target : null;
				const removeUrlButton = eventTarget && eventTarget.closest('[data-wa-url-remove]');
				if (!removeUrlButton) {
					return;
				}

				event.preventDefault();
				removeUrlButton.closest('[data-wa-url-row]').remove();
				if (!urlFields.querySelector('[data-wa-url-row]')) {
					urlFields.appendChild(createUrlRow(urlFields.dataset.waUrlName));
				}
				updateSnippet(snippet);
			});
		}

		if (addUrlButton && urlFields) {
			addUrlButton.addEventListener('click', function () {
				const row = createUrlRow(urlFields.dataset.waUrlName);
				urlFields.appendChild(row);
				row.querySelector('[data-wa-url-value]').focus();
			});
		}

		devices.forEach(function (device) {
			device.addEventListener('change', function () {
				const allDevices = devices.find(function (item) { return item.value === 'all'; });

				if (device.value === 'all' && device.checked) {
					devices.forEach(function (item) {
						if (item !== device) {
							item.checked = false;
						}
					});
				} else if (device.value !== 'all' && device.checked && allDevices) {
					allDevices.checked = false;
				}

				if (!devices.some(function (item) { return item.checked; }) && allDevices) {
					allDevices.checked = true;
				}
				updateSnippet(snippet);
			});
		});

		if (codeEditor && codeInput) {
			updateLineNumbers(codeEditor);
			codeInput.addEventListener('input', function () { updateLineNumbers(codeEditor); });
			codeInput.addEventListener('scroll', function () { updateLineNumbers(codeEditor); });

			const codeShell = codeEditor.closest('[data-wa-code-shell]');
			if (codeShell) {
				codeShell.querySelectorAll('[data-wa-code-theme]').forEach(function (button) {
					button.addEventListener('click', function () {
						const theme = button.dataset.waCodeTheme === 'dark' ? 'dark' : 'light';
						codeShell.dataset.theme = theme;
						codeShell.querySelectorAll('[data-wa-code-theme]').forEach(function (themeButton) {
							themeButton.setAttribute('aria-pressed', String(themeButton === button));
						});
					});
				});
			}
		}

		if (toggle) {
			toggle.addEventListener('click', function (event) {
				event.stopPropagation();
			});
			toggle.addEventListener('change', function () {
				const previousState = !toggle.checked;
				if (toggle.dataset.snippetPersisted === '0') {
					if (toggleLabel) {
						toggleLabel.textContent = toggle.checked ? 'Ativo' : 'Inativo';
					}
					return;
				}

				toggle.disabled = true;
				toggle.setAttribute('aria-busy', 'true');
				if (toggleLabel) {
					toggleLabel.textContent = waSuitCodeSnippets.strings.saving;
				}

				const body = new URLSearchParams({
					action: 'wa_suit_toggle_code_snippet',
					nonce: waSuitCodeSnippets.nonce,
					snippet_id: toggle.dataset.snippetId || '',
					enabled: toggle.checked ? '1' : '0'
				});

				fetch(waSuitCodeSnippets.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				})
					.then(function (response) {
						return response.json().then(function (data) {
							if (!response.ok || !data.success) {
								throw new Error(data.data && data.data.message ? data.data.message : waSuitCodeSnippets.strings.error);
							}
							return data;
						});
					})
					.then(function (data) {
						toggle.checked = Boolean(data.data.enabled);
						if (toggleLabel) {
							toggleLabel.textContent = data.data.enabled ? 'Ativo' : 'Inativo';
						}
						toggle.title = data.data.message;
					})
					.catch(function (error) {
						toggle.checked = previousState;
						if (toggleLabel) {
							toggleLabel.textContent = previousState ? 'Ativo' : 'Inativo';
						}
						toggle.title = error.message;
						window.alert(error.message);
					})
					.finally(function () {
						toggle.disabled = false;
						toggle.removeAttribute('aria-busy');
					});
			});
		}

		if (removeButton) {
			removeButton.addEventListener('click', function (event) {
				event.preventDefault();
				snippet.remove();
			});
		}

		updateSnippet(snippet);
		if (collapse) {
			snippet.open = false;
		}
	};

	const createUrlRow = function (name) {
		const row = document.createElement('div');
		row.className = 'wa-code-snippet__url-row';
		row.setAttribute('data-wa-url-row', '');

		const input = document.createElement('input');
		input.type = 'text';
		input.name = name;
		input.placeholder = waSuitCodeSnippets.strings.urlPlaceholder;
		input.setAttribute('data-wa-url-value', '');
		input.setAttribute('aria-label', waSuitCodeSnippets.strings.urlMatch);

		const removeButton = document.createElement('button');
		removeButton.type = 'button';
		removeButton.className = 'wa-code-snippet__url-remove';
		removeButton.setAttribute('data-wa-url-remove', '');
		removeButton.setAttribute('aria-label', waSuitCodeSnippets.strings.removeUrl);

		const icon = document.createElement('span');
		icon.className = 'dashicons dashicons-no-alt';
		icon.setAttribute('aria-hidden', 'true');
		removeButton.appendChild(icon);
		row.append(input, removeButton);

		return row;
	};

	list.querySelectorAll('[data-wa-snippet]').forEach(function (snippet) {
		bindSnippet(snippet, true);
	});

	const form = list.closest('form');
	if (form) {
		form.addEventListener('submit', function (event) {
			const incompleteSnippet = Array.from(list.querySelectorAll('[data-wa-snippet]')).find(function (snippet) {
				const title = snippet.querySelector('[data-wa-snippet-title-input]');
				const code = snippet.querySelector('[data-wa-code-input]');
				return !title || !title.value.trim() || !code || !code.value.trim();
			});

			if (incompleteSnippet) {
				event.preventDefault();
				incompleteSnippet.open = true;
				const missingField = Array.from(incompleteSnippet.querySelectorAll('[data-wa-snippet-title-input], [data-wa-code-input]'))
					.find(function (field) { return !field.value.trim(); });
				if (missingField) {
					missingField.focus();
				}
				window.alert(waSuitCodeSnippets.strings.requiredFields);
				return;
			}

			const missingUrlRule = Array.from(list.querySelectorAll('[data-wa-snippet]')).find(function (snippet) {
				const shortcodeOnly = snippet.querySelector('[data-wa-shortcode-only]');
				if (shortcodeOnly && shortcodeOnly.checked) {
					return false;
				}
				const usesUrlRule = Array.from(snippet.querySelectorAll('[data-wa-display-target]')).some(function (target) {
					return target.checked && target.value === 'url_contains';
				});
				if (!usesUrlRule) {
					return false;
				}

				return !Array.from(snippet.querySelectorAll('[data-wa-url-value]')).some(function (input) {
					return input.value.trim() !== '';
				});
			});

			if (missingUrlRule) {
				event.preventDefault();
				missingUrlRule.open = true;
				const firstUrlInput = missingUrlRule.querySelector('[data-wa-url-value]');
				if (firstUrlInput) {
					firstUrlInput.focus();
				}
				window.alert(waSuitCodeSnippets.strings.urlRequired);
			}
		});
	}

	addButton.addEventListener('click', function () {
		const key = 'new-' + Date.now() + '-' + Math.random().toString(36).slice(2);
		const html = template.innerHTML.split('__SNIPPET_KEY__').join(key);
		const wrapper = document.createElement('div');
		wrapper.innerHTML = html.trim();
		const snippet = wrapper.firstElementChild;

		if (snippet) {
			list.appendChild(snippet);
			bindSnippet(snippet, false);
			snippet.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	});
});
