/**
 * Registers the CyberSource Secure Acceptance gateway with the
 * WooCommerce Cart/Checkout blocks.
 *
 * Uses globals provided by WooCommerce Blocks (no build step required):
 *   wc.wcSettings, wc.wcBlocksRegistry, wp.element, wp.htmlEntities
 */
( function () {
	'use strict';

	var settings = window.wc.wcSettings.getSetting( 'hosted_card_payments_data', {} );
	var decode = window.wp.htmlEntities.decodeEntities;
	var createElement = window.wp.element.createElement;

	var label = decode( settings.title ) || 'Credit/Debit Card';

	var Content = function () {
		return decode( settings.description || '' );
	};

	var Label = function ( props ) {
		var PaymentMethodLabel = props.components.PaymentMethodLabel;
		var labelEl = createElement( PaymentMethodLabel, { text: label } );

		// Show the gateway icon (if one ships with the plugin) next to the label.
		if ( settings.icon ) {
			return createElement(
				'span',
				{
					style: {
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'space-between',
						width: '100%',
					},
				},
				labelEl,
				createElement( 'img', {
					src: settings.icon,
					alt: label,
					style: { maxHeight: '24px', marginLeft: 'auto' },
				} )
			);
		}

		return labelEl;
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'hosted_card_payments',
		label: createElement( Label, null ),
		content: createElement( Content, null ),
		edit: createElement( Content, null ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
