(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var Button = wp.components.Button;
	var RangeControl = wp.components.RangeControl;
	var __ = wp.i18n.__;

	function registerHomepageBlock(name, settings) {
		if (typeof wp.blocks.getBlockType === 'function' && wp.blocks.getBlockType(name)) {
			wp.blocks.unregisterBlockType(name);
		}
		wp.blocks.registerBlockType(
			name,
			Object.assign(
				{
					category: 'lookazma-homepage',
					supports: { html: false, inserter: true },
					save: function () {
						return null;
					}
				},
				settings
			)
		);
	}

	function blockLabel(name) {
		return __('بلوک: ', 'hello-elementor-child') + name;
	}

	function updateListItem(items, index, patch, setAttributes, attrName) {
		var next = (items || []).slice();
		next[index] = Object.assign({}, next[index], patch);
		var update = {};
		update[attrName] = next;
		setAttributes(update);
	}

	function removeListItem(items, index, setAttributes, attrName) {
		var next = (items || []).slice();
		next.splice(index, 1);
		var update = {};
		update[attrName] = next;
		setAttributes(update);
	}

	function addListItem(items, emptyItem, setAttributes, attrName) {
		var next = (items || []).slice();
		next.push(emptyItem);
		var update = {};
		update[attrName] = next;
		setAttributes(update);
	}

	function renderListEditor(items, fields, setAttributes, attrName, emptyItem, title) {
		return el(
			'div',
			{ className: 'lk-lk-editor-panel' },
			el('h4', null, title),
			(items || []).map(function (item, index) {
				return el(
					'div',
					{ key: index, className: 'lk-lk-editor-row' },
					fields.map(function (field) {
						return el(TextControl, {
							key: field.key,
							label: field.label,
							value: item[field.key] || '',
							onChange: function (value) {
								var patch = {};
								patch[field.key] = value;
								updateListItem(items, index, patch, setAttributes, attrName);
							}
						});
					}),
					el(
						Button,
						{
							isDestructive: true,
							variant: 'secondary',
							onClick: function () {
								removeListItem(items, index, setAttributes, attrName);
							}
						},
						__('حذف', 'hello-elementor-child')
					)
				);
			}),
			el(
				Button,
				{
					variant: 'primary',
					onClick: function () {
						addListItem(items, emptyItem, setAttributes, attrName);
					}
				},
				__('افزودن', 'hello-elementor-child')
			)
		);
	}

	registerHomepageBlock('lookazma/home-hero-carousel', {
		title: __('اسلایدر هیرو', 'hello-elementor-child'),
		description: __('اسلایدر تمام‌صفحه قابل ویرایش برای صفحه اصلی.', 'hello-elementor-child'),
		icon: 'images-alt2',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var slides = attributes.slides || [];
			var blockProps = useBlockProps({ className: 'lk-homepage-block' });

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('اسلایدر هیرو', 'hello-elementor-child'), initialOpen: true },
						el(RangeControl, {
							label: __('زمان هر اسلاید (میلی‌ثانیه)', 'hello-elementor-child'),
							min: 2000,
							max: 12000,
							step: 500,
							value: attributes.autoplayMs || 5000,
							onChange: function (value) {
								setAttributes({ autoplayMs: value });
							}
						}),
						renderListEditor(
							slides,
							[
								{ key: 'url', label: __('آدرس تصویر', 'hello-elementor-child') },
								{ key: 'alt', label: __('متن جایگزین', 'hello-elementor-child') }
							],
							setAttributes,
							'slides',
							{ url: '', alt: '' },
							__('اسلایدها', 'hello-elementor-child')
						)
					)
				),
				el(
					'section',
					blockProps,
					el('div', { className: 'lk-hero-carousel', 'data-lk-hero-carousel': true },
						el('div', { className: 'lk-hero-carousel__viewport' },
							slides.length
								? slides.map(function (slide, index) {
									return el(
										'div',
										{
											key: index,
											className: 'lk-hero-carousel__slide' + (index === 0 ? ' is-active' : '')
										},
										slide.url
											? el('img', { src: slide.url, alt: slide.alt || '' })
											: el('p', null, blockLabel(__('اسلایدر هیرو', 'hello-elementor-child')))
									);
								})
								: el('p', null, blockLabel(__('اسلایدر هیرو', 'hello-elementor-child')))
						)
					)
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-promise-bar', {
		title: __('نوار مزایا', 'hello-elementor-child'),
		description: __('چهار کارت اعتماد و مزیت خرید.', 'hello-elementor-child'),
		icon: 'shield',
		edit: function (props) {
			var items = props.attributes.items || [];
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: 'lk-homepage-block container' });

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('نوار مزایا', 'hello-elementor-child'), initialOpen: true },
						renderListEditor(
							items,
							[
								{ key: 'title', label: __('عنوان', 'hello-elementor-child') },
								{ key: 'text', label: __('توضیح', 'hello-elementor-child') }
							],
							setAttributes,
							'items',
							{ title: '', text: '' },
							__('آیتم‌ها', 'hello-elementor-child')
						)
					)
				),
				el(
					'section',
					blockProps,
					el('div', { className: 'lk-promise-bar lk-section' },
						items.map(function (item, index) {
							return el(
								'div',
								{ key: index, className: 'lk-promise' },
								el('strong', null, item.title || ''),
								el('span', null, item.text || '')
							);
						})
					)
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-brand-strip', {
		title: __('نوار برندها', 'hello-elementor-child'),
		description: __('لوگوی برندها با عنوان و دکمه.', 'hello-elementor-child'),
		icon: 'tag',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var brands = attributes.brands || [];
			var blockProps = useBlockProps({ className: 'lk-homepage-block container' });

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('نوار برندها', 'hello-elementor-child'), initialOpen: true },
						el(TextControl, {
							label: __('عنوان', 'hello-elementor-child'),
							value: attributes.title || '',
							onChange: function (value) { setAttributes({ title: value }); }
						}),
						el(TextareaControl, {
							label: __('توضیح', 'hello-elementor-child'),
							value: attributes.description || '',
							onChange: function (value) { setAttributes({ description: value }); }
						}),
						el(TextControl, {
							label: __('متن دکمه', 'hello-elementor-child'),
							value: attributes.ctaText || '',
							onChange: function (value) { setAttributes({ ctaText: value }); }
						}),
						el(TextControl, {
							label: __('لینک دکمه', 'hello-elementor-child'),
							value: attributes.ctaUrl || '',
							onChange: function (value) { setAttributes({ ctaUrl: value }); }
						}),
						renderListEditor(
							brands,
							[
								{ key: 'image', label: __('آدرس لوگو', 'hello-elementor-child') },
								{ key: 'alt', label: __('نام برند', 'hello-elementor-child') },
								{ key: 'link', label: __('لینک', 'hello-elementor-child') }
							],
							setAttributes,
							'brands',
							{ image: '', alt: '', link: '' },
							__('برندها', 'hello-elementor-child')
						)
					)
				),
				el(
					'section',
					blockProps,
					el('h2', null, attributes.title || blockLabel(__('نوار برندها', 'hello-elementor-child'))),
					el('p', null, attributes.description || ''),
					el('div', { className: 'lk-brand-strip__track' },
						brands.map(function (brand, index) {
							return el(
								'div',
								{ key: index, className: 'lk-brand' },
								brand.image ? el('img', { src: brand.image, alt: brand.alt || '' }) : null
							);
						})
					)
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-category-grid', {
		title: __('دسته‌بندی‌ها', 'hello-elementor-child'),
		description: __('کارت‌های میانبر دسته‌بندی محصولات.', 'hello-elementor-child'),
		icon: 'category',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var categories = attributes.categories || [];
			var blockProps = useBlockProps({ className: 'lk-homepage-block container' });

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('دسته‌بندی‌ها', 'hello-elementor-child'), initialOpen: true },
						el(TextControl, {
							label: __('عنوان', 'hello-elementor-child'),
							value: attributes.title || '',
							onChange: function (value) { setAttributes({ title: value }); }
						}),
						renderListEditor(
							categories,
							[
								{ key: 'title', label: __('عنوان', 'hello-elementor-child') },
								{ key: 'subtitle', label: __('زیرعنوان', 'hello-elementor-child') },
								{ key: 'image', label: __('تصویر', 'hello-elementor-child') },
								{ key: 'link', label: __('لینک', 'hello-elementor-child') }
							],
							setAttributes,
							'categories',
							{ title: '', subtitle: '', image: '', link: '' },
							__('کارت‌ها', 'hello-elementor-child')
						)
					)
				),
				el(
					'section',
					blockProps,
					el('div', { className: 'lk-categories__viewport' },
						el('div', { className: 'lk-categories__track' },
							categories.map(function (cat, index) {
								return el('div', { key: index, className: 'lk-category' }, el('strong', null, cat.title || ''));
							})
						)
					)
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-product-grid', {
		title: __('گرید محصولات', 'hello-elementor-child'),
		description: __('نمایش محصولات ووکامرس در صفحه اصلی.', 'hello-elementor-child'),
		icon: 'products',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: 'lk-homepage-block container' });

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('گرید محصولات', 'hello-elementor-child'), initialOpen: true },
						el(TextControl, {
							label: __('عنوان', 'hello-elementor-child'),
							value: attributes.title || '',
							onChange: function (value) { setAttributes({ title: value }); }
						}),
						el(TextareaControl, {
							label: __('توضیح', 'hello-elementor-child'),
							value: attributes.description || '',
							onChange: function (value) { setAttributes({ description: value }); }
						}),
						el(RangeControl, {
							label: __('تعداد محصولات', 'hello-elementor-child'),
							min: 1,
							max: 12,
							value: attributes.limit || 5,
							onChange: function (value) { setAttributes({ limit: value }); }
						}),
						el(TextControl, {
							label: __('متن دکمه', 'hello-elementor-child'),
							value: attributes.ctaText || '',
							onChange: function (value) { setAttributes({ ctaText: value }); }
						}),
						el(TextControl, {
							label: __('لینک دکمه', 'hello-elementor-child'),
							value: attributes.ctaUrl || '',
							onChange: function (value) { setAttributes({ ctaUrl: value }); }
						})
					)
				),
				el(
					'section',
					blockProps,
					el('h2', null, attributes.title || blockLabel(__('گرید محصولات', 'hello-elementor-child'))),
					el('p', null, __('در فرانت‌اند محصولات ووکامرس نمایش داده می‌شوند.', 'hello-elementor-child'))
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-banner-row', {
		title: __('ردیف بنر', 'hello-elementor-child'),
		description: __('سه بنر تبلیغاتی با تصویر و لینک.', 'hello-elementor-child'),
		icon: 'format-image',
		edit: function (props) {
			var banners = props.attributes.banners || [];
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: 'lk-homepage-block container' });

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('ردیف بنر', 'hello-elementor-child'), initialOpen: true },
						renderListEditor(
							banners,
							[
								{ key: 'image', label: __('تصویر', 'hello-elementor-child') },
								{ key: 'label', label: __('برچسب', 'hello-elementor-child') },
								{ key: 'title', label: __('عنوان', 'hello-elementor-child') },
								{ key: 'text', label: __('متن', 'hello-elementor-child') },
								{ key: 'link', label: __('لینک', 'hello-elementor-child') }
							],
							setAttributes,
							'banners',
							{ image: '', label: '', title: '', text: '', link: '' },
							__('بنرها', 'hello-elementor-child')
						)
					)
				),
				el(
					'section',
					blockProps,
					el('div', { className: 'lk-banners' },
						banners.map(function (banner, index) {
							return el('div', {
								key: index,
								className: 'lk-banner',
								style: banner.image ? {
									backgroundImage: 'url(' + banner.image + ')',
									backgroundSize: 'contain',
									backgroundPosition: 'center',
									backgroundRepeat: 'no-repeat'
								} : undefined,
								title: banner.title || banner.label || ''
							});
						})
					)
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-article-grid', {
		title: __('گرید مقالات', 'hello-elementor-child'),
		description: __('آخرین نوشته‌ها و مقالات آموزشی.', 'hello-elementor-child'),
		icon: 'welcome-write-blog',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: 'lk-homepage-block container' });

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('گرید مقالات', 'hello-elementor-child'), initialOpen: true },
						el(TextControl, {
							label: __('عنوان', 'hello-elementor-child'),
							value: attributes.title || '',
							onChange: function (value) { setAttributes({ title: value }); }
						}),
						el(RangeControl, {
							label: __('تعداد مقالات', 'hello-elementor-child'),
							min: 1,
							max: 3,
							value: attributes.limit || 3,
							onChange: function (value) { setAttributes({ limit: value }); }
						}),
						el(TextControl, {
							label: __('متن دکمه', 'hello-elementor-child'),
							value: attributes.ctaText || '',
							onChange: function (value) { setAttributes({ ctaText: value }); }
						}),
						el(TextControl, {
							label: __('لینک دکمه', 'hello-elementor-child'),
							value: attributes.ctaUrl || '',
							onChange: function (value) { setAttributes({ ctaUrl: value }); }
						})
					)
				),
				el(
					'section',
					blockProps,
					el('h2', null, attributes.title || blockLabel(__('گرید مقالات', 'hello-elementor-child'))),
					el('p', null, __('در فرانت‌اند آخرین نوشته‌ها نمایش داده می‌شوند.', 'hello-elementor-child'))
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-seo-box', {
		title: __('باکس متن اسکرول‌شو', 'hello-elementor-child'),
		description: __('باکس متن پایین صفحه با اسکرول داخلی.', 'hello-elementor-child'),
		icon: 'editor-justify',
		edit: function (props) {
			var blockProps = useBlockProps({ className: 'lk-homepage-block container' });
			return el(
				'section',
				blockProps,
				el('div', { className: 'lk-seo-box', style: { maxHeight: '160px', overflow: 'auto', padding: '14px', border: '1px solid #e5e7eb', borderRadius: '12px', background: '#fff' } },
					el('strong', null, __('باکس متن اسکرول‌شو', 'hello-elementor-child')),
					el('p', null, __('متن پیش‌فرض صفحه اصلی (مثل المنتور) در فرانت‌اند نمایش داده می‌شود.', 'hello-elementor-child'))
				)
			);
		}
	});
})(window.wp);
