( function ( $ ) {
	'use strict';

	function isMaskToken( character ) {
		return '0' === character || 'A' === character || '*' === character;
	}

	function matchesToken( character, token ) {
		if ( '0' === token ) {
			return /\d/.test( character );
		}

		if ( 'A' === token ) {
			return /[a-z]/i.test( character );
		}

		return /[a-z0-9]/i.test( character );
	}

	function meaningfulCharacters( value ) {
		return value.match( /[a-z0-9]/gi ) || [];
	}

	function applyMask( value, pattern ) {
		var source = meaningfulCharacters( value );
		var sourceIndex = 0;
		var output = '';

		for ( var i = 0; i < pattern.length; i++ ) {
			var patternCharacter = pattern.charAt( i );

			if ( isMaskToken( patternCharacter ) ) {
				while ( sourceIndex < source.length && ! matchesToken( source[ sourceIndex ], patternCharacter ) ) {
					sourceIndex++;
				}

				if ( sourceIndex >= source.length ) {
					break;
				}

				output += source[ sourceIndex ];
				sourceIndex++;
				continue;
			}

			if ( sourceIndex < source.length ) {
				output += patternCharacter;
			}
		}

		return output;
	}

	function cursorPositionAfterCharacters( value, characterCount ) {
		if ( ! characterCount ) {
			return 0;
		}

		var found = 0;
		var position = 0;

		while ( position < value.length && found < characterCount ) {
			if ( /[a-z0-9]/i.test( value.charAt( position ) ) ) {
				found++;
			}
			position++;
		}

		while ( position < value.length && ! /[a-z0-9]/i.test( value.charAt( position ) ) ) {
			position++;
		}

		return position;
	}

	function initializeCustomMask( input ) {
		var pattern = input.getAttribute( 'data-wa-mask-pattern' ) || '';

		if ( ! pattern || input.dataset.waMaskInitialized ) {
			return;
		}

		input.dataset.waMaskInitialized = '1';
		input.addEventListener( 'input', function () {
			var oldValue = input.value;
			var cursor = input.selectionStart || oldValue.length;
			var charactersBeforeCursor = meaningfulCharacters( oldValue.slice( 0, cursor ) ).length;
			var maskedValue = applyMask( oldValue, pattern );
			input.value = maskedValue;

			if ( document.activeElement === input && typeof input.setSelectionRange === 'function' ) {
				var nextPosition = cursorPositionAfterCharacters( maskedValue, charactersBeforeCursor );
				input.setSelectionRange( nextPosition, nextPosition );
			}
		} );

		if ( input.value ) {
			input.value = applyMask( input.value, pattern );
		}
	}

	function syncPhoneNumberForSubmit( input, iti ) {
		if ( ! iti || 'function' !== typeof iti.getNumber ) {
			return;
		}

		var internationalNumber = iti.getNumber();
		if ( internationalNumber ) {
			input.value = internationalNumber;
		}
	}

	function syncPhoneCountrySelectorColor( input ) {
		var container = input.closest( '.iti' );
		if ( ! container ) {
			return;
		}

		container.style.setProperty(
			'--wa-suit-phone-input-color',
			window.getComputedStyle( input ).color
		);
	}

	function initializePhoneMask( input ) {
		if ( input.dataset.waMaskInitialized ) {
			return;
		}

		if ( 'function' !== typeof window.intlTelInput ) {
			console.error( 'WA Suit: a biblioteca de formatação internacional de telefone não foi carregada.' );
			return;
		}

		input.dataset.waMaskInitialized = '1';
		input.setAttribute( 'inputmode', 'tel' );
		input.removeAttribute( 'maxlength' );
		var phoneSettings = window.waSuitFormMasks || {};
		var fieldMode = input.getAttribute( 'data-wa-phone-country-mode' ) || phoneSettings.phoneCountryMode || 'exclude';
		var fieldCountries = input.getAttribute( 'data-wa-phone-countries' );
		var configuredCountries = fieldCountries !== null
			? fieldCountries.split( ',' ).filter( Boolean )
			: ( Array.isArray( phoneSettings.phoneCountries ) ? phoneSettings.phoneCountries : [] );
		var initialCountry = input.getAttribute( 'data-wa-phone-default-country' ) || phoneSettings.phoneDefaultCountry || 'br';
		var phoneOptions = {
			initialCountry: initialCountry,
			countrySearch: true,
			separateDialCode: true,
			numberDisplayFormat: 'INTERNATIONAL',
			showFlags: true,
			formatAsYouType: true,
			strictMode: true,
			countryNameLocale: document.documentElement.lang || 'pt-BR',
			uiTranslations: {
				selectedCountryAriaLabel: 'Alterar o país do número de telefone. Selecionado: ${countryName} (${dialCode})',
				noCountrySelected: 'Selecionar o país do número de telefone',
				countryListAriaLabel: 'Lista de países',
				searchPlaceholder: 'Pesquisar',
				clearSearchAriaLabel: 'Limpar pesquisa',
				closeCountrySelectorAriaLabel: 'Fechar',
				searchEmptyState: 'Nenhum resultado encontrado',
				searchSummaryAria: function ( count ) {
					if ( 0 === count ) {
						return 'Nenhum resultado encontrado';
					}

					if ( 1 === count ) {
						return '1 resultado encontrado';
					}

					return count + ' resultados encontrados';
				}
			}
		};

		if ( configuredCountries.length ) {
			if ( 'only' === fieldMode ) {
				phoneOptions.onlyCountries = configuredCountries;
			} else if ( 'exclude' === fieldMode ) {
				phoneOptions.excludeCountries = configuredCountries;
			}
		}

		var iti = window.intlTelInput( input, phoneOptions );

		syncPhoneCountrySelectorColor( input );
		input.addEventListener( 'focus', function () {
			syncPhoneCountrySelectorColor( input );
		} );
		window.addEventListener( 'resize', function () {
			syncPhoneCountrySelectorColor( input );
		} );

		input.addEventListener( 'countrychange', function () {
			syncPhoneCountrySelectorColor( input );

			if ( ! iti || 'function' !== typeof iti.getNumber || 'function' !== typeof iti.setNumber ) {
				return;
			}

			var internationalNumber = iti.getNumber();
			if ( internationalNumber ) {
				iti.setNumber( internationalNumber );
			}
		} );

		if ( input.form ) {
			input.form.addEventListener( 'submit', function () {
				syncPhoneNumberForSubmit( input, iti );
			}, true );
		}
	}

	function initializeMasks( scope ) {
		var root = scope || document;
		var inputs = root.querySelectorAll( '[data-wa-mask-type]' );

		inputs.forEach( function ( input ) {
			var maskType = input.getAttribute( 'data-wa-mask-type' );

			if ( 'none' === maskType ) {
				return;
			}

			if ( 'custom' === maskType || 'pattern' === maskType ) {
				initializeCustomMask( input );
			} else if ( 'phone' === maskType ) {
				initializePhoneMask( input );
			}
		} );

	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initializeMasks( document );
		} );
	} else {
		initializeMasks( document );
	}

	$( window ).on( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/form.default', function ( $scope ) {
				initializeMasks( $scope[ 0 ] );
			} );
		}
	} );

	$( document ).on( 'elementor/popup/show', function () {
		initializeMasks( document );
	} );
} )( jQuery );
