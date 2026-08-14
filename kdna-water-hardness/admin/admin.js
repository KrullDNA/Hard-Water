/**
 * Admin behaviour for the Water Hardness screens.
 *
 * Two small jobs only: loading an existing source link into the edit form,
 * and confirming a deletion before it happens. Everything else on these
 * screens is plain form posts, so the pages still work with this file blocked.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		/*
		 * Clicking Edit on a source link copies its values into that country's
		 * form, so saving updates the existing link rather than adding another.
		 */
		document.querySelectorAll( '.kdna-wh-edit-link' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var panel = button.closest( '.kdna-wh-panel' );

				if ( ! panel ) {
					return;
				}

				var form = panel.querySelector( '.kdna-wh-link-form' );

				if ( ! form ) {
					return;
				}

				var fields = {
					'.kdna-wh-link-id': button.dataset.id,
					'.kdna-wh-link-label': button.dataset.label,
					'.kdna-wh-link-url': button.dataset.url,
					'.kdna-wh-link-region': button.dataset.region,
					'.kdna-wh-link-data-date': button.dataset.dataDate,
					'.kdna-wh-link-last-checked': button.dataset.lastChecked
				};

				Object.keys( fields ).forEach( function ( selector ) {
					var input = form.querySelector( selector );

					if ( input ) {
						input.value = fields[ selector ] || '';
					}
				} );

				var label = form.querySelector( '.kdna-wh-link-label' );

				if ( label ) {
					label.focus();
				}
			} );
		} );

		/*
		 * Deleting data cannot be undone, so every delete button asks first.
		 * The message comes from the button itself, so each one can be
		 * specific about what is about to go.
		 */
		document.querySelectorAll( '.kdna-wh-confirm' ).forEach( function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				if ( ! window.confirm( button.dataset.confirm ) ) {
					event.preventDefault();
				}
			} );
		} );

	} );
}() );
