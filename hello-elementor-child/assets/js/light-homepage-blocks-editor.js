(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var apiFetch = wp.apiFetch;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var Button = wp.components.Button;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var BaseControl = wp.components.BaseControl;
	var SelectControl = wp.components.SelectControl;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var __ = wp.i18n.__;

	function registerHomepageBlock(name, settings) {
		var seeded = (window.lookazmaHomepageBlockAttributes && window.lookazmaHomepageBlockAttributes[name]) || {};
		var existing = typeof wp.blocks.getBlockType === 'function' ? wp.blocks.getBlockType(name) : null;
		var attributes = Object.assign({}, existing && existing.attributes, seeded, settings.attributes);
		if (existing) {
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
				settings,
				{ attributes: attributes }
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

	function renderHeroSlidesEditor(items, setAttributes) {
		return el(
			'div',
			{ className: 'lk-lk-editor-panel' },
			el('h4', null, __('اسلایدها', 'hello-elementor-child')),
			(items || []).map(function (item, index) {
				var chooseLabel = item.url
					? __('تغییر تصویر', 'hello-elementor-child')
					: __('انتخاب تصویر', 'hello-elementor-child');
				var picker = MediaUpload
					? el(MediaUpload, {
						onSelect: function (media) {
							var patch = { url: (media && (media.url || media.source_url)) || '' };
							if (media && media.alt && !(item.alt || '').trim()) {
								patch.alt = media.alt;
							}
							updateListItem(items, index, patch, setAttributes, 'slides');
						},
						allowedTypes: ['image'],
						render: function (obj) {
							return el(
								Button,
								{ variant: 'secondary', onClick: obj.open },
								chooseLabel
							);
						}
					})
					: null;
				if (picker && MediaUploadCheck) {
					picker = el(MediaUploadCheck, null, picker);
				}
				return el(
					'div',
					{ key: index, className: 'lk-lk-editor-row' },
					el(
						BaseControl,
						{ label: __('تصویر', 'hello-elementor-child') },
						item.url
							? el('img', {
								src: item.url,
								alt: '',
								style: {
									display: 'block',
									width: '100%',
									height: '88px',
									objectFit: 'cover',
									borderRadius: '8px',
									marginBottom: '8px',
									background: '#eaf4ff'
								}
							})
							: null,
						el(
							'div',
							{ style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
							picker,
							item.url
								? el(
									Button,
									{
										isDestructive: true,
										variant: 'tertiary',
										onClick: function () {
											updateListItem(items, index, { url: '' }, setAttributes, 'slides');
										}
									},
									__('حذف تصویر', 'hello-elementor-child')
								)
								: null
						)
					),
					el(TextControl, {
						label: __('متن جایگزین', 'hello-elementor-child'),
						value: item.alt || '',
						onChange: function (value) {
							updateListItem(items, index, { alt: value }, setAttributes, 'slides');
						}
					}),
					el(
						Button,
						{
							isDestructive: true,
							variant: 'secondary',
							onClick: function () {
								removeListItem(items, index, setAttributes, 'slides');
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
						addListItem(items, { url: '', alt: '' }, setAttributes, 'slides');
					}
				},
				__('افزودن', 'hello-elementor-child')
			)
		);
	}

	function renderBrandLogosEditor(items, setAttributes) {
		return el(
			'div',
			{ className: 'lk-lk-editor-panel' },
			el('h4', null, __('برندها', 'hello-elementor-child')),
			(items || []).map(function (item, index) {
				var chooseLabel = item.image
					? __('تغییر لوگو', 'hello-elementor-child')
					: __('انتخاب لوگو', 'hello-elementor-child');
				var picker = MediaUpload
					? el(MediaUpload, {
						onSelect: function (media) {
							var patch = { image: (media && (media.url || media.source_url)) || '' };
							if (media && media.alt && !(item.alt || '').trim()) {
								patch.alt = media.alt;
							}
							updateListItem(items, index, patch, setAttributes, 'brands');
						},
						allowedTypes: ['image'],
						render: function (obj) {
							return el(
								Button,
								{ variant: 'secondary', onClick: obj.open },
								chooseLabel
							);
						}
					})
					: null;
				if (picker && MediaUploadCheck) {
					picker = el(MediaUploadCheck, null, picker);
				}
				return el(
					'div',
					{ key: index, className: 'lk-lk-editor-row' },
					el(
						BaseControl,
						{ label: __('لوگو', 'hello-elementor-child') },
						item.image
							? el('img', {
								src: item.image,
								alt: '',
								style: {
									display: 'block',
									width: '100%',
									height: '72px',
									objectFit: 'contain',
									borderRadius: '8px',
									marginBottom: '8px',
									background: '#fff',
									border: '1px solid #e5e7eb'
								}
							})
							: null,
						el(
							'div',
							{ style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
							picker,
							item.image
								? el(
									Button,
									{
										isDestructive: true,
										variant: 'tertiary',
										onClick: function () {
											updateListItem(items, index, { image: '' }, setAttributes, 'brands');
										}
									},
									__('حذف لوگو', 'hello-elementor-child')
								)
								: null
						)
					),
					el(TextControl, {
						label: __('نام برند', 'hello-elementor-child'),
						value: item.alt || '',
						onChange: function (value) {
							updateListItem(items, index, { alt: value }, setAttributes, 'brands');
						}
					}),
					el(TextControl, {
						label: __('لینک', 'hello-elementor-child'),
						value: item.link || '',
						onChange: function (value) {
							updateListItem(items, index, { link: value }, setAttributes, 'brands');
						}
					}),
					el(
						Button,
						{
							isDestructive: true,
							variant: 'secondary',
							onClick: function () {
								removeListItem(items, index, setAttributes, 'brands');
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
						addListItem(items, { image: '', alt: '', link: '' }, setAttributes, 'brands');
					}
				},
				__('افزودن', 'hello-elementor-child')
			)
		);
	}

	function useProductCategories() {
		var state = useState(null);
		var terms = state[0];
		var setTerms = state[1];
		useEffect(function () {
			var active = true;
			if (!apiFetch) {
				setTerms([]);
				return undefined;
			}
			apiFetch({ path: '/lookazma/v1/product-cats' }).then(function (list) {
				if (active) {
					setTerms(Array.isArray(list) ? list : []);
				}
			}).catch(function () {
				if (active) {
					setTerms([]);
				}
			});
			return function () {
				active = false;
			};
		}, []);
		return terms;
	}

	function catCardSource(attributes) {
		var source = attributes.source || 'latest';
		var ids = (attributes.categoryIds || []).filter(function (id) { return !!id; });
		if ('random' !== source && ids.length) {
			return 'picked';
		}
		return source;
	}

	function CatCardPicker(props) {
		var categoryIds = props.categoryIds;
		var setCategoryIds = props.onChange;
		var count = props.count;
		var terms = useProductCategories();
		var options = [
			{ label: __('انتخاب دسته', 'hello-elementor-child'), value: '0' }
		];
		(terms || []).forEach(function (term) {
			options.push({ label: term.name, value: String(term.id) });
		});

		function choose(index, value) {
			var next = (categoryIds || []).slice();
			while (next.length < count) {
				next.push(0);
			}
			next = next.slice(0, count);
			next[index] = parseInt(value, 10) || 0;
			setCategoryIds(next);
		}

		return (function () {
			var cards = [];
			var i;
			for (i = 0; i < count; i++) {
				cards.push(i);
			}
			return el(
				'div',
				{ className: 'lk-cat-cards__grid' },
				cards.map(function (index) {
					var selected = String((categoryIds && categoryIds[index]) || 0);
					var chosen = (terms || []).filter(function (term) {
						return String(term.id) === selected;
					})[0];
					return el(
						'div',
						{ key: index, className: 'lk-cat-card' },
						el('span', { className: 'lk-cat-card__media' }),
						el(
							'span',
							{ className: 'lk-cat-card__body' },
							el('strong', { className: 'lk-cat-card__name' }, chosen ? chosen.name : __('انتخاب دسته', 'hello-elementor-child')),
							el(SelectControl, {
								label: __('کارت', 'hello-elementor-child') + ' ' + (index + 1),
								value: selected,
								options: options,
								onChange: function (value) {
									choose(index, value);
								},
								disabled: terms === null
							})
						)
					);
				})
			);
		})();
	}

	function useProducts() {
		var state = useState(null);
		var products = state[0];
		var setProducts = state[1];
		useEffect(function () {
			var active = true;
			if (!apiFetch) {
				setProducts([]);
				return undefined;
			}
			apiFetch({ path: '/lookazma/v1/products' }).then(function (list) {
				if (active) {
					setProducts(Array.isArray(list) ? list : []);
				}
			}).catch(function () {
				if (active) {
					setProducts([]);
				}
			});
			return function () {
				active = false;
			};
		}, []);
		return products;
	}

	function ProductCardPicker(props) {
		var productIds = props.productIds;
		var setProductIds = props.onChange;
		var count = props.count;
		var products = useProducts();
		var options = [
			{ label: __('انتخاب محصول', 'hello-elementor-child'), value: '0' }
		];
		(products || []).forEach(function (product) {
			options.push({ label: product.name, value: String(product.id) });
		});

		function choose(index, value) {
			var next = (productIds || []).slice();
			while (next.length < count) {
				next.push(0);
			}
			next = next.slice(0, count);
			next[index] = parseInt(value, 10) || 0;
			setProductIds(next);
		}

		var cards = [];
		var i;
		for (i = 0; i < count; i++) {
			cards.push(i);
		}
		return el(
			'div',
			{ className: 'lk-cat-cards__grid', style: { marginTop: '1rem' } },
			cards.map(function (index) {
				var selected = String((productIds && productIds[index]) || 0);
				var chosen = (products || []).filter(function (product) {
					return String(product.id) === selected;
				})[0];
				return el(
					'div',
					{ key: index, className: 'lk-cat-card' },
					el(
						'span',
						{ className: 'lk-cat-card__body' },
						el('strong', { className: 'lk-cat-card__name' }, chosen ? chosen.name : __('انتخاب محصول', 'hello-elementor-child')),
						el(SelectControl, {
							label: __('کارت', 'hello-elementor-child') + ' ' + (index + 1),
							value: selected,
							options: options,
							onChange: function (value) {
								choose(index, value);
							},
							disabled: products === null
						})
					)
				);
			})
		);
	}

	function usePosts() {
		var state = useState(null);
		var posts = state[0];
		var setPosts = state[1];
		useEffect(function () {
			var active = true;
			if (!apiFetch) {
				setPosts([]);
				return undefined;
			}
			apiFetch({ path: '/lookazma/v1/posts' }).then(function (list) {
				if (active) {
					setPosts(Array.isArray(list) ? list : []);
				}
			}).catch(function () {
				if (active) {
					setPosts([]);
				}
			});
			return function () {
				active = false;
			};
		}, []);
		return posts;
	}

	function ArticleCardPicker(props) {
		var postIds = props.postIds;
		var setPostIds = props.onChange;
		var count = props.count;
		var posts = usePosts();
		var options = [
			{ label: __('انتخاب مقاله', 'hello-elementor-child'), value: '0' }
		];
		(posts || []).forEach(function (post) {
			options.push({ label: post.name, value: String(post.id) });
		});

		function choose(index, value) {
			var next = (postIds || []).slice();
			while (next.length < count) {
				next.push(0);
			}
			next = next.slice(0, count);
			next[index] = parseInt(value, 10) || 0;
			setPostIds(next);
		}

		var cards = [];
		var i;
		for (i = 0; i < count; i++) {
			cards.push(i);
		}
		return el(
			'div',
			{ className: 'lk-cat-cards__grid', style: { marginTop: '1rem' } },
			cards.map(function (index) {
				var selected = String((postIds && postIds[index]) || 0);
				var chosen = (posts || []).filter(function (post) {
					return String(post.id) === selected;
				})[0];
				return el(
					'div',
					{ key: index, className: 'lk-cat-card' },
					el(
						'span',
						{ className: 'lk-cat-card__body' },
						el('strong', { className: 'lk-cat-card__name' }, chosen ? chosen.name : __('انتخاب مقاله', 'hello-elementor-child')),
						el(SelectControl, {
							label: __('کارت', 'hello-elementor-child') + ' ' + (index + 1),
							value: selected,
							options: options,
							onChange: function (value) {
								choose(index, value);
							},
							disabled: posts === null
						})
					)
				);
			})
		);
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
						el(ToggleControl, {
							label: __('هیرو تمام‌عرض با تصویر پس‌زمینه', 'hello-elementor-child'),
							help: __('تصویر هیرو کل باکس را می‌پوشاند (نسبت ۵:۲، مثلاً ۱۹۸۳×۷۹۳). با روشن بودن، اسلاید برندهایی که همین گزینه را دارند هم به اسلایدر اضافه می‌شود.', 'hello-elementor-child'),
							checked: !!attributes.coverHero,
							onChange: function (value) {
								setAttributes({ coverHero: !!value });
							}
						}),
						renderHeroSlidesEditor(slides, setAttributes)
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
		attributes: {
			autoplayMs: { type: 'number', default: 3000 }
		},
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
						el(RangeControl, {
							label: __('زمان حرکت خودکار (ثانیه)', 'hello-elementor-child'),
							min: 1,
							max: 15,
							step: 1,
							value: Math.max(1, Math.round((attributes.autoplayMs || 3000) / 1000)),
							onChange: function (value) {
								setAttributes({ autoplayMs: Math.max(1, value || 3) * 1000 });
							}
						}),
						renderBrandLogosEditor(brands, setAttributes)
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

	registerHomepageBlock('lookazma/home-cat-cards', {
		title: __('کارت دسته‌ها', 'hello-elementor-child'),
		description: __('کارت سفید دسته با تصویر، نام فارسی، نام انگلیسی و CAS.', 'hello-elementor-child'),
		icon: 'category',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps({ className: 'lk-section lk-glass-card lk-cat-cards lk-homepage-block container' });
			var count = Math.max(1, Math.min(12, attributes.limit || 6));
			var catSource = catCardSource(attributes);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('کارت دسته‌ها', 'hello-elementor-child'), initialOpen: true },
						el(SelectControl, {
							label: __('نمایش دسته‌ها', 'hello-elementor-child'),
							value: catSource,
							options: [
								{ label: __('دسته‌های دارای CAS', 'hello-elementor-child'), value: 'latest' },
								{ label: __('انتخاب تصادفی', 'hello-elementor-child'), value: 'random' },
								{ label: __('دسته‌های انتخابی', 'hello-elementor-child'), value: 'picked' }
							],
							onChange: function (value) {
								setAttributes({
									source: value,
									categoryIds: value === 'picked' ? (attributes.categoryIds || []) : []
								});
							}
						}),
						el(RangeControl, {
							label: __('تعداد کارت', 'hello-elementor-child'),
							min: 1,
							max: 12,
							value: count,
							onChange: function (value) {
								setAttributes({ limit: value });
							}
						})
					)
				),
				el(
					'section',
					blockProps,
					el('div', { className: 'lk-section-head lk-cat-cards__head' },
						el('h2', null, __('مواد شیمیایی پرجستجو', 'hello-elementor-child')),
						el('p', null, __('محبوب‌ترین مواد شیمیایی در بین مشتریان لوک‌آزما', 'hello-elementor-child'))
					),
					(catSource === 'picked')
						? el(CatCardPicker, {
							categoryIds: attributes.categoryIds || [],
							count: count,
							onChange: function (next) {
								setAttributes({ categoryIds: next });
							}
						})
						: null
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
			var isChemical = (attributes.title || 'محصولات مواد شیمیایی') === 'محصولات مواد شیمیایی';
			var blockProps = useBlockProps({
				className: isChemical
					? 'lk-section lk-glass-card lk-cat-cards lk-homepage-block container'
					: 'lk-homepage-block container'
			});
			var cardCount = Math.max(1, Math.min(12, attributes.limit || 6));
			var catSource = catCardSource(attributes);

			if (isChemical) {
				return el(
					Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __('کارت دسته‌ها', 'hello-elementor-child'), initialOpen: true },
							el(SelectControl, {
								label: __('نمایش دسته‌ها', 'hello-elementor-child'),
								value: catSource,
								options: [
									{ label: __('دسته‌های دارای CAS', 'hello-elementor-child'), value: 'latest' },
									{ label: __('انتخاب تصادفی', 'hello-elementor-child'), value: 'random' },
									{ label: __('دسته‌های انتخابی', 'hello-elementor-child'), value: 'picked' }
								],
								onChange: function (value) {
								setAttributes({
									source: value,
									categoryIds: value === 'picked' ? (attributes.categoryIds || []) : []
								});
							}
							}),
							el(RangeControl, {
								label: __('تعداد کارت', 'hello-elementor-child'),
								min: 1,
								max: 12,
								value: cardCount,
								onChange: function (value) { setAttributes({ limit: value }); }
							})
						)
					),
					el(
						'section',
						blockProps,
						el('div', { className: 'lk-section-head lk-cat-cards__head' },
							el('h2', null, __('مواد شیمیایی پرجستجو', 'hello-elementor-child')),
							el('p', null, __('محبوب‌ترین مواد شیمیایی در بین مشتریان لوک‌آزما', 'hello-elementor-child'))
						),
						(catSource === 'picked')
							? el(CatCardPicker, {
								categoryIds: attributes.categoryIds || [],
								count: cardCount,
								onChange: function (next) {
									setAttributes({ categoryIds: next });
								}
							})
							: null
					)
				);
			}

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('گرید محصولات', 'hello-elementor-child'), initialOpen: true },
						el(SelectControl, {
							label: __('نمایش محصولات', 'hello-elementor-child'),
							value: attributes.source || 'latest',
							options: [
								{ label: __('آخرین محصولات اضافه شده', 'hello-elementor-child'), value: 'latest' },
								{ label: __('انتخاب تصادفی', 'hello-elementor-child'), value: 'random' },
								{ label: __('محصولات انتخابی', 'hello-elementor-child'), value: 'picked' }
							],
							onChange: function (value) { setAttributes({ source: value }); }
						}),
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
					el('p', null, __('در فرانت‌اند محصولات ووکامرس نمایش داده می‌شوند.', 'hello-elementor-child')),
					(attributes.source === 'picked')
						? el(ProductCardPicker, {
							productIds: attributes.productIds || [],
							count: cardCount,
							onChange: function (next) {
								setAttributes({ productIds: next });
							}
						})
						: null
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
						el(SelectControl, {
							label: __('نمایش مقالات', 'hello-elementor-child'),
							value: attributes.source || 'latest',
							options: [
								{ label: __('آخرین مقالات اضافه شده', 'hello-elementor-child'), value: 'latest' },
								{ label: __('انتخاب تصادفی', 'hello-elementor-child'), value: 'random' },
								{ label: __('مقالات انتخابی', 'hello-elementor-child'), value: 'picked' }
							],
							onChange: function (value) { setAttributes({ source: value }); }
						}),
						el(TextControl, {
							label: __('عنوان', 'hello-elementor-child'),
							value: attributes.title || '',
							onChange: function (value) { setAttributes({ title: value }); }
						}),
						el(RangeControl, {
							label: __('تعداد مقالات', 'hello-elementor-child'),
							min: 1,
							max: 10,
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
					el('p', null, __('در فرانت‌اند مقالات نمایش داده می‌شوند.', 'hello-elementor-child')),
					(attributes.source === 'picked')
						? el(ArticleCardPicker, {
							postIds: attributes.postIds || [],
							count: Math.max(1, Math.min(10, attributes.limit || 3)),
							onChange: function (next) {
								setAttributes({ postIds: next });
							}
						})
						: null
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-why', {
		title: __('چرا لوک آزما؟', 'hello-elementor-child'),
		description: __('کارت‌های مزیت، معرفی و تعهدها.', 'hello-elementor-child'),
		icon: 'star-filled',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var image = attributes.image || '';
			var blockProps = useBlockProps({ className: 'lk-homepage-block container' });
			var chooseLabel = image
				? __('تغییر تصویر', 'hello-elementor-child')
				: __('انتخاب تصویر', 'hello-elementor-child');
			var picker = MediaUpload
				? el(MediaUpload, {
					onSelect: function (media) {
						setAttributes({ image: (media && (media.url || media.source_url)) || '' });
					},
					allowedTypes: ['image'],
					render: function (obj) {
						return el(Button, { variant: 'secondary', onClick: obj.open }, chooseLabel);
					}
				})
				: null;
			if (picker && MediaUploadCheck) {
				picker = el(MediaUploadCheck, null, picker);
			}

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('چرا لوک آزما؟', 'hello-elementor-child'), initialOpen: true },
						el(
							BaseControl,
							{ label: __('تصویر لوک آزما، تأمین هوشمند برای صنعتی پیشرو', 'hello-elementor-child') },
							image
								? el('img', {
									src: image,
									alt: '',
									style: {
										display: 'block',
										width: '100%',
										height: '120px',
										objectFit: 'cover',
										borderRadius: '8px',
										marginBottom: '8px'
									}
								})
								: null,
							el(
								'div',
								{ style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
								picker,
								image
									? el(Button, {
										isDestructive: true,
										variant: 'tertiary',
										onClick: function () { setAttributes({ image: '' }); }
									}, __('حذف تصویر', 'hello-elementor-child'))
									: null
							)
						)
					)
				),
				el(
					'section',
					blockProps,
					el('h2', null, __('چرا لوک آزما؟', 'hello-elementor-child')),
					el('p', null, __('لوک آزما، تأمین هوشمند برای صنعتی پیشرو', 'hello-elementor-child'))
				)
			);
		}
	});

	registerHomepageBlock('lookazma/home-seo-box', {
		title: __('باکس متن اسکرول‌شو', 'hello-elementor-child'),
		description: __('باکس متن پایین صفحه با اسکرول داخلی.', 'hello-elementor-child'),
		icon: 'editor-justify',
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
						{ title: __('باکس متن اسکرول‌شو', 'hello-elementor-child'), initialOpen: true },
						el(TextareaControl, {
							label: __('متن', 'hello-elementor-child'),
							rows: 12,
							value: attributes.content || '',
							onChange: function (value) { setAttributes({ content: value }); }
						})
					)
				),
				el(
					'section',
					blockProps,
					el('div', { className: 'lk-seo-box', style: { maxHeight: '160px', overflow: 'auto', padding: '14px', border: '1px solid #e5e7eb', borderRadius: '12px', background: '#fff' } },
						el('strong', null, __('باکس متن اسکرول‌شو', 'hello-elementor-child'))
					)
				)
			);
		}
	});
})(window.wp);
