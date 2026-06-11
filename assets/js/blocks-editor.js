(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element || !wp.i18n) {
		return;
	}

	var blockEditor = wp.blockEditor || wp.editor;

	if (!blockEditor || !blockEditor.useBlockProps || !wp.components || !wp.data) {
		// eslint-disable-next-line no-console
		console.warn('АРТ ЛМС: block editor dependencies are not available.');
		return;
	}

	var registerBlockType = wp.blocks.registerBlockType;
	var getBlockType = wp.blocks.getBlockType;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var PanelBody = wp.components.PanelBody;
	var Spinner = wp.components.Spinner;
	var BaseControl = wp.components.BaseControl;
	var Button = wp.components.Button;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;
	var editorBlockHint = __('Этот блок откроется на фронтенде. Здесь — только настройки.', 'art-lms');
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;

	var defaultButtonText = __('Оформить', 'art-lms');

	var paymentButtonDesignDefaults = {
		buttonAlign: 'center',
		buttonFontSize: 0,
		buttonTextColor: '',
		buttonBackgroundColor: '',
		buttonBorderRadius: 0,
	};

	var paymentButtonDesignFallbacks = {
		fontSize: 16,
		textColor: '#ffffff',
		backgroundColor: '#2563eb',
		borderRadius: 6,
	};

	var customerAccountBorderFallbacks = {
		color: '#e8ecf1',
		radius: 10,
	};

	var customerAccountSectionTitleFallbacks = {
		fontSize: 16,
	};

	var customerAccountWidthDefaults = {
		mode: 'theme',
		customWidth: 640,
	};

	var paymentButtonAlignOptions = [
		{ label: __('Слева', 'art-lms'), value: 'left' },
		{ label: __('По центру', 'art-lms'), value: 'center' },
		{ label: __('Справа', 'art-lms'), value: 'right' },
	];

	var paymentButtonPostType = 'art_lms_pay_button';
	var paymentButtonQuery = {
		per_page: 100,
		orderby: 'title',
		order: 'asc',
		status: ['publish'],
	};

	var saveNull = function () {
		return null;
	};

	function registerDynamicBlock(name, settings) {
		var existing = getBlockType(name);
		var merged = existing ? Object.assign({}, existing, settings) : settings;

		registerBlockType(name, merged);
	}

	function SimpleBlockEdit(props) {
		var blockProps = useBlockProps({
			className: 'art-lms-editor-shell',
		});

		return el(
			'div',
			blockProps,
			el('div', { className: 'art-lms-editor-title' }, props.title),
			el('div', { className: 'art-lms-editor-hint' }, props.description)
		);
	}

	function getButtonText(attributes) {
		var text = (attributes.buttonText || '').toString().trim();

		return text || defaultButtonText;
	}

	function getPaymentButtonFontSizeValue(attributes) {
		var size = parseInt(attributes.buttonFontSize, 10);

		return size > 0 ? size : paymentButtonDesignFallbacks.fontSize;
	}

	function getPaymentButtonBorderRadiusValue(attributes) {
		var radius = parseInt(attributes.buttonBorderRadius, 10);

		return radius > 0 ? radius : paymentButtonDesignFallbacks.borderRadius;
	}

	function resetPaymentButtonDesign(setAttributes) {
		setAttributes(paymentButtonDesignDefaults);
	}

	function renderPaymentButtonColorControl(label, attributeKey, fallbackColor, attributes, setAttributes) {
		var value = attributes[attributeKey] || fallbackColor;
		var defaultValue = paymentButtonDesignDefaults[attributeKey] || '';

		return el(
			BaseControl,
			{ label: label, className: 'art-lms-editor-color-control' },
			el(
				'div',
				{ className: 'art-lms-editor-color-control__row' },
				el(
					'span',
					{ className: 'art-lms-editor-color-control__picker-wrap' },
					el('input', {
						type: 'color',
						className: 'art-lms-editor-color-control__picker',
						value: value,
						onChange: function (event) {
							var patch = {};
							patch[attributeKey] = event.target.value;
							setAttributes(patch);
						},
					})
				),
				el(TextControl, {
					value: attributes[attributeKey] || '',
					placeholder: fallbackColor,
					onChange: function (nextValue) {
						var patch = {};
						patch[attributeKey] = nextValue;
						setAttributes(patch);
					},
				}),
				el(
					Button,
					{
						variant: 'secondary',
						onClick: function () {
							var patch = {};
							patch[attributeKey] = defaultValue;
							setAttributes(patch);
						},
					},
					__('Сбросить', 'art-lms')
				)
			)
		);
	}

	function PaymentButtonBlockEdit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps({
			className: 'art-lms-editor-shell art-lms-editor-shell--payment-button',
		});

		var buttons = useSelect(function (select) {
			return select('core').getEntityRecords('postType', paymentButtonPostType, paymentButtonQuery);
		}, []);

		var isLoading = useSelect(function (select) {
			return !select('core/data').hasFinishedResolution('core', 'getEntityRecords', [
				'postType',
				paymentButtonPostType,
				paymentButtonQuery,
			]);
		}, []);

		var selectedButton = useSelect(
			function (select) {
				if (!attributes.buttonId) {
					return null;
				}

				return select('core').getEntityRecord('postType', paymentButtonPostType, attributes.buttonId);
			},
			[attributes.buttonId]
		);

		var options = [
			{
				label: __('— Выберите кнопку —', 'art-lms'),
				value: '0',
			},
		];

		if (buttons && buttons.length) {
			buttons.forEach(function (button) {
				var title =
					button.title && button.title.rendered
						? button.title.rendered
						: '#' + button.id;

				options.push({
					label: title,
					value: String(button.id),
				});
			});
		}

		var selectedButtonName = '';

		if (selectedButton && selectedButton.title && selectedButton.title.rendered) {
			selectedButtonName = selectedButton.title.rendered;
		}

		var buttonText = getButtonText(attributes);
		var emptyValue = '—';
		var editorHint = editorBlockHint;
		var displayButtonName = attributes.buttonId && selectedButtonName ? selectedButtonName : emptyValue;
		var displayButtonText = attributes.buttonId ? buttonText : emptyValue;

		if (isLoading) {
			editorHint = __('Загружаем список платежных кнопок…', 'art-lms');
		} else if (!buttons || !buttons.length) {
			editorHint = __('Сначала создайте платежную кнопку в разделе ART LMS.', 'art-lms');
		}

		function renderDetailRow(label, value) {
			return el(
				'div',
				{ className: 'art-lms-editor-detail' },
				el('span', { className: 'art-lms-editor-detail__label' }, label + ': '),
				el('span', { className: 'art-lms-editor-detail__value' }, value)
			);
		}

		var previewContent = el(
			Fragment,
			null,
			el('div', { className: 'art-lms-editor-title' }, __('АРТ ЛМС: Платежная кнопка', 'art-lms')),
			el('div', { className: 'art-lms-editor-hint' }, editorHint),
			isLoading
				? el(Spinner, null)
				: el(
						'div',
						{ className: 'art-lms-editor-details' },
						renderDetailRow(__('Выбранная кнопка', 'art-lms'), displayButtonName),
						renderDetailRow(__('Текст кнопки', 'art-lms'), displayButtonText)
				  )
		);

		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __('Настройки', 'art-lms'), initialOpen: true },
					el(SelectControl, {
						label: __('Платежная кнопка', 'art-lms'),
						value: String(attributes.buttonId || 0),
						options: options,
						onChange: function (value) {
							setAttributes({ buttonId: parseInt(value, 10) || 0 });
						},
					}),
					el(TextControl, {
						label: __('Текст кнопки', 'art-lms'),
						value: attributes.buttonText || defaultButtonText,
						onChange: function (value) {
							setAttributes({ buttonText: value });
						},
					}),
					el(ToggleControl, {
						label: __('Скрыть название товара', 'art-lms'),
						checked: !!attributes.hideProductName,
						onChange: function (value) {
							setAttributes({ hideProductName: !!value });
						},
					}),
					el(ToggleControl, {
						label: __('Скрыть старую цену', 'art-lms'),
						checked: !!attributes.hideComparePrice,
						disabled: !!attributes.hidePrice,
						onChange: function (value) {
							setAttributes({ hideComparePrice: !!value });
						},
					}),
					el(ToggleControl, {
						label: __('Скрыть цену', 'art-lms'),
						checked: !!attributes.hidePrice,
						onChange: function (value) {
							setAttributes({
								hidePrice: !!value,
								hideComparePrice: value ? true : attributes.hideComparePrice,
							});
						},
					})
				)
			),
			el(
				InspectorControls,
				{ group: 'styles' },
				el(
					PanelBody,
					{ title: __('Дизайн кнопки', 'art-lms'), initialOpen: true },
					el(SelectControl, {
						label: __('Расположение кнопки', 'art-lms'),
						value: attributes.buttonAlign || paymentButtonDesignDefaults.buttonAlign,
						options: paymentButtonAlignOptions,
						onChange: function (value) {
							setAttributes({ buttonAlign: value || paymentButtonDesignDefaults.buttonAlign });
						},
					}),
					el(
						BaseControl,
						{
							label: __('Размер текста кнопки', 'art-lms'),
							className: 'art-lms-editor-font-size-control',
						},
						el(
							'div',
							{ className: 'art-lms-editor-font-size-control__row' },
							el(TextControl, {
								type: 'number',
								min: 10,
								max: 32,
								value: String(getPaymentButtonFontSizeValue(attributes)),
								onChange: function (value) {
									var size = parseInt(value, 10);

									if (isNaN(size)) {
										return;
									}

									size = Math.max(10, Math.min(32, size));

									setAttributes({
										buttonFontSize: size === paymentButtonDesignFallbacks.fontSize ? 0 : size,
									});
								},
							}),
							el(
								Button,
								{
									variant: 'secondary',
									onClick: function () {
										resetPaymentButtonDesign(setAttributes);
									},
								},
								__('Сбросить', 'art-lms')
							)
						)
					),
					renderPaymentButtonColorControl(
						__('Цвет текста кнопки', 'art-lms'),
						'buttonTextColor',
						paymentButtonDesignFallbacks.textColor,
						attributes,
						setAttributes
					),
					renderPaymentButtonColorControl(
						__('Цвет фона кнопки', 'art-lms'),
						'buttonBackgroundColor',
						paymentButtonDesignFallbacks.backgroundColor,
						attributes,
						setAttributes
					),
					el(
						BaseControl,
						{
							label: __('Скругление углов кнопки', 'art-lms'),
							className: 'art-lms-editor-font-size-control',
						},
						el(
							'div',
							{ className: 'art-lms-editor-font-size-control__row' },
							el(TextControl, {
								type: 'number',
								min: 0,
								max: 32,
								value: String(getPaymentButtonBorderRadiusValue(attributes)),
								onChange: function (value) {
									var radius = parseInt(value, 10);

									if (isNaN(radius)) {
										return;
									}

									radius = Math.max(0, Math.min(32, radius));

									setAttributes({
										buttonBorderRadius: radius === paymentButtonDesignFallbacks.borderRadius ? 0 : radius,
									});
								},
							}),
							el(
								Button,
								{
									variant: 'secondary',
									onClick: function () {
										setAttributes({
											buttonBorderRadius: paymentButtonDesignDefaults.buttonBorderRadius,
										});
									},
								},
								__('Сбросить', 'art-lms')
							)
						)
					)
				)
			),
			el('div', blockProps, previewContent)
		);
	}

	function CustomerAccountBlockEdit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps({
			className: 'art-lms-editor-shell',
		});

		var customerAccountDefaults = {
			materialsTitle: __('Ваши материалы', 'art-lms'),
			emptyMessage: __(
				'Пока нет доступных материалов. После оплаты они появятся здесь автоматически.',
				'art-lms'
			),
			openButtonText: __('Открыть', 'art-lms'),
			logoutLinkText: __('Выйти', 'art-lms'),
			resetPasswordLinkText: __('Сменить пароль', 'art-lms'),
			hideMaterialsTitle: false,
			hideAccessLabel: false,
			hideOpenButton: false,
			hideLogoutLink: false,
			hideResetPassword: false,
			containerWidthMode: customerAccountWidthDefaults.mode,
			containerCustomWidth: customerAccountWidthDefaults.customWidth,
			hideBorder: false,
			borderColor: '',
			borderRadius: 0,
			buttonFontSize: 0,
			buttonTextColor: '',
			buttonBackgroundColor: '',
			buttonBorderRadius: 0,
		};

		var customerAccountDesignDefaults = {
			containerWidthMode: customerAccountWidthDefaults.mode,
			containerCustomWidth: customerAccountWidthDefaults.customWidth,
			hideBorder: false,
			borderColor: '',
			borderRadius: 0,
			materialsTitleFontSize: 0,
			buttonFontSize: 0,
			buttonTextColor: '',
			buttonBackgroundColor: '',
			buttonBorderRadius: 0,
		};

		var containerWidthModeOptions = [
			{ label: __('По умолчанию (ширина темы)', 'art-lms'), value: 'theme' },
			{ label: __('На всю ширину', 'art-lms'), value: 'full' },
			{ label: __('Произвольная', 'art-lms'), value: 'custom' },
		];

		function getAccountButtonFontSizeValue(attrs) {
			var size = parseInt(attrs.buttonFontSize, 10);

			return size > 0 ? size : paymentButtonDesignFallbacks.fontSize;
		}

		function getAccountMaterialsTitleFontSizeValue(attrs) {
			var size = parseInt(attrs.materialsTitleFontSize, 10);

			return size > 0 ? size : customerAccountSectionTitleFallbacks.fontSize;
		}

		function getAccountButtonBorderRadiusValue(attrs) {
			var radius = parseInt(attrs.buttonBorderRadius, 10);

			return radius > 0 ? radius : paymentButtonDesignFallbacks.borderRadius;
		}

		function getAccountBorderRadiusValue(attrs) {
			var radius = parseInt(attrs.borderRadius, 10);

			return radius > 0 ? radius : customerAccountBorderFallbacks.radius;
		}

		function getContainerWidthMode(attrs) {
			var mode = attrs.containerWidthMode || customerAccountWidthDefaults.mode;

			if (mode === 'full' || mode === 'custom' || mode === 'theme') {
				return mode;
			}

			return customerAccountWidthDefaults.mode;
		}

		function getContainerCustomWidthValue(attrs) {
			var width = parseInt(attrs.containerCustomWidth, 10);

			if (!isNaN(width) && width > 0) {
				return width;
			}

			return customerAccountWidthDefaults.customWidth;
		}

		function resetCustomerAccountDesign() {
			setAttributes(customerAccountDesignDefaults);
		}

		function resetCustomerAccountWidth() {
			setAttributes({
				containerWidthMode: customerAccountWidthDefaults.mode,
				containerCustomWidth: customerAccountWidthDefaults.customWidth,
			});
		}

		function renderDetailRow(label, value) {
			return el(
				'div',
				{ className: 'art-lms-editor-detail' },
				el('span', { className: 'art-lms-editor-detail__label' }, label + ': '),
				el('span', { className: 'art-lms-editor-detail__value' }, value)
			);
		}

		var widthMode = getContainerWidthMode(attributes);
		var widthLabel =
			containerWidthModeOptions.find(function (option) {
				return option.value === widthMode;
			}) || containerWidthModeOptions[0];

		if (widthMode === 'custom') {
			widthLabel = {
				label: widthLabel.label + ' (' + getContainerCustomWidthValue(attributes) + 'px)',
			};
		}

		var previewContent = el(
			Fragment,
			null,
			el('div', { className: 'art-lms-editor-title' }, __('АРТ ЛМС: Личный кабинет', 'art-lms')),
			el(
				'div',
				{ className: 'art-lms-editor-hint' },
				editorBlockHint
			),
			el(
				'div',
				{ className: 'art-lms-editor-details' },
				renderDetailRow(__('Материалы', 'art-lms'), attributes.materialsTitle || customerAccountDefaults.materialsTitle),
				renderDetailRow(__('Ширина блока', 'art-lms'), widthLabel.label)
			)
		);

		return el(
			Fragment,
			null,
			el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __('Настройки', 'art-lms'), initialOpen: true },
					el(TextControl, {
						label: __('Заголовок списка материалов', 'art-lms'),
						value: attributes.materialsTitle || customerAccountDefaults.materialsTitle,
						onChange: function (value) {
							setAttributes({ materialsTitle: value });
						},
					}),
					el(ToggleControl, {
						label: __('Скрыть заголовок списка материалов', 'art-lms'),
						checked: !!attributes.hideMaterialsTitle,
						onChange: function (value) {
							setAttributes({ hideMaterialsTitle: !!value });
						},
					}),
					el(TextControl, {
						label: __('Сообщение при пустом списке', 'art-lms'),
						value: attributes.emptyMessage || customerAccountDefaults.emptyMessage,
						onChange: function (value) {
							setAttributes({ emptyMessage: value });
						},
					}),
					el(TextControl, {
						label: __('Текст кнопки «Открыть»', 'art-lms'),
						value: attributes.openButtonText || customerAccountDefaults.openButtonText,
						onChange: function (value) {
							setAttributes({ openButtonText: value });
						},
					}),
					el(ToggleControl, {
						label: __('Скрыть срок доступа', 'art-lms'),
						checked: !!attributes.hideAccessLabel,
						onChange: function (value) {
							setAttributes({ hideAccessLabel: !!value });
						},
					}),
					el(ToggleControl, {
						label: __('Скрыть кнопку «Открыть»', 'art-lms'),
						checked: !!attributes.hideOpenButton,
						onChange: function (value) {
							setAttributes({ hideOpenButton: !!value });
						},
					}),
					el(TextControl, {
						label: __('Текст ссылки «Выйти»', 'art-lms'),
						value: attributes.logoutLinkText || customerAccountDefaults.logoutLinkText,
						onChange: function (value) {
							setAttributes({ logoutLinkText: value });
						},
					}),
					el(ToggleControl, {
						label: __('Скрыть ссылку «Выйти»', 'art-lms'),
						checked: !!attributes.hideLogoutLink,
						onChange: function (value) {
							setAttributes({ hideLogoutLink: !!value });
						},
					}),
					el(TextControl, {
						label: __('Текст ссылки смены пароля', 'art-lms'),
						value: attributes.resetPasswordLinkText || customerAccountDefaults.resetPasswordLinkText,
						onChange: function (value) {
							setAttributes({ resetPasswordLinkText: value });
						},
					}),
					el(ToggleControl, {
						label: __('Скрыть ссылку смены пароля', 'art-lms'),
						checked: !!attributes.hideResetPassword,
						onChange: function (value) {
							setAttributes({ hideResetPassword: !!value });
						},
					})
				)
			),
			el(
				InspectorControls,
				{ group: 'styles' },
				el(
					PanelBody,
					{ title: __('Дизайн', 'art-lms'), initialOpen: true },
					el(SelectControl, {
						label: __('Ширина блока', 'art-lms'),
						value: widthMode,
						options: containerWidthModeOptions,
						onChange: function (value) {
							setAttributes({
								containerWidthMode: value || customerAccountDesignDefaults.containerWidthMode,
							});
						},
					}),
					widthMode === 'custom'
						? el(
								BaseControl,
								{
									label: __('Ширина в пикселях', 'art-lms'),
									className: 'art-lms-editor-font-size-control',
								},
								el(
									'div',
									{ className: 'art-lms-editor-font-size-control__row' },
									el(TextControl, {
										type: 'number',
										min: 240,
										max: 1600,
										value: String(getContainerCustomWidthValue(attributes)),
										onChange: function (value) {
											var width = parseInt(value, 10);

											if (isNaN(width)) {
												return;
											}

											width = Math.max(240, Math.min(1600, width));
											setAttributes({ containerCustomWidth: width });
										},
									}),
									el(
										Button,
										{
											variant: 'secondary',
											onClick: resetCustomerAccountWidth,
										},
										__('Сбросить', 'art-lms')
									)
								)
						  )
						: null,
					el(ToggleControl, {
						label: __('Скрыть рамку', 'art-lms'),
						checked: !!attributes.hideBorder,
						onChange: function (value) {
							setAttributes({ hideBorder: !!value });
						},
					}),
					!attributes.hideBorder
						? renderPaymentButtonColorControl(
								__('Цвет рамки', 'art-lms'),
								'borderColor',
								customerAccountBorderFallbacks.color,
								attributes,
								setAttributes
						  )
						: null,
					!attributes.hideBorder
						? el(
								BaseControl,
								{
									label: __('Скругление рамки', 'art-lms'),
									className: 'art-lms-editor-font-size-control',
								},
								el(
									'div',
									{ className: 'art-lms-editor-font-size-control__row' },
									el(TextControl, {
										type: 'number',
										min: 0,
										max: 32,
										value: String(getAccountBorderRadiusValue(attributes)),
										onChange: function (value) {
											var radius = parseInt(value, 10);

											if (isNaN(radius)) {
												return;
											}

											radius = Math.max(0, Math.min(32, radius));

											setAttributes({
												borderRadius:
													radius === customerAccountBorderFallbacks.radius ? 0 : radius,
											});
										},
									}),
									el(
										Button,
										{
											variant: 'secondary',
											onClick: function () {
												setAttributes({
													borderRadius: customerAccountDesignDefaults.borderRadius,
												});
											},
										},
										__('Сбросить', 'art-lms')
									)
								)
						  )
						: null,
					el(
						BaseControl,
						{
							label: __('Размер текста заголовка материалов', 'art-lms'),
							className: 'art-lms-editor-font-size-control',
						},
						el(
							'div',
							{ className: 'art-lms-editor-font-size-control__row' },
							el(TextControl, {
								type: 'number',
								min: 12,
								max: 32,
								value: String(getAccountMaterialsTitleFontSizeValue(attributes)),
								onChange: function (value) {
									var size = parseInt(value, 10);

									if (isNaN(size)) {
										return;
									}

									size = Math.max(12, Math.min(32, size));

									setAttributes({
										materialsTitleFontSize:
											size === customerAccountSectionTitleFallbacks.fontSize ? 0 : size,
									});
								},
							}),
							el(
								Button,
								{
									variant: 'secondary',
									onClick: function () {
										setAttributes({
											materialsTitleFontSize: customerAccountDesignDefaults.materialsTitleFontSize,
										});
									},
								},
								__('Сбросить', 'art-lms')
							)
						)
					),
					el(
						BaseControl,
						{
							label: __('Размер текста кнопок', 'art-lms'),
							className: 'art-lms-editor-font-size-control',
						},
						el(
							'div',
							{ className: 'art-lms-editor-font-size-control__row' },
							el(TextControl, {
								type: 'number',
								min: 10,
								max: 32,
								value: String(getAccountButtonFontSizeValue(attributes)),
								onChange: function (value) {
									var size = parseInt(value, 10);

									if (isNaN(size)) {
										return;
									}

									size = Math.max(10, Math.min(32, size));

									setAttributes({
										buttonFontSize: size === paymentButtonDesignFallbacks.fontSize ? 0 : size,
									});
								},
							}),
							el(
								Button,
								{
									variant: 'secondary',
									onClick: resetCustomerAccountDesign,
								},
								__('Сбросить', 'art-lms')
							)
						)
					),
					renderPaymentButtonColorControl(
						__('Цвет текста кнопок', 'art-lms'),
						'buttonTextColor',
						paymentButtonDesignFallbacks.textColor,
						attributes,
						setAttributes
					),
					renderPaymentButtonColorControl(
						__('Цвет фона кнопок', 'art-lms'),
						'buttonBackgroundColor',
						paymentButtonDesignFallbacks.backgroundColor,
						attributes,
						setAttributes
					),
					el(
						BaseControl,
						{
							label: __('Скругление углов кнопок', 'art-lms'),
							className: 'art-lms-editor-font-size-control',
						},
						el(
							'div',
							{ className: 'art-lms-editor-font-size-control__row' },
							el(TextControl, {
								type: 'number',
								min: 0,
								max: 32,
								value: String(getAccountButtonBorderRadiusValue(attributes)),
								onChange: function (value) {
									var radius = parseInt(value, 10);

									if (isNaN(radius)) {
										return;
									}

									radius = Math.max(0, Math.min(32, radius));

									setAttributes({
										buttonBorderRadius:
											radius === paymentButtonDesignFallbacks.borderRadius ? 0 : radius,
									});
								},
							}),
							el(
								Button,
								{
									variant: 'secondary',
									onClick: function () {
										setAttributes({
											buttonBorderRadius: customerAccountDesignDefaults.buttonBorderRadius,
										});
									},
								},
								__('Сбросить', 'art-lms')
							)
						)
					)
				)
			),
			el('div', blockProps, previewContent)
		);
	}

	function registerBlocks() {
		registerDynamicBlock('art-lms/customer-account', {
			apiVersion: 3,
			title: __('АРТ ЛМС: Личный кабинет', 'art-lms'),
			category: 'art-lms',
			icon: 'admin-users',
			description: __('Личный кабинет покупателя.', 'art-lms'),
			keywords: ['art', 'lms', 'art lms', 'кабинет', 'account', 'личный кабинет'],
			attributes: {
				materialsTitle: {
					type: 'string',
					default: __('Ваши материалы', 'art-lms'),
				},
				emptyMessage: {
					type: 'string',
					default: __(
						'Пока нет доступных материалов. После оплаты они появятся здесь автоматически.',
						'art-lms'
					),
				},
				openButtonText: {
					type: 'string',
					default: __('Открыть', 'art-lms'),
				},
				logoutLinkText: {
					type: 'string',
					default: __('Выйти', 'art-lms'),
				},
				resetPasswordLinkText: {
					type: 'string',
					default: __('Сменить пароль', 'art-lms'),
				},
				hideMaterialsTitle: {
					type: 'boolean',
					default: false,
				},
				hideAccessLabel: {
					type: 'boolean',
					default: false,
				},
				hideOpenButton: {
					type: 'boolean',
					default: false,
				},
				hideLogoutLink: {
					type: 'boolean',
					default: false,
				},
				hideResetPassword: {
					type: 'boolean',
					default: false,
				},
				containerWidthMode: {
					type: 'string',
					default: 'theme',
				},
				containerCustomWidth: {
					type: 'number',
					default: 640,
				},
				hideBorder: {
					type: 'boolean',
					default: false,
				},
				borderColor: {
					type: 'string',
					default: '',
				},
				borderRadius: {
					type: 'number',
					default: 0,
				},
				materialsTitleFontSize: {
					type: 'number',
					default: 0,
				},
				buttonFontSize: {
					type: 'number',
					default: 0,
				},
				buttonTextColor: {
					type: 'string',
					default: '',
				},
				buttonBackgroundColor: {
					type: 'string',
					default: '',
				},
				buttonBorderRadius: {
					type: 'number',
					default: 0,
				},
			},
			supports: {
				html: false,
				multiple: false,
			},
			edit: CustomerAccountBlockEdit,
			save: saveNull,
		});

		registerDynamicBlock('art-lms/payment-status', {
			apiVersion: 3,
			title: __('АРТ ЛМС: Статус оплаты', 'art-lms'),
			category: 'art-lms',
			icon: 'yes-alt',
			description: __('Блок проверки оплаты для страницы «Спасибо за покупку».', 'art-lms'),
			keywords: ['art', 'lms', 'art lms', 'оплата', 'статус', 'success'],
			supports: {
				html: false,
				multiple: false,
			},
			edit: function () {
				return SimpleBlockEdit({
					title: __('АРТ ЛМС: Статус оплаты', 'art-lms'),
					description: editorBlockHint,
				});
			},
			save: saveNull,
		});

		registerDynamicBlock('art-lms/payment-button', {
			apiVersion: 3,
			title: __('АРТ ЛМС: Платежная кнопка', 'art-lms'),
			category: 'art-lms',
			icon: 'money-alt',
			description: __('Кнопка оплаты с переходом на checkout.', 'art-lms'),
			keywords: ['art', 'lms', 'art lms', 'кнопка', 'оплата', 'payment', 'checkout'],
			attributes: {
				buttonId: {
					type: 'number',
					default: 0,
				},
				buttonText: {
					type: 'string',
					default: defaultButtonText,
				},
				hideProductName: {
					type: 'boolean',
					default: true,
				},
				hideComparePrice: {
					type: 'boolean',
					default: false,
				},
				hidePrice: {
					type: 'boolean',
					default: true,
				},
				buttonAlign: {
					type: 'string',
					default: paymentButtonDesignDefaults.buttonAlign,
				},
				buttonFontSize: {
					type: 'number',
					default: paymentButtonDesignDefaults.buttonFontSize,
				},
				buttonTextColor: {
					type: 'string',
					default: paymentButtonDesignDefaults.buttonTextColor,
				},
				buttonBackgroundColor: {
					type: 'string',
					default: paymentButtonDesignDefaults.buttonBackgroundColor,
				},
				buttonBorderRadius: {
					type: 'number',
					default: paymentButtonDesignDefaults.buttonBorderRadius,
				},
			},
			supports: {
				html: false,
				multiple: true,
			},
			edit: PaymentButtonBlockEdit,
			save: saveNull,
		});
	}

	if (wp.domReady) {
		wp.domReady(registerBlocks);
	} else {
		registerBlocks();
	}
})(window.wp);
