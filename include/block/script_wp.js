(function()
{
	var el = wp.element.createElement,
		registerBlockType = wp.blocks.registerBlockType,
		TextControl = wp.components.TextControl,
		InspectorControls = wp.blockEditor.InspectorControls;

	registerBlockType('mf/bankidlogin',
	{
		title: script_bankid_block_wp.block_title,
		description: script_bankid_block_wp.block_description,
		icon: 'lock',
		category: 'widgets',
		'attributes':
		{
			'align':
			{
				'type': 'string',
				'default': ''
			},
			'bankid_return_url':
			{
				'type': 'string',
				'default': ''
			}
		},
		'supports':
		{
			'html': false,
			'multiple': false,
			'align': true,
			'spacing':
			{
				'margin': true,
				'padding': true
			},
			'color':
			{
				'background': true,
				'gradients': false,
				'text': true
			},
			'defaultStylePicker': true,
			'typography':
			{
				'fontSize': true,
				'lineHeight': true
			},
			"__experimentalBorder":
			{
				"radius": true
			}
		},
		edit: function(props)
		{
			return el(
				'div',
				{className: 'wp_mf_block_container'},
				[
					el(
						InspectorControls,
						'div',
						el(
							TextControl,
							{
								label: script_bankid_block_wp.bankid_return_url_label,
								type: 'url',
								value: props.attributes.bankid_return_url,
								onChange: function(value)
								{
									props.setAttributes({bankid_return_url: value});
								}
							}
						)
					),
					el(
						'strong',
						{className: props.className},
						script_bankid_block_wp.block_title
					)
				]
			);
		},
		save: function()
		{
			return null;
		}
	});
})();