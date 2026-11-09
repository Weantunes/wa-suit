document.addEventListener('DOMContentLoaded', function () {
	const list = document.querySelector('[data-wa-snippet-list]');
	const template = document.querySelector('[data-wa-snippet-template]');
	const addButton = document.querySelector('[data-wa-snippet-add]');

	if (!list || !template || !addButton || !window.waSuitCodeSnippets) {
		return;
	}

	const languageTabs = Array.from(document.querySelectorAll('[data-wa-language-filter]'));
	const statusTabs = Array.from(document.querySelectorAll('[data-wa-status-filter]'));
	const searchInput = document.querySelector('[data-wa-snippet-search]');
	const locationFilter = document.querySelector('[data-wa-snippet-location-filter]');
	const pageSizeInput = document.querySelector('[data-wa-snippet-page-size]');
	const pagination = document.querySelector('[data-wa-snippet-pagination]');
	const pageLabel = document.querySelector('[data-wa-snippet-page-label]');
	const pageNumbers = document.querySelector('[data-wa-snippet-page-numbers]');
	const emptyState = document.querySelector('[data-wa-snippet-empty]');
	const bulkForm = document.querySelector('#wa-suit-snippets-bulk-form');
	const bulkAction = bulkForm && bulkForm.querySelector('[name="bulk_action"]');
	const selectionToggle = document.querySelector('[data-wa-selection-toggle]');
	const selectedCountLabel = document.querySelector('[data-wa-selected-count]');
	const bulkActions = document.querySelector('[data-wa-bulk-actions]');
	const bulkActionButtons = Array.from(document.querySelectorAll('[data-wa-bulk-action]'));
	const bulkDialog = document.querySelector('[data-wa-bulk-dialog]');
	const bulkDialogTitle = bulkDialog && bulkDialog.querySelector('[data-wa-bulk-dialog-title]');
	const bulkDialogMessage = bulkDialog && bulkDialog.querySelector('[data-wa-bulk-dialog-message]');
	const bulkDialogConfirm = bulkDialog && bulkDialog.querySelector('[data-wa-bulk-dialog-confirm]');
	const bulkDialogCancel = bulkDialog && bulkDialog.querySelector('[data-wa-bulk-dialog-cancel]');
	let selectedLanguage = 'all';
	let selectedStatus = 'all';
	let selectedLocation = 'all';
	let currentPage = 1;
	let selectionMode = false;

	const applySnippetFilters = function () {
		const snippets = Array.from(list.querySelectorAll('[data-wa-snippet]'));
		const query = searchInput ? searchInput.value.trim().toLocaleLowerCase() : '';
		const pageSize = pageSizeInput ? Number(pageSizeInput.value) || 20 : 20;
		const filtered = snippets.filter(function (snippet) {
			const isTrashed = snippet.dataset.waSnippetTrashed === '1';
			const language = snippet.dataset.waSnippetLanguage || 'html';
			const location = snippet.dataset.waSnippetLocation || 'footer';
			const toggle = snippet.querySelector('[data-wa-snippet-enabled]');
			const title = snippet.querySelector('[data-wa-snippet-title-input]');
			const code = snippet.querySelector('[data-wa-code-input]');

			if (selectedLanguage !== 'all' && selectedLanguage !== language) {
				return false;
			}
			if (selectedLocation !== 'all' && selectedLocation !== location) {
				return false;
			}
			if (selectedStatus === 'trash' ? !isTrashed : isTrashed) {
				return false;
			}
			if (selectedStatus === 'active' && (!toggle || !toggle.checked)) {
				return false;
			}
			if (selectedStatus === 'inactive' && toggle && toggle.checked) {
				return false;
			}

			const searchText = [
				snippet.dataset.waSnippetId || '',
				title ? title.value : '',
				code ? code.value : ''
			].join(' ').toLocaleLowerCase();
			return !query || searchText.includes(query);
		});
		const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
		currentPage = Math.min(currentPage, pageCount);
		const firstIndex = (currentPage - 1) * pageSize;
		const visible = new Set(filtered.slice(firstIndex, firstIndex + pageSize));
		const selectedSnippets = snippets.filter(function (snippet) {
			return snippet.classList.contains('is-selected');
		});
		const selected = selectedSnippets.length;

		snippets.forEach(function (snippet) {
			snippet.hidden = !visible.has(snippet);
		});
		if (emptyState) {
			emptyState.hidden = filtered.length > 0;
		}
		if (pagination) {
			pagination.hidden = filtered.length === 0;
		}
		if (pageLabel) {
			const start = filtered.length ? firstIndex + 1 : 0;
			const end = Math.min(firstIndex + pageSize, filtered.length);
			pageLabel.textContent = 'Itens ' + start + '–' + end + ' de ' + filtered.length;
		}
		if (pageNumbers) {
			pageNumbers.replaceChildren();
			if (pageCount > 1) {
				for (let page = 1; page <= pageCount; page++) {
					const pageButton = document.createElement('button');
					pageButton.type = 'button';
					pageButton.className = 'wa-code-snippets__page-number';
					pageButton.textContent = String(page);
					pageButton.setAttribute('aria-label', 'Página ' + page);
					pageButton.setAttribute('aria-current', page === currentPage ? 'page' : 'false');
					pageButton.classList.toggle('is-current', page === currentPage);
					pageButton.addEventListener('click', function () {
						currentPage = page;
						applySnippetFilters();
					});
					pageNumbers.appendChild(pageButton);
				}
			}
		}
		if (selectionToggle) {
			selectionToggle.setAttribute('aria-pressed', String(selectionMode));
			selectionToggle.classList.toggle('is-active', selectionMode);
			selectionToggle.title = selectionMode ? 'Sair da seleção' : 'Selecionar códigos';
			const visibleLabel = selectionToggle.querySelector('[data-wa-selection-label]');
			if (visibleLabel) {
				visibleLabel.textContent = selectionMode ? 'Cancelar' : 'Selecionar';
			}
		}
		if (selectedCountLabel) {
			selectedCountLabel.hidden = selected === 0;
			selectedCountLabel.textContent = selected + (selected === 1 ? ' selecionado' : ' selecionados');
		}
		if (bulkActions) {
			bulkActions.hidden = !selectionMode;
		}
		const selectedTrashItem = selectedSnippets.some(function (snippet) { return snippet.dataset.waSnippetTrashed === '1'; });
		bulkActionButtons.forEach(function (button) {
			const action = button.dataset.waBulkAction;
			let visibleAction = selectionMode;
			if (selectedStatus === 'trash') {
				visibleAction = visibleAction && (action === 'restore' || action === 'delete');
			} else {
				visibleAction = visibleAction && !selectedTrashItem && (
					action === 'trash' ||
					action === 'activate' ||
					action === 'deactivate'
				);
			}
			button.hidden = !visibleAction;
			button.disabled = selected === 0;
		});
		list.closest('.wa-code-snippets').classList.toggle('is-selecting', selectionMode);

		const allSnippets = snippets.filter(function (snippet) {
			return snippet.dataset.waSnippetTrashed !== '1';
		});
		const counts = {
			all: allSnippets.length,
			active: allSnippets.filter(function (snippet) {
				const toggle = snippet.querySelector('[data-wa-snippet-enabled]');
				return toggle && toggle.checked;
			}).length,
			inactive: allSnippets.filter(function (snippet) {
				const toggle = snippet.querySelector('[data-wa-snippet-enabled]');
				return !toggle || !toggle.checked;
			}).length,
			trash: snippets.length - allSnippets.length
		};
		Object.keys(counts).forEach(function (key) {
			const counter = document.querySelector('[data-wa-status-count="' + key + '"]');
			if (counter) {
				counter.textContent = counts[key];
			}
		});
		['all', 'html', 'css', 'js', 'php', 'shortcode'].forEach(function (language) {
			const counter = document.querySelector('[data-wa-language-count="' + language + '"]');
			if (counter) {
				const count = language === 'all'
					? allSnippets.length
					: allSnippets.filter(function (snippet) { return snippet.dataset.waSnippetLanguage === language; }).length;
				counter.textContent = count;
			}
		});
	};

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
		if (language) {
			snippet.dataset.waSnippetLanguage = language.value;
		}
		if (location) {
			snippet.dataset.waSnippetLocation = location.value;
		}
		if (titleInput) {
			snippet.dataset.waSnippetSearch = titleInput.value;
		}
		applySnippetFilters();
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
		const duplicateButton = snippet.querySelector('.wa-code-snippet__duplicate');
		const rowActions = Array.from(snippet.querySelectorAll('[data-wa-snippet-action]'));
		const removeDraftButton = snippet.querySelector('[data-wa-snippet-draft-remove]');
		const urlFields = snippet.querySelector('[data-wa-url-fields]');
		const addUrlButton = snippet.querySelector('[data-wa-url-add]');

		[duplicateButton].concat(rowActions).forEach(function (control) {
			if (control) {
				control.addEventListener('click', function (event) {
					event.stopPropagation();
					if (control.hasAttribute('data-wa-confirm-delete') && !window.confirm('Excluir este código permanentemente? Esta ação não pode ser desfeita.')) {
						event.preventDefault();
					}
				});
			}
		});
		if (removeDraftButton) {
			removeDraftButton.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				snippet.remove();
				applySnippetFilters();
			});
		}
		if (codeInput) {
			codeInput.addEventListener('input', applySnippetFilters);
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
			codeInput.addEventListener('input', function () {
				updateLineNumbers(codeEditor);
				applySnippetFilters();
			});
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
						toggleLabel.textContent = toggle.checked ? 'ON' : 'OFF';
					}
					applySnippetFilters();
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
							toggleLabel.textContent = data.data.enabled ? 'ON' : 'OFF';
						}
						toggle.title = data.data.message;
						applySnippetFilters();
					})
					.catch(function (error) {
						toggle.checked = previousState;
						if (toggleLabel) {
							toggleLabel.textContent = previousState ? 'ON' : 'OFF';
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

	languageTabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			selectedLanguage = tab.dataset.waLanguageFilter || 'all';
			currentPage = 1;
			languageTabs.forEach(function (item) {
				item.setAttribute('aria-pressed', item === tab ? 'true' : 'false');
			});
			applySnippetFilters();
		});
	});

	statusTabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			list.querySelectorAll('[data-wa-snippet].is-selected').forEach(function (snippet) {
				snippet.classList.remove('is-selected');
			});
			const requestedStatus = tab.dataset.waStatusFilter || 'all';
			selectedStatus = selectedStatus === requestedStatus ? 'all' : requestedStatus;
			currentPage = 1;
			statusTabs.forEach(function (item) {
				item.setAttribute('aria-pressed', item.dataset.waStatusFilter === selectedStatus ? 'true' : 'false');
			});
			applySnippetFilters();
		});
	});

	if (searchInput) {
		searchInput.addEventListener('input', function () {
			currentPage = 1;
			applySnippetFilters();
		});
	}
	if (locationFilter) {
		locationFilter.addEventListener('change', function () {
			selectedLocation = locationFilter.value || 'all';
			currentPage = 1;
			applySnippetFilters();
		});
	}
	if (pageSizeInput) {
		pageSizeInput.addEventListener('change', function () {
			currentPage = 1;
			applySnippetFilters();
		});
	}
	if (selectionToggle) {
		selectionToggle.addEventListener('click', function () {
			selectionMode = !selectionMode;
			if (selectionMode) {
				list.querySelectorAll('[data-wa-snippet][open]').forEach(function (snippet) {
					snippet.open = false;
				});
			}
			if (!selectionMode) {
				list.querySelectorAll('[data-wa-snippet].is-selected').forEach(function (snippet) {
					snippet.classList.remove('is-selected');
				});
			}
			applySnippetFilters();
		});
	}
	list.addEventListener('click', function (event) {
		if (!selectionMode || !(event.target instanceof Element)) {
			return;
		}
		const snippet = event.target.closest('[data-wa-snippet]');
		if (!snippet || (snippet.dataset.waSnippetId || '').startsWith('new-') ||
			event.target.closest('button, a, input, select, textarea, label, [role="button"]')) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		snippet.classList.toggle('is-selected');
		applySnippetFilters();
	}, true);
	let pendingBulkAction = '';
	const closeBulkDialog = function () {
		if (!bulkDialog) {
			return;
		}
		bulkDialog.hidden = true;
		pendingBulkAction = '';
	};
	const submitBulkAction = function (action) {
		if (!bulkForm || !bulkAction) {
			return;
		}
		const selected = Array.from(list.querySelectorAll('[data-wa-snippet].is-selected'))
			.filter(function (snippet) { return !(snippet.dataset.waSnippetId || '').startsWith('new-'); });
		if (!selected.length) {
			return;
		}
		bulkForm.querySelectorAll('[name="snippet_ids[]"]').forEach(function (field) {
			field.remove();
		});
		selected.forEach(function (snippet) {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'snippet_ids[]';
			input.value = snippet.dataset.waSnippetId;
			bulkForm.appendChild(input);
		});
		bulkAction.value = action;
		bulkForm.requestSubmit();
	};
	bulkActionButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			const action = button.dataset.waBulkAction;
			if (!action || !bulkForm || !bulkAction) {
				return;
			}
			const selectedCount = list.querySelectorAll('[data-wa-snippet].is-selected:not([data-wa-snippet-id^="new-"])').length;
			if (!selectedCount) {
				return;
			}
			const actionLabels = {
				trash: 'Mover para a lixeira',
				activate: 'Ativar',
				deactivate: 'Desativar',
				restore: 'Restaurar',
				delete: 'Excluir permanentemente'
			};
			const actionLabel = actionLabels[action];
			if (!actionLabel) {
				return;
			}
			if (!bulkDialog || !bulkDialogTitle || !bulkDialogMessage || !bulkDialogConfirm) {
				if (window.confirm(actionLabel + ' os ' + selectedCount + ' códigos selecionados?')) {
					submitBulkAction(action);
				}
				return;
			}
			pendingBulkAction = action;
			bulkDialogTitle.textContent = action === 'delete' ? 'Excluir códigos permanentemente?' : actionLabel + ' códigos selecionados?';
			bulkDialogMessage.textContent = action === 'delete'
				? 'Esta ação não pode ser desfeita. Os ' + selectedCount + ' códigos selecionados serão excluídos permanentemente.'
				: actionLabel + ' os ' + selectedCount + ' códigos selecionados de uma só vez?';
			bulkDialogConfirm.textContent = action === 'delete' ? 'Excluir permanentemente' : 'Confirmar';
			bulkDialogConfirm.hidden = false;
			bulkDialogConfirm.classList.toggle('is-destructive', action === 'delete' || action === 'trash');
			bulkDialog.hidden = false;
			bulkDialogConfirm.focus();
		});
	});
	if (bulkDialog && bulkDialogConfirm && bulkAction && bulkForm) {
		bulkDialogConfirm.addEventListener('click', function () {
			if (!pendingBulkAction) {
				return;
			}
			const action = pendingBulkAction;
			closeBulkDialog();
			submitBulkAction(action);
		});
	}
	if (bulkDialog && bulkDialogCancel) {
		bulkDialog.querySelectorAll('[data-wa-bulk-dialog-cancel]').forEach(function (cancelButton) {
			cancelButton.addEventListener('click', function () {
				closeBulkDialog();
				if (selectionToggle) {
					selectionToggle.focus();
				}
			});
		});
		bulkDialog.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				closeBulkDialog();
				if (selectionToggle) {
					selectionToggle.focus();
				}
			}
		});
	}
	if (bulkForm) {
		bulkForm.addEventListener('submit', function (event) {
			const selected = Array.from(list.querySelectorAll('[data-wa-snippet].is-selected'))
				.filter(function (snippet) { return !(snippet.dataset.waSnippetId || '').startsWith('new-'); });
			if (!selected.length || !bulkAction || !bulkAction.value) {
				event.preventDefault();
				bulkAction.value = '';
				if (bulkDialogTitle && bulkDialogMessage && bulkDialog) {
					bulkDialogTitle.textContent = 'Não foi possível aplicar a ação';
					bulkDialogMessage.textContent = !selected.length
						? 'Selecione pelo menos um código salvo para continuar.'
						: 'Escolha uma ação válida e tente novamente.';
					bulkDialog.hidden = false;
					if (bulkDialogConfirm) {
						bulkDialogConfirm.hidden = true;
					}
				}
				return;
			}
		});
	}

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
		selectedLanguage = 'all';
		selectedStatus = 'all';
		currentPage = 1;
		if (searchInput) {
			searchInput.value = '';
		}
		if (locationFilter) {
			locationFilter.value = 'all';
			selectedLocation = 'all';
		}
		languageTabs.forEach(function (tab) {
			tab.setAttribute('aria-pressed', tab.dataset.waLanguageFilter === 'all' ? 'true' : 'false');
		});
		statusTabs.forEach(function (tab) {
			tab.setAttribute('aria-pressed', tab.dataset.waStatusFilter === 'all' ? 'true' : 'false');
		});
		const key = 'new-' + Date.now() + '-' + Math.random().toString(36).slice(2);
		const html = template.innerHTML.split('__SNIPPET_KEY__').join(key);
		const wrapper = document.createElement('div');
		wrapper.innerHTML = html.trim();
		const snippet = wrapper.firstElementChild;

		if (snippet) {
			list.appendChild(snippet);
			bindSnippet(snippet, false);
			snippet.open = true;
			currentPage = 1;
			applySnippetFilters();
			snippet.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	});

	applySnippetFilters();
});
