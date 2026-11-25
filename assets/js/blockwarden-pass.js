/**
 * BlockWarden invisible pass.
 *
 * Fetches a signed pass after the page loads and adds it as a hidden field to
 * the WooCommerce login, register and lost password forms. No jQuery, no UI.
 */
( function () {
	'use strict';

	var cfg = window.blockwardenData;
	if ( ! cfg || ! cfg.passUrl || ! window.fetch ) {
		return;
	}
	var SELECTOR = 'form.woocommerce-form-login, form.woocommerce-form-register, form.lost_reset_password, form.woocommerce-ResetPassword';
	var pass = '';

	function fill() {
		var forms = document.querySelectorAll( SELECTOR );
		for ( var i = 0; i < forms.length; i++ ) {
			var input = forms[ i ].querySelector( 'input[name="' + cfg.field + '"]' );
			if ( ! input ) {
				input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = cfg.field;
				forms[ i ].appendChild( input );
			}
			input.value = pass;
		}
	}

	function load() {
		var url = cfg.passUrl + ( cfg.passUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + '_=' + Date.now();
		window.fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( r ) { return r.ok ? r.json() : null; } )
			.then( function ( data ) {
				if ( data && data.pass ) {
					pass = data.pass;
					fill();
				}
			} )
			.catch( function () {} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', load );
	} else {
		load();
	}
	// Refresh before the pass expires on pages left open for a long time.
	window.setInterval( load, 6 * 60 * 60 * 1000 );
}() );
