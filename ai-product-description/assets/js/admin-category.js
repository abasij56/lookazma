(function () {
	'use strict';

	var cfg = window.aiProductDescCategory || null;
	var root = document.getElementById('ai-cat-tools');
	if (!cfg || !root) {
		return;
	}

	var termId = root.getAttribute('data-term-id') || '';
	var previewSeo = { title: '', metadesc: '', focuskw: '' };
	var previewSpecs = null;
	var EDITOR_CURRENT = 'ai_cat_current_desc';
	var EDITOR_PREVIEW = 'ai_cat_preview_desc';
	var EDITOR_STAGE1 = 'ai_cat_stage1_desc';

	function looksLikeVisibleHtmlSource(html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html || '';
		var text = String(tmp.textContent || tmp.innerText || '').trim();
		return /^<\/?[a-z]/i.test(text);
	}

	function forceVisualMode(editorId) {
		if (typeof window.switchEditors !== 'undefined' && window.switchEditors.go) {
			try {
				window.switchEditors.go(editorId, 'tmce');
			} catch (e) {
				// Ignore switch errors.
			}
		}
	}

	function hydrateCurrentEditor(editor) {
		if (!editor || editor.id !== EDITOR_CURRENT) {
			return;
		}

		forceVisualMode(EDITOR_CURRENT);

		var html = (cfg.currentDescHtml || '').trim();
		if (!html) {
			return;
		}

		// Always set prepared HTML into Visual mode so tags render, not show as text.
		window.setTimeout(function () {
			forceVisualMode(EDITOR_CURRENT);
			var ed = window.tinymce ? window.tinymce.get(EDITOR_CURRENT) : null;
			if (!ed) {
				return;
			}
			var current = String(ed.getContent() || '');
			if (!current || looksLikeVisibleHtmlSource(current) || current !== html) {
				ed.setContent(html);
				ed.setDirty(false);
			}
		}, 200);
	}

	function isConfigured() {
		return cfg.isConfigured === 1 || cfg.isConfigured === '1' || cfg.isConfigured === true;
	}

	function setStatus(el, message, isError) {
		if (!el) {
			return;
		}
		if (!message) {
			el.hidden = true;
			el.textContent = '';
			el.classList.remove('is-error', 'is-success');
			return;
		}
		el.hidden = false;
		el.textContent = message;
		el.classList.toggle('is-error', !!isError);
		el.classList.toggle('is-success', !isError);
	}

	function setLoading(btn, loading, label) {
		if (!btn) {
			return;
		}
		if (loading) {
			if (!btn.getAttribute('data-original-text')) {
				btn.setAttribute('data-original-text', btn.textContent);
			}
			btn.disabled = true;
			btn.classList.add('is-loading');
			btn.textContent = label;
		} else {
			btn.disabled = false;
			btn.classList.remove('is-loading');
			btn.textContent =
				btn.getAttribute('data-original-text') || btn.textContent;
		}
	}

	function post(action, fields) {
		var formData = new FormData();
		formData.append('action', action);
		formData.append('nonce', cfg.nonce);
		formData.append('term_id', termId);
		Object.keys(fields || {}).forEach(function (key) {
			formData.append(key, fields[key]);
		});

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		}).then(function (response) {
			return response.json().then(function (payload) {
				return { ok: response.ok, payload: payload };
			});
		});
	}

	function fillText(el, value, emptyLabel) {
		if (!el) {
			return;
		}
		var text = (value || '').trim();
		if (!text) {
			el.textContent = emptyLabel;
			el.classList.add('is-empty');
			return;
		}
		el.textContent = text;
		el.classList.remove('is-empty');
	}

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function plainToHtml(text) {
		var value = String(text || '').trim();
		if (!value) {
			return '';
		}
		if (/<[a-z][\s\S]*>/i.test(value)) {
			return value;
		}
		return value
			.split(/\n{2,}/)
			.map(function (block) {
				return (
					'<p>' +
					escapeHtml(block.trim()).replace(/\r\n|\r|\n/g, '<br>') +
					'</p>'
				);
			})
			.join('');
	}

	function setEditorContent(editorId, html) {
		var content = html || '';
		if (window.tinymce) {
			var editor = window.tinymce.get(editorId);
			if (editor) {
				editor.setContent(content);
				editor.setDirty(false);
				return;
			}
		}
		var textarea = document.getElementById(editorId);
		if (textarea) {
			textarea.value = content;
		}
		if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.val === 'function') {
			window.jQuery('#' + editorId).val(content).trigger('change');
		}
	}

	function getEditorContent(editorId) {
		if (window.tinymce) {
			var editor = window.tinymce.get(editorId);
			if (editor) {
				if (editor.isHidden()) {
					var ta = document.getElementById(editorId);
					return ta ? String(ta.value || '').trim() : '';
				}
				return String(editor.getContent() || '').trim();
			}
		}
		var textarea = document.getElementById(editorId);
		return textarea ? String(textarea.value || '').trim() : '';
	}

	function hasMeaningfulContent(html) {
		var tmp = document.createElement('div');
		tmp.innerHTML = html || '';
		return String(tmp.textContent || tmp.innerText || '')
			.replace(/\u00a0/g, ' ')
			.trim().length > 0;
	}

	document
		.getElementById('ai-cat-generate-seo')
		.addEventListener('click', function () {
			var btn = this;
			var saveBtn = document.getElementById('ai-cat-save-seo');
			var status = document.getElementById('ai-cat-seo-status');

			if (!isConfigured()) {
				window.alert(cfg.i18n.notConfigured);
				return;
			}

			setStatus(status, '');
			setLoading(btn, true, cfg.i18n.loading);
			if (saveBtn) {
				saveBtn.disabled = true;
			}

			post(cfg.actions.generateSeo, {})
				.then(function (result) {
					setLoading(btn, false);
					if (!result.payload || !result.payload.success) {
						var message =
							(result.payload &&
								result.payload.data &&
								result.payload.data.message) ||
							cfg.i18n.error;
						setStatus(status, message, true);
						return;
					}

					var data = result.payload.data || {};
					previewSeo.title = data.seo_title || '';
					previewSeo.metadesc = data.seo_metadesc || '';
					previewSeo.focuskw = data.seo_focuskw || '';

					fillText(
						document.getElementById('ai-cat-preview-seo-focuskw'),
						previewSeo.focuskw,
						cfg.i18n.emptyFocuskw
					);
					fillText(
						document.getElementById('ai-cat-preview-seo-title'),
						previewSeo.title,
						cfg.i18n.emptyTitle
					);
					fillText(
						document.getElementById('ai-cat-preview-seo-meta'),
						previewSeo.metadesc,
						cfg.i18n.emptyMeta
					);

					if (saveBtn) {
						saveBtn.disabled = !(
							previewSeo.title ||
							previewSeo.metadesc ||
							previewSeo.focuskw
						);
					}
					setStatus(status, '', false);
				})
				.catch(function () {
					setLoading(btn, false);
					setStatus(status, cfg.i18n.error, true);
				});
		});

	document
		.getElementById('ai-cat-save-seo')
		.addEventListener('click', function () {
			var btn = this;
			var status = document.getElementById('ai-cat-seo-status');

			if (!previewSeo.title && !previewSeo.metadesc && !previewSeo.focuskw) {
				window.alert(cfg.i18n.needPreview);
				return;
			}

			if (
				!window.confirm(
					cfg.i18n.confirmSeo ||
						'سئوی فعلی دسته با پیشنهاد AI جایگزین شود؟'
				)
			) {
				return;
			}

			setLoading(btn, true, cfg.i18n.saving);
			setStatus(status, '');

			post(cfg.actions.saveSeo, {
				seo_title: previewSeo.title,
				seo_metadesc: previewSeo.metadesc,
				seo_focuskw: previewSeo.focuskw,
			})
				.then(function (result) {
					setLoading(btn, false);
					btn.disabled = false;

					if (!result.payload || !result.payload.success) {
						var message =
							(result.payload &&
								result.payload.data &&
								result.payload.data.message) ||
							cfg.i18n.saveError;
						setStatus(status, message, true);
						return;
					}

					var data = result.payload.data || {};
					fillText(
						document.getElementById('ai-cat-current-seo-focuskw'),
						data.seo_focuskw,
						cfg.i18n.emptyFocuskw
					);
					fillText(
						document.getElementById('ai-cat-current-seo-title'),
						data.seo_title,
						cfg.i18n.emptyTitle
					);
					fillText(
						document.getElementById('ai-cat-current-seo-meta'),
						data.seo_metadesc,
						cfg.i18n.emptyMeta
					);

					// Sync Yoast focus keyphrase field on the edit screen if present.
					var focusInput =
						document.getElementById('focuskw') ||
						document.querySelector('input[name="wpseo_focuskw"]') ||
						document.querySelector('#wpseo_focuskw');
					if (focusInput && data.seo_focuskw) {
						focusInput.value = data.seo_focuskw;
						focusInput.dispatchEvent(new Event('input', { bubbles: true }));
						focusInput.dispatchEvent(new Event('change', { bubbles: true }));
					}

					setStatus(
						status,
						data.message || 'ذخیره شد.',
						false
					);
				})
				.catch(function () {
					setLoading(btn, false);
					btn.disabled = false;
					setStatus(status, cfg.i18n.saveError, true);
				});
		});

	document
		.getElementById('ai-cat-generate-desc')
		.addEventListener('click', function () {
			var btn = this;
			var saveBtn = document.getElementById('ai-cat-save-desc');
			var status = document.getElementById('ai-cat-desc-status');

			if (!isConfigured()) {
				window.alert(cfg.i18n.notConfigured);
				return;
			}

			setStatus(status, '');
			setLoading(btn, true, cfg.i18n.loadingDesc || cfg.i18n.loading);
			if (saveBtn) {
				saveBtn.disabled = true;
			}

			post(cfg.actions.generateDesc, {})
				.then(function (result) {
					setLoading(btn, false);
					if (!result.payload || !result.payload.success) {
						var message =
							(result.payload &&
								result.payload.data &&
								result.payload.data.message) ||
							cfg.i18n.error;
						setStatus(status, message, true);
						return;
					}

					var data = result.payload.data || {};
					var html = plainToHtml(data.description || '');
					var stage1 = plainToHtml(data.stage1_html || '');
					setEditorContent(EDITOR_PREVIEW, html);
					setEditorContent(EDITOR_STAGE1, stage1);

					if (saveBtn) {
						saveBtn.disabled = !hasMeaningfulContent(html);
					}
				})
				.catch(function () {
					setLoading(btn, false);
					setStatus(status, cfg.i18n.error, true);
				});
		});

	document
		.getElementById('ai-cat-save-desc')
		.addEventListener('click', function () {
			var btn = this;
			var status = document.getElementById('ai-cat-desc-status');
			var html = getEditorContent(EDITOR_PREVIEW);

			if (!hasMeaningfulContent(html)) {
				window.alert(cfg.i18n.needPreview);
				return;
			}

			if (
				!window.confirm(
					cfg.i18n.confirmDesc ||
						'توضیحات فعلی دسته با پیشنهاد AI جایگزین شود؟'
				)
			) {
				return;
			}

			setLoading(btn, true, cfg.i18n.saving);
			setStatus(status, '');

			post(cfg.actions.saveDesc, {
				description: html,
			})
				.then(function (result) {
					setLoading(btn, false);
					btn.disabled = false;

					if (!result.payload || !result.payload.success) {
						var message =
							(result.payload &&
								result.payload.data &&
								result.payload.data.message) ||
							cfg.i18n.saveError;
						setStatus(status, message, true);
						return;
					}

					var data = result.payload.data || {};
					var savedHtml = data.html || html;
					setEditorContent(EDITOR_CURRENT, savedHtml);

					var coreDesc = document.getElementById('description');
					if (coreDesc) {
						coreDesc.value = savedHtml;
					}
					if (window.tinymce && window.tinymce.get('description')) {
						window.tinymce.get('description').setContent(savedHtml);
					}

					setStatus(
						status,
						data.message || 'ذخیره شد.',
						false
					);
				})
				.catch(function () {
					setLoading(btn, false);
					btn.disabled = false;
					setStatus(status, cfg.i18n.saveError, true);
				});
		});

	// Enable save when user edits preview manually.
	function bindPreviewEditorEvents(editor) {
		if (!editor) {
			return;
		}

		if (editor.id === EDITOR_CURRENT) {
			hydrateCurrentEditor(editor);
		}

		if (editor.id !== EDITOR_PREVIEW) {
			return;
		}
		editor.on('change keyup SetContent', function () {
			var saveBtn = document.getElementById('ai-cat-save-desc');
			if (saveBtn) {
				saveBtn.disabled = !hasMeaningfulContent(
					getEditorContent(EDITOR_PREVIEW)
				);
			}
		});
	}

	function bootEditors() {
		forceVisualMode(EDITOR_CURRENT);
		forceVisualMode(EDITOR_PREVIEW);

		if (window.tinymce) {
			window.tinymce.on('AddEditor', function (event) {
				bindPreviewEditorEvents(event.editor);
			});

			if (window.tinymce.editors && window.tinymce.editors.length) {
				for (var i = 0; i < window.tinymce.editors.length; i++) {
					bindPreviewEditorEvents(window.tinymce.editors[i]);
				}
			}

			var currentEd = window.tinymce.get(EDITOR_CURRENT);
			if (currentEd) {
				hydrateCurrentEditor(currentEd);
			} else {
				// TinyMCE may init slightly later on first open.
				window.setTimeout(function () {
					var ed = window.tinymce.get(EDITOR_CURRENT);
					if (ed) {
						hydrateCurrentEditor(ed);
					}
				}, 600);
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootEditors);
	} else {
		bootEditors();
	}

	window.addEventListener('load', function () {
		forceVisualMode(EDITOR_CURRENT);
		forceVisualMode(EDITOR_PREVIEW);
		if (window.tinymce && window.tinymce.get(EDITOR_CURRENT)) {
			hydrateCurrentEditor(window.tinymce.get(EDITOR_CURRENT));
		}
	});

	var previewTextarea = document.getElementById(EDITOR_PREVIEW);
	if (previewTextarea) {
		previewTextarea.addEventListener('input', function () {
			var saveBtn = document.getElementById('ai-cat-save-desc');
			if (saveBtn) {
				saveBtn.disabled = !hasMeaningfulContent(this.value);
			}
		});
	}

	function fillSpecsTable(tbodyId, fields, emptyLabel) {
		var tbody = document.getElementById(tbodyId);
		if (!tbody) {
			return;
		}
		var rows = tbody.querySelectorAll('tr[data-field]');
		for (var i = 0; i < rows.length; i++) {
			var row = rows[i];
			var key = row.getAttribute('data-field') || '';
			var cell = row.querySelector('td');
			if (!cell) {
				continue;
			}
			var value =
				fields && Object.prototype.hasOwnProperty.call(fields, key)
					? String(fields[key] || '').trim()
					: '';
			if (!value) {
				cell.textContent = emptyLabel || cfg.i18n.emptyValue || 'خالی';
				cell.classList.add('is-empty');
			} else {
				cell.textContent = value;
				cell.classList.remove('is-empty');
			}
		}
	}

	function syncAcfInputs(fields) {
		if (!fields || !window.jQuery) {
			return;
		}
		Object.keys(fields).forEach(function (name) {
			var value = fields[name];
			var $wrap = window.jQuery('.acf-field[data-name="' + name + '"]');
			if (!$wrap.length) {
				return;
			}
			var $input = $wrap.find('input[type="text"], textarea').first();
			if ($input.length) {
				$input.val(value).trigger('change');
			}
		});
	}

	var generateSpecsBtn = document.getElementById('ai-cat-generate-specs');
	if (generateSpecsBtn) {
		generateSpecsBtn.addEventListener('click', function () {
			var btn = this;
			var saveBtn = document.getElementById('ai-cat-save-specs');
			var status = document.getElementById('ai-cat-specs-status');
			var hint = document.getElementById('ai-cat-specs-hint');

			if (!isConfigured()) {
				window.alert(cfg.i18n.notConfigured);
				return;
			}

			setStatus(status, '');
			if (hint) {
				hint.hidden = true;
				hint.textContent = '';
			}
			setLoading(btn, true, cfg.i18n.loading);
			if (saveBtn) {
				saveBtn.disabled = true;
			}
			previewSpecs = null;

			post(cfg.actions.generateSpecs, {})
				.then(function (result) {
					setLoading(btn, false);
					if (!result.payload || !result.payload.success) {
						var message =
							(result.payload &&
								result.payload.data &&
								result.payload.data.message) ||
							cfg.i18n.error;
						setStatus(status, message, true);
						return;
					}

					var data = result.payload.data || {};
					previewSpecs = data.fields || {};
					fillSpecsTable(
						'ai-cat-preview-specs-body',
						previewSpecs,
						cfg.i18n.emptyValue
					);

					if (hint) {
						if (data.is_single_chemical === false) {
							hint.textContent =
								cfg.i18n.notChemical ||
								'این دسته ماده شیمیایی تکی تشخیص داده نشد.';
							hint.hidden = false;
						} else {
							hint.hidden = true;
							hint.textContent = '';
						}
					}

					if (saveBtn) {
						saveBtn.disabled = !previewSpecs || !Object.keys(previewSpecs).length;
					}
				})
				.catch(function () {
					setLoading(btn, false);
					setStatus(status, cfg.i18n.error, true);
				});
		});
	}

	var saveSpecsBtn = document.getElementById('ai-cat-save-specs');
	if (saveSpecsBtn) {
		saveSpecsBtn.addEventListener('click', function () {
			var btn = this;
			var status = document.getElementById('ai-cat-specs-status');

			if (!previewSpecs || !Object.keys(previewSpecs).length) {
				window.alert(cfg.i18n.needPreview);
				return;
			}

			if (
				!window.confirm(
					cfg.i18n.confirmSpecs ||
						'مشخصات فنی فعلی دسته با پیشنهاد AI جایگزین شود؟'
				)
			) {
				return;
			}

			setLoading(btn, true, cfg.i18n.saving);
			setStatus(status, '');

			post(cfg.actions.saveSpecs, {
				fields: JSON.stringify(previewSpecs),
			})
				.then(function (result) {
					setLoading(btn, false);
					btn.disabled = false;

					if (!result.payload || !result.payload.success) {
						var message =
							(result.payload &&
								result.payload.data &&
								result.payload.data.message) ||
							cfg.i18n.saveError;
						setStatus(status, message, true);
						return;
					}

					var data = result.payload.data || {};
					var saved = data.fields || previewSpecs;
					fillSpecsTable(
						'ai-cat-current-specs-body',
						saved,
						cfg.i18n.emptyValue
					);
					syncAcfInputs(saved);
					cfg.currentSpecs = saved;
					setStatus(status, data.message || 'ذخیره شد.', false);
				})
				.catch(function () {
					setLoading(btn, false);
					btn.disabled = false;
					setStatus(status, cfg.i18n.saveError, true);
				});
		});
	}
})();
