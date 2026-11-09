/**
 * WA Web Design - Apple-Inspired Admin JavaScript
 * Handles AJAX toggling, Dynamic Links CRUD and 1-click shortcode copying.
 */

(function ($) {
	'use strict';

	$(function () {
		const $toast = $('#wa-suit-toast');
		let toastTimer = null;
		const currentPage = new URLSearchParams(window.location.search).get('page');
		const waSuitPages = ['wa-suit', 'wa-suit-modules', 'wa-suit-settings', 'wa-suit-support'];

		if (waSuitPages.indexOf(currentPage) !== -1) {
			document.querySelectorAll('#wpbody-content .notice').forEach(function (notice) {
				const message = notice.textContent.toLocaleLowerCase();
				const hasFooEventsSettingsLink = notice.querySelector('a[href*="fooevents-settings"]');
				if (
					hasFooEventsSettingsLink &&
					message.indexOf('fooevents') !== -1 &&
					message.indexOf('license key is not set') !== -1
				) {
					notice.remove();
				}
			});
		}

		/**
		 * Shows clean floating toast notification.
		 *
		 * @param {string} message Text to display.
		 */
		function showToast(message) {
			clearTimeout(toastTimer);
			$toast.text(message).addClass('is-visible');

			toastTimer = setTimeout(function () {
				$toast.removeClass('is-visible');
			}, 3000);
		}

		/**
		 * 1. Module Switch Toggle (AJAX)
		 */
		$(document).on('change', '.wa-admin-module-toggle, .wa-apple-module-toggle', function () {
			const $checkbox = $(this);
			const moduleId = $checkbox.data('module-id');
			const isChecked = $checkbox.is(':checked');
			const state = isChecked ? 1 : 0;

			// Sync toggles if multiple exist (card & header)
			$('.wa-admin-module-toggle[data-module-id="' + moduleId + '"], .wa-apple-module-toggle[data-module-id="' + moduleId + '"]')
				.not($checkbox)
				.prop('checked', isChecked);

			$checkbox.prop('disabled', true);

			$.ajax({
				url: waSuitAdminData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wa_suit_toggle_module',
					module_id: moduleId,
					state: state,
					nonce: waSuitAdminData.nonce
				},
				success: function (response) {
					$checkbox.prop('disabled', false);

					if (response.success) {
						const data = response.data;
						const $card = $('[data-module-card="' + moduleId + '"]');
						const $label = $('[data-status-label="' + moduleId + '"]');
						const $actionBtn = $('[data-action-area="' + moduleId + '"]');
						const $navTab = $('[data-nav-module="' + moduleId + '"]');

						if (data.state) {
							$card.removeClass('is-inactive').addClass('is-active');
							$label.text(waSuitAdminData.strings.active);
							$actionBtn.fadeIn(150);
							$navTab.fadeIn(150);
						} else {
							$card.removeClass('is-active').addClass('is-inactive');
							$label.text(waSuitAdminData.strings.inactive);
							$actionBtn.fadeOut(150);
							$navTab.fadeOut(150);
						}

						// Update empty tab state
						setTimeout(function () {
							const visibleCount = $('.wa-apple-nav__tab--module:visible').length;
							if (visibleCount > 0) {
								$('#wa-nav-empty').hide();
							} else {
								$('#wa-nav-empty').fadeIn(150);
							}
						}, 200);

						showToast(data.message);
					} else {
						$checkbox.prop('checked', !isChecked);
						showToast(response.data && response.data.message ? response.data.message : 'Erro ao atualizar.');
					}
				},
				error: function () {
					$checkbox.prop('disabled', false);
					$checkbox.prop('checked', !isChecked);
					showToast('Erro de conexão ao salvar.');
				}
			});
		});

		/**
		 * 2. 1-Click Copy Shortcode
		 */
		$(document).on('click', '.wa-apple-btn-copy', function () {
			const $btn = $(this);
			const textToCopy = $btn.data('copy');
			const $textSpan = $btn.find('.copy-text');
			const originalText = $textSpan.text();

			if (!textToCopy) {
				return;
			}

			// Clipboard API
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(textToCopy).then(onCopied);
			} else {
				// Fallback
				const $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(textToCopy).select();
				document.execCommand('copy');
				$temp.remove();
				onCopied();
			}

			function onCopied() {
				$btn.addClass('is-copied');
				$textSpan.text(waSuitAdminData.strings.copied || 'Copiado!');
				showToast('Shortcode copiado: ' + textToCopy);

				setTimeout(function () {
					$btn.removeClass('is-copied');
					$textSpan.text(originalText);
				}, 2000);
			}
		});

		/**
		 * 3. Dynamic Links: Open / Close Add/Edit Drawer
		 */
		const $drawer = $('#wa-link-form-drawer');
		const $form = $('#wa-link-form');

		$('#wa-btn-toggle-add-link').on('click', function () {
			$('#wa-drawer-title').text('Criar Novo Link Dinâmico');
			$form[0].reset();
			$('#wa_link_id').prop('readonly', false);
			$drawer.slideDown(200);
			$('#wa_link_title').trigger('focus');
		});

		$('#wa-btn-close-drawer, #wa-btn-cancel-drawer').on('click', function () {
			$drawer.slideUp(150);
			$form[0].reset();
		});

		// Auto-generate slug from title while typing (if slug was empty)
		$('#wa_link_title').on('input', function () {
			const $idInput = $('#wa_link_id');
			if (!$idInput.prop('readonly') && !$idInput.data('user-edited')) {
				const val = $(this).val();
				const slug = val
					.toLowerCase()
					.normalize('NFD')
					.replace(/[\u0300-\u036f]/g, '')
					.replace(/[^a-z0-9]+/g, '-')
					.replace(/^-+|-+$/g, '');
				$idInput.val(slug);
			}
		});

		$('#wa_link_id').on('input', function () {
			$(this).data('user-edited', true);
		});

		/**
		 * 4. Dynamic Links: Save (Add or Edit) via AJAX
		 */
		$form.on('submit', function (e) {
			e.preventDefault();

			const $saveBtn = $('#wa-btn-save-link');
			const title = $.trim($('#wa_link_title').val());
			const id = $.trim($('#wa_link_id').val());
			const url = $.trim($('#wa_link_url').val());

			if (!title || !url) {
				showToast('Preencha o Nome e a URL.');
				return;
			}

			$saveBtn.prop('disabled', true).text(waSuitAdminData.strings.saving || 'Salvando...');

			$.ajax({
				url: waSuitAdminData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wa_suit_save_link',
					nonce: waSuitAdminData.nonce,
					title: title,
					id: id,
					url: url
				},
				success: function (res) {
					$saveBtn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + (waSuitAdminData.strings.save || 'Salvar Link'));

					if (res.success) {
						const link = res.data.link;
						const shortcode = res.data.shortcode;

						// Remove empty state if present
						$('#wa-empty-row').remove();

						const existingRow = $('tr[data-link-id="' + link.id + '"]');

						const rowHtml = `
							<tr data-link-id="${link.id}">
								<td>
									<strong class="wa-apple-link-title">${escapeHtml(link.title)}</strong>
									<span class="wa-apple-link-id">ID: <code>${escapeHtml(link.id)}</code></span>
								</td>
								<td>
									<a href="${escapeHtml(link.url)}" target="_blank" rel="noopener noreferrer" class="wa-apple-url-preview" title="${escapeHtml(link.url)}">
										${escapeHtml(link.url)}
									</a>
								</td>
								<td>
									<div class="wa-apple-copy-box">
										<code class="wa-apple-code-tag">${escapeHtml(shortcode)}</code>
										<button type="button" class="wa-apple-btn-copy" data-copy="${escapeHtml(shortcode)}" title="Copiar shortcode">
											<span class="dashicons dashicons-admin-page"></span>
											<span class="copy-text">${waSuitAdminData.strings.copy || 'Copiar'}</span>
										</button>
									</div>
								</td>
								<td class="wa-apple-text-right">
									<button type="button" class="wa-apple-icon-btn wa-btn-edit-link"
										data-id="${escapeHtml(link.id)}"
										data-title="${escapeHtml(link.title)}"
										data-url="${escapeHtml(link.url)}"
										title="Editar">
										<span class="dashicons dashicons-edit"></span>
									</button>
									<button type="button" class="wa-apple-icon-btn wa-apple-icon-btn--danger wa-btn-delete-link"
										data-id="${escapeHtml(link.id)}"
										data-title="${escapeHtml(link.title)}"
										title="Excluir">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</td>
							</tr>
						`;

						if (existingRow.length > 0) {
							existingRow.replaceWith(rowHtml);
						} else {
							$('#wa-links-tbody').prepend(rowHtml);
						}

						$drawer.slideUp(150);
						$form[0].reset();
						$('#wa_link_id').removeData('user-edited');
						showToast(res.data.message || 'Link salvo com sucesso!');
					} else {
						showToast(res.data && res.data.message ? res.data.message : 'Erro ao salvar link.');
					}
				},
				error: function () {
					$saveBtn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + (waSuitAdminData.strings.save || 'Salvar Link'));
					showToast('Erro de conexão ao salvar link.');
				}
			});
		});

		/**
		 * 5. Dynamic Links: Edit Row
		 */
		$(document).on('click', '.wa-btn-edit-link', function () {
			const $btn = $(this);
			const id = $btn.data('id');
			const title = $btn.data('title');
			const url = $btn.data('url');

			$('#wa-drawer-title').text('Editar Link: ' + title);
			$('#wa_link_title').val(title);
			$('#wa_link_id').val(id).prop('readonly', true);
			$('#wa_link_url').val(url);

			$drawer.slideDown(200);
			$('#wa_link_url').trigger('focus');
		});

		/**
		 * 6. Dynamic Links: Delete Row
		 */
		$(document).on('click', '.wa-btn-delete-link', function () {
			const $btn = $(this);
			const id = $btn.data('id');
			const title = $btn.data('title') || id;

			const confirmMsg = (waSuitAdminData.strings.confirmDel || 'Excluir?') + '\n"' + title + '"';
			if (!confirm(confirmMsg)) {
				return;
			}

			$.ajax({
				url: waSuitAdminData.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wa_suit_delete_link',
					nonce: waSuitAdminData.nonce,
					id: id
				},
				success: function (res) {
					if (res.success) {
						$('tr[data-link-id="' + id + '"]').fadeOut(200, function () {
							$(this).remove();
							if ($('#wa-links-tbody tr').length === 0) {
								$('#wa-links-tbody').html(`
									<tr class="wa-apple-empty-row" id="wa-empty-row">
										<td colspan="4">
											<div class="wa-apple-empty-state">
												<div class="wa-apple-empty-icon">
													<span class="dashicons dashicons-admin-links"></span>
												</div>
												<h4>Nenhum link cadastrado ainda</h4>
												<p>Clique no botão acima "Novo Link" para criar seu primeiro link dinâmico.</p>
											</div>
										</td>
									</tr>
								`);
							}
						});
						showToast(res.data.message || 'Link excluído.');
					} else {
						showToast(res.data && res.data.message ? res.data.message : 'Erro ao excluir link.');
					}
				},
				error: function () {
					showToast('Erro de conexão ao excluir.');
				}
			});
		});

		function escapeHtml(string) {
			const entityMap = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;'
			};
			return String(string).replace(/[&<>"']/g, function (s) {
				return entityMap[s];
			});
		}
	});
})(jQuery);
