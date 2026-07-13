
jQuery( function( $ )  {
	var settings = {
		init: function() {
			$( document ).on(
				'click',
				'#edd_cfp_license_deactivate, #edd_cfp_license_activate',
				settings.save_license
			);
		},

		save_license: function() {
			var license_key = $( '#edd_license_key_call_for_price' ).val();
			var action = 'Deactivate' === $( this ).val() ? 'cfp_deactivate_license' : 'cfp_activate_license';
			var key = 'Deactivate' === $( this ).val() ? $( '#edd_cfp_license_deactivate' ).val() : $( '#edd_cfp_license_activate' ).val();

			var data = { 
				action: action,
				edd_cfp_license_activate: key,
				edd_cfp_license_deactivate: key,
				license_key: license_key,
			};

			$.ajax({
				type: 'POST',
				url: localizeStrings.ajax_url,
				data: data,
				success: function( response ) {
					// Check the response
					if( 'valid' === response ) {
						// Hide the activate button and show the deactivate button
						$( '#edd_cfp_license_activate' ).hide();
						$( '#edd_cfp_license_deactivate' ).show();
						$('.mode-deactive').show();
						$('.mode-active').hide();
					} else {
						// Hide the deactivate button and show the activate button
						$( '#edd_cfp_license_deactivate' ).hide();
						$( '#edd_cfp_license_activate' ).show();
						$('.mode-active').show();
						$('.mode-deactive').hide();
					}
					window.location.reload();
				},
			});
		}
	}
	settings.init();
});