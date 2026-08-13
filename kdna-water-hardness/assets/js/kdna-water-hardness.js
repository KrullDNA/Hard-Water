/**
 * Water Hardness Lookup, front end.
 *
 * Vanilla JavaScript, no jQuery, no libraries. Every instance on the page is
 * wired up independently, so two of these on one page do not interfere.
 *
 * The client-side validation here is for immediate feedback only. The server
 * validates again and its answer is the one that counts.
 */
( function () {
	'use strict';

	var settings = window.kdnaWaterHardness || {};
	var countries = settings.countries || {};
	var strings = settings.strings || {};

	/**
	 * Escapes text before it goes anywhere near innerHTML. Zone names, utility
	 * names and messages all originate in imported data, so none of it is
	 * assumed safe.
	 *
	 * @param {string} value Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( value === null || value === undefined ? '' : String( value ) ) );
		return div.innerHTML;
	}

	/**
	 * Builds one instance.
	 *
	 * @param {Element} root The wrapper element.
	 */
	function setup( root ) {
		var form = root.querySelector( 'form' );
		var countryField = root.querySelector( '[data-kdna-wh-country]' );
		var input = root.querySelector( '[data-kdna-wh-input]' );
		var label = root.querySelector( '[data-kdna-wh-label]' );
		var errorEl = root.querySelector( '[data-kdna-wh-error]' );
		var resultEl = root.querySelector( '[data-kdna-wh-result]' );
		var button = root.querySelector( '[data-kdna-wh-submit]' );
		var buttonText = root.querySelector( '[data-kdna-wh-button-text]' );
		var busy = false;
		var originalButtonText = buttonText ? buttonText.textContent : '';

		if ( ! form || ! input || ! countryField ) {
			return;
		}

		/**
		 * The current country's field configuration.
		 *
		 * @return {Object} Configuration, or an empty object.
		 */
		function currentConfig() {
			return countries[ countryField.value ] || {};
		}

		/**
		 * Shows a validation message under the field.
		 *
		 * @param {string} message Text to show, or empty to clear it.
		 */
		function setError( message ) {
			if ( ! errorEl ) {
				return;
			}

			if ( message ) {
				errorEl.textContent = message;
				errorEl.hidden = false;
				input.setAttribute( 'aria-invalid', 'true' );
				root.classList.add( 'kdna-wh--has-error' );
			} else {
				errorEl.textContent = '';
				errorEl.hidden = true;
				input.removeAttribute( 'aria-invalid' );
				root.classList.remove( 'kdna-wh--has-error' );
			}
		}

		/**
		 * Redraws the field for the selected country: its label, example,
		 * length and the keyboard a phone should raise.
		 *
		 * Changing country clears whatever was typed, because a postcode
		 * valid in one country is rarely valid in the next and leaving it
		 * there only invites a confusing error.
		 *
		 * @param {boolean} clear Whether to clear the entered value.
		 */
		function applyCountry( clear ) {
			var config = currentConfig();

			if ( label && config.label ) {
				label.textContent = config.label;
			}

			input.placeholder = config.placeholder || '';
			input.setAttribute( 'inputmode', config.keyboard || 'text' );

			if ( config.maxlength ) {
				input.setAttribute( 'maxlength', String( config.maxlength ) );
			} else {
				input.removeAttribute( 'maxlength' );
			}

			if ( clear ) {
				input.value = '';
				setError( '' );
				hideResult();
			}
		}

		/**
		 * Whether what has been typed matches the country's pattern.
		 *
		 * @return {boolean} True when it looks like a postcode for this country.
		 */
		function isValid() {
			var config = currentConfig();
			var value = input.value.trim();

			if ( ! value ) {
				return false;
			}

			if ( ! config.pattern ) {
				return true;
			}

			try {
				return new RegExp( config.pattern ).test( value );
			} catch ( e ) {
				// A pattern the browser cannot compile must not block a
				// lookup. The server will validate it properly.
				return true;
			}
		}

		/**
		 * Hides the results panel.
		 */
		function hideResult() {
			if ( ! resultEl ) {
				return;
			}

			resultEl.hidden = true;
			resultEl.innerHTML = '';
		}

		/**
		 * Puts the form into or out of its loading state.
		 *
		 * @param {boolean} state Whether a lookup is running.
		 */
		function setBusy( state ) {
			busy = state;
			root.classList.toggle( 'kdna-wh--busy', state );

			if ( button ) {
				button.disabled = state;
				button.setAttribute( 'aria-busy', state ? 'true' : 'false' );
			}

			if ( buttonText && strings.loading ) {
				buttonText.textContent = state ? strings.loading : originalButtonText;
			}
		}

		/**
		 * Renders a finished lookup.
		 *
		 * @param {Object} result The response from the endpoint.
		 */
		function render( result ) {
			if ( ! resultEl ) {
				return;
			}

			// An invalid postcode belongs under the field with the other
			// validation, not in the results panel.
			if ( result.state === 'invalid' ) {
				hideResult();
				setError( result.message || strings.invalid );
				input.focus();
				return;
			}

			setError( '' );

			var html = '';

			if ( result.state === 'no_match' ) {
				html = '<div class="kdna-wh__message kdna-wh__message--no-match">' +
					'<p>' + escapeHtml( result.message ) + '</p>' +
					'</div>';
			} else {
				html = '<div class="kdna-wh__panel kdna-wh__panel--' + escapeHtml( result.state ) + '">';

				html += '<p class="kdna-wh__value">' +
					'<span class="kdna-wh__number">' + escapeHtml( result.value_display ) + '</span> ' +
					'<span class="kdna-wh__unit">' + escapeHtml( result.unit_label ) + '</span>' +
					'</p>';

				if ( result.state === 'range' && strings.spansZones ) {
					html += '<p class="kdna-wh__note">' + escapeHtml( strings.spansZones ) + '</p>';
				}

				if ( result.is_estimated && strings.estimated ) {
					html += '<p class="kdna-wh__note kdna-wh__note--estimated">' + escapeHtml( strings.estimated ) + '</p>';
				}

				if ( result.source_summary ) {
					html += '<p class="kdna-wh__meta">' + escapeHtml( result.source_summary ) + '</p>';
				}

				html += renderZones( result );
				html += '</div>';
			}

			resultEl.innerHTML = html;
			resultEl.hidden = false;
		}

		/**
		 * The per-zone detail lines, naming the zone, its utility and the
		 * report the figure came from.
		 *
		 * @param {Object} result The response from the endpoint.
		 * @return {string} Markup.
		 */
		function renderZones( result ) {
			if ( ! result.zones || ! result.zones.length ) {
				return '';
			}

			var html = '<ul class="kdna-wh__zones">';

			result.zones.forEach( function ( zone ) {
				html += '<li class="kdna-wh__zone">';
				html += '<span class="kdna-wh__zone-name">' + escapeHtml( zone.zone_name ) + '</span>';
				html += ' <span class="kdna-wh__zone-value">' + escapeHtml( zone.value_display ) + ' ' + escapeHtml( result.unit_label ) + '</span>';

				if ( zone.utility_name ) {
					html += ' <span class="kdna-wh__zone-utility">' + escapeHtml( zone.utility_name ) + '</span>';
				}

				// Only http and https links are rendered. The URL comes from
				// imported data, and anything else does not belong in an href.
				if ( zone.source_url && /^https?:\/\//i.test( zone.source_url ) ) {
					html += ' <a class="kdna-wh__zone-source" href="' + escapeHtml( zone.source_url ) + '" target="_blank" rel="noopener noreferrer nofollow">' +
						escapeHtml( zone.source_date_display || strings.sourceLabel || 'Source' ) +
						'</a>';
				}

				html += '</li>';
			} );

			return html + '</ul>';
		}

		/**
		 * Asks the server.
		 */
		function submit() {
			if ( busy ) {
				return;
			}

			var value = input.value.trim();

			if ( ! value ) {
				setError( strings.required || '' );
				input.focus();
				return;
			}

			if ( ! isValid() ) {
				var config = currentConfig();
				var message = strings.invalid || '';

				if ( config.placeholder ) {
					message += ' ' + config.placeholder;
				}

				setError( message.trim() );
				input.focus();
				return;
			}

			setError( '' );
			setBusy( true );

			var url = settings.endpoint +
				( settings.endpoint.indexOf( '?' ) === -1 ? '?' : '&' ) +
				'country=' + encodeURIComponent( countryField.value ) +
				'&postcode=' + encodeURIComponent( value );

			var headers = { Accept: 'application/json' };

			// Sent so a logged-in visitor is recognised as themselves rather
			// than as a logged-out one. The endpoint is public either way.
			if ( settings.nonce ) {
				headers[ 'X-WP-Nonce' ] = settings.nonce;
			}

			window.fetch( url, {
				method: 'GET',
				headers: headers,
				credentials: 'same-origin'
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'HTTP ' + response.status );
					}

					return response.json();
				} )
				.then( function ( result ) {
					render( result );
				} )
				.catch( function () {
					hideResult();
					setError( strings.failed || '' );
				} )
				.then( function () {
					setBusy( false );
				} );
		}

		countryField.addEventListener( 'change', function () {
			applyCountry( true );
		} );

		// Clear a stale error as soon as the visitor starts fixing it.
		input.addEventListener( 'input', function () {
			if ( errorEl && ! errorEl.hidden ) {
				setError( '' );
			}
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			submit();
		} );

		applyCountry( false );
	}

	/**
	 * Wires up every instance on the page.
	 */
	function init() {
		var roots = document.querySelectorAll( '[data-kdna-wh]' );

		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.dataset.kdnaWhReady ) {
				return;
			}

			root.dataset.kdnaWhReady = '1';
			setup( root );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	/*
	 * Elementor rebuilds a widget's markup when it is edited, so the editor
	 * needs to re-run this against the new DOM. Harmless on the front end.
	 */
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', init );
		}
	} );
}() );
