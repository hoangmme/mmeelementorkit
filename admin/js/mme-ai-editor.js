/**
 * MMe AI in the Elementor editor.
 *
 * Adds an "MMe AI" button to every "add section" area of the preview. The
 * dialog starts a generation (mme_kit_ai_start), polls it (mme_kit_ai_poll)
 * and inserts the returned elements at the position of the clicked area with
 * Elementor's own import command, so the result is one Undo step and nothing
 * is saved until the page is updated.
 */
( function ( $, Backbone ) {
	'use strict';

	var data = window.MmeKitAi;
	if ( ! data ) {
		return;
	}
	var t = data.i18n;
	var POLL_MS = 3000;
	var MAX_WAIT_MS = 300000;

	var state = {
		at: null,
		$addSection: null,
		mode: 'elementor',
		kind: 'auto',
		busy: false,
		cancelled: false,
		result: null,
		usage: data.usage || {},
	};
	var ui = {};

	function format( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return text.replace( /%(\d\$)?[sd]/g, function ( match, position ) {
			var index = position ? parseInt( position, 10 ) - 1 : i++;
			return args[ index ] !== undefined ? args[ index ] : match;
		} );
	}

	/* ------------------------------------------------------------ button */

	function buttonHtml() {
		return '<div class="elementor-add-section-area-button mme-ai-add-btn" role="button" tabindex="0" title="' +
			_.escape( t.button ) + '" aria-label="' + _.escape( t.button ) + '"><img alt="" src="' + _.escape( data.icon ) + '"></div>';
	}

	function injectButtons() {
		var $preview = elementor.$previewContents;
		if ( ! $preview ) {
			return;
		}
		$preview.find( '.elementor-add-new-section' ).each( function () {
			var $area = $( this );
			if ( $area.find( '.mme-ai-add-btn' ).length ) {
				return;
			}
			var $templates = $area.find( '.ems-add-section-btn' );
			if ( $templates.length ) {
				$templates.last().after( buttonHtml() );
			} else {
				$area.prepend( buttonHtml() );
			}
		} );
	}

	/**
	 * Index among the page's top-level elements where the clicked area inserts:
	 * an inline area (opened from an element's "+") sits right before that element;
	 * the area at the end of the page appends (null).
	 */
	function insertIndex( $addSection ) {
		if ( ! $addSection.hasClass( 'elementor-add-section-inline' ) ) {
			return null;
		}
		var $next = $addSection.nextAll( '.elementor-element[data-id]' ).first();
		var id = $next.data( 'id' );
		var container = elementor.getPreviewContainer && elementor.getPreviewContainer();
		if ( ! id || ! container || ! container.children ) {
			return null;
		}
		for ( var i = 0; i < container.children.length; i++ ) {
			if ( container.children[ i ].id === id ) {
				return i;
			}
		}
		return null;
	}

	function watchPreview() {
		injectButtons();
		var body = elementor.$previewContents && elementor.$previewContents[ 0 ] && elementor.$previewContents[ 0 ].body;
		if ( ! body || body.__mmeAiObserved ) {
			return;
		}
		body.__mmeAiObserved = true;
		var timer = null;
		new MutationObserver( function () {
			clearTimeout( timer );
			timer = setTimeout( injectButtons, 60 );
		} ).observe( body, { childList: true, subtree: true } );

		elementor.$previewContents.on( 'click keydown', '.mme-ai-add-btn', function ( event ) {
			if ( 'keydown' === event.type && 'Enter' !== event.key && ' ' !== event.key ) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			var $addSection = $( this ).closest( '.elementor-add-section' );
			open( insertIndex( $addSection ), $addSection );
		} );
	}

	/* ------------------------------------------------------------ dialog */

	function build() {
		if ( ui.$modal ) {
			return;
		}
		var kinds = _.map( data.kinds, function ( label, key ) {
			return '<button type="button" class="mme-ai-chip' + ( 'auto' === key ? ' is-active' : '' ) + '" data-kind="' + _.escape( key ) + '">' + _.escape( label ) + '</button>';
		} ).join( '' );
		var examples = _.map( t.examples, function ( text ) {
			return '<button type="button" class="mme-ai-chip is-example">' + _.escape( text ) + '</button>';
		} ).join( '' );

		ui.$modal = $(
			'<div class="mme-ai-modal" hidden>' +
				'<div class="mme-ai-dialog" role="dialog" aria-modal="true" aria-labelledby="mme-ai-title">' +
					'<div class="mme-ai-head">' +
						'<img class="mme-ai-logo" alt="MMe" src="' + _.escape( data.logo ) + '">' +
						'<h2 id="mme-ai-title">' + _.escape( t.title ) + '</h2>' +
						'<span class="mme-ai-usage"></span>' +
						'<button type="button" class="mme-ai-x" aria-label="' + _.escape( t.close ) + '">×</button>' +
					'</div>' +
					'<div class="mme-ai-body">' +
						'<div class="mme-ai-form">' +
							'<div class="mme-ai-modes" role="radiogroup">' +
								'<button type="button" class="mme-ai-mode is-active" data-mode="elementor" role="radio" aria-checked="true"><strong>' + _.escape( t.modeElementor ) + '</strong><span>' + _.escape( t.modeElementorHint ) + '</span></button>' +
								'<button type="button" class="mme-ai-mode" data-mode="html" role="radio" aria-checked="false"' + ( data.canHtml ? '' : ' disabled' ) + '><strong>' + _.escape( t.modeHtml ) + '</strong><span>' + _.escape( t.modeHtmlHint ) + '</span></button>' +
							'</div>' +
						'</div>' +
						'<div class="mme-ai-form"><div class="mme-ai-label">' + _.escape( t.kind ) + '</div><div class="mme-ai-chips mme-ai-kinds">' + kinds + '</div></div>' +
						'<div class="mme-ai-form"><div class="mme-ai-label">' + _.escape( t.brief ) + '</div>' +
							'<textarea class="mme-ai-textarea" maxlength="4000" placeholder="' + _.escape( t.briefPlaceholder ) + '"></textarea>' +
							'<div class="mme-ai-chips mme-ai-examples" style="margin-top:8px">' + examples + '</div>' +
						'</div>' +
						'<label class="mme-ai-check mme-ai-form"><input type="checkbox" class="mme-ai-knowledge" checked> ' + _.escape( t.useKnowledge ) + '</label>' +
						'<div class="mme-ai-preview" hidden><iframe title="Preview" sandbox="allow-same-origin"></iframe></div>' +
						'<div class="mme-ai-status" aria-live="polite"></div>' +
					'</div>' +
					'<div class="mme-ai-foot">' +
						'<button type="button" class="mme-ai-btn mme-ai-cancel">' + _.escape( t.close ) + '</button>' +
						'<button type="button" class="mme-ai-btn mme-ai-retry" hidden>' + _.escape( t.regenerate ) + '</button>' +
						'<button type="button" class="mme-ai-btn is-primary mme-ai-insert" hidden>' + _.escape( t.insert ) + '</button>' +
						'<button type="button" class="mme-ai-btn is-primary mme-ai-go">' + _.escape( t.generate ) + '</button>' +
					'</div>' +
				'</div>' +
			'</div>'
		).appendTo( document.body );

		ui.$dialog = ui.$modal.find( '.mme-ai-dialog' );
		ui.$usage = ui.$modal.find( '.mme-ai-usage' );
		ui.$brief = ui.$modal.find( '.mme-ai-textarea' );
		ui.$status = ui.$modal.find( '.mme-ai-status' );
		ui.$preview = ui.$modal.find( '.mme-ai-preview' );
		ui.$form = ui.$modal.find( '.mme-ai-form' );
		ui.$go = ui.$modal.find( '.mme-ai-go' );
		ui.$retry = ui.$modal.find( '.mme-ai-retry' );
		ui.$insert = ui.$modal.find( '.mme-ai-insert' );

		ui.$modal.on( 'click', '.mme-ai-mode', function () {
			if ( state.busy || this.disabled ) {
				return;
			}
			state.mode = $( this ).data( 'mode' );
			ui.$modal.find( '.mme-ai-mode' ).removeClass( 'is-active' ).attr( 'aria-checked', 'false' );
			$( this ).addClass( 'is-active' ).attr( 'aria-checked', 'true' );
		} );
		ui.$modal.on( 'click', '.mme-ai-kinds .mme-ai-chip', function () {
			state.kind = $( this ).data( 'kind' );
			ui.$modal.find( '.mme-ai-kinds .mme-ai-chip' ).removeClass( 'is-active' );
			$( this ).addClass( 'is-active' );
		} );
		ui.$modal.on( 'click', '.mme-ai-examples .mme-ai-chip', function () {
			ui.$brief.val( $( this ).text() ).trigger( 'focus' );
		} );
		ui.$modal.on( 'click', '.mme-ai-x, .mme-ai-cancel', close );
		ui.$modal.on( 'click', function ( event ) {
			if ( event.target === ui.$modal[ 0 ] && ! state.busy ) {
				close();
			}
		} );
		ui.$modal.on( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				event.stopPropagation();
				close();
			}
		} );
		ui.$go.on( 'click', generate );
		ui.$retry.on( 'click', function () {
			showForm();
			generate();
		} );
		ui.$insert.on( 'click', function () {
			if ( state.result ) {
				insert( state.result );
			}
		} );
	}

	function renderUsage() {
		var usage = state.usage || {};
		if ( usage.quota ) {
			ui.$usage.text( format( t.usage, usage.used || 0, usage.quota ) ).show();
		} else {
			ui.$usage.hide();
		}
	}

	function setStatus( text, isError, withSettingsLink ) {
		ui.$status.toggleClass( 'is-error', !! isError ).text( text || '' );
		if ( withSettingsLink ) {
			ui.$status.append( ' ', $( '<a target="_blank" rel="noopener">' ).attr( 'href', data.settingsUrl ).text( t.settings ) );
		}
	}

	function showForm() {
		state.result = null;
		ui.$dialog.removeClass( 'has-preview' );
		ui.$preview.prop( 'hidden', true ).find( 'iframe' ).attr( 'srcdoc', '' );
		ui.$form.show();
		ui.$go.prop( 'hidden', false );
		ui.$retry.prop( 'hidden', true );
		ui.$insert.prop( 'hidden', true );
		setStatus( '' );
	}

	function open( at, $addSection ) {
		build();
		state.at = at;
		state.$addSection = $addSection;
		state.cancelled = false;
		if ( ! state.busy ) {
			showForm();
		}
		renderUsage();
		ui.$modal.prop( 'hidden', false );
		setTimeout( function () {
			ui.$brief.trigger( 'focus' );
		}, 30 );
	}

	function close() {
		state.cancelled = true;
		state.busy = false;
		setBusy( false );
		ui.$modal.prop( 'hidden', true );
	}

	function setBusy( busy ) {
		state.busy = busy;
		ui.$go.prop( 'disabled', busy );
		ui.$retry.prop( 'disabled', busy );
		ui.$insert.prop( 'disabled', busy );
		ui.$modal.find( '.mme-ai-mode, .mme-ai-chip, .mme-ai-knowledge' ).prop( 'disabled', busy );
		ui.$brief.prop( 'readonly', busy );
		if ( ! busy && ! data.canHtml ) {
			ui.$modal.find( '.mme-ai-mode[data-mode="html"]' ).prop( 'disabled', true );
		}
	}

	function request( action, extra ) {
		return $.ajax( {
			url: data.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: $.extend( {
				action: action,
				nonce: data.nonce,
				post_id: data.postId || ( elementor.config.document && elementor.config.document.id ) || 0,
				mode: state.mode,
			}, extra ),
		} );
	}

	function errorMessage( xhr ) {
		var json = xhr && xhr.responseJSON;
		return json && json.data && json.data.message ? json.data.message : t.failed;
	}

	function generate() {
		var brief = $.trim( ui.$brief.val() );
		if ( brief.length < 10 ) {
			setStatus( t.tooShort, true );
			return;
		}
		state.cancelled = false;
		setBusy( true );
		setStatus( t.starting );

		request( 'mme_kit_ai_start', {
			brief: brief,
			kind: state.kind,
			use_knowledge: ui.$modal.find( '.mme-ai-knowledge' ).is( ':checked' ) ? 1 : '',
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				fail( ( response && response.data && response.data.message ) || t.failed, true );
				return;
			}
			if ( response.data.usage ) {
				state.usage = response.data.usage;
				renderUsage();
			}
			poll( response.data.job, Date.now() );
		} ).fail( function ( xhr ) {
			fail( errorMessage( xhr ), true );
		} );
	}

	function poll( job, startedAt ) {
		if ( state.cancelled ) {
			return;
		}
		var elapsed = Date.now() - startedAt;
		if ( elapsed > MAX_WAIT_MS ) {
			fail( t.timeout );
			return;
		}
		setStatus( format( t.writing, Math.round( elapsed / 1000 ) ) );
		setTimeout( function () {
			if ( state.cancelled ) {
				return;
			}
			request( 'mme_kit_ai_poll', { job: job } ).done( function ( response ) {
				if ( state.cancelled ) {
					return;
				}
				if ( ! response || ! response.success ) {
					fail( ( response && response.data && response.data.message ) || t.failed );
					return;
				}
				if ( 'done' !== response.data.status ) {
					poll( job, startedAt );
					return;
				}
				done( response.data );
			} ).fail( function ( xhr ) {
				var json = xhr && xhr.responseJSON;
				if ( json && json.data && json.data.retry ) {
					poll( job, startedAt );
				} else {
					fail( errorMessage( xhr ) );
				}
			} );
		}, POLL_MS );
	}

	function fail( message, withSettingsLink ) {
		setBusy( false );
		setStatus( message, true, withSettingsLink );
	}

	function done( result ) {
		setBusy( false );
		if ( 'html' === state.mode && result.html ) {
			state.result = result;
			ui.$form.hide();
			ui.$dialog.addClass( 'has-preview' );
			ui.$preview.prop( 'hidden', false ).find( 'iframe' ).attr(
				'srcdoc',
				'<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
				'<style>body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}</style></head><body>' + result.html + '</body></html>'
			);
			ui.$go.prop( 'hidden', true );
			ui.$retry.prop( 'hidden', false );
			ui.$insert.prop( 'hidden', false );
			setStatus( t.previewHint );
			return;
		}
		insert( result );
	}

	/* ------------------------------------------------------------ insert */

	function insert( result ) {
		var elements = result.elements || [];
		if ( ! elements.length ) {
			setStatus( t.failed, true );
			return;
		}
		setStatus( t.building );
		var options = { edit: false };
		if ( null !== state.at && ! isNaN( state.at ) ) {
			options.at = state.at;
		}
		try {
			if ( window.$e && $e.run ) {
				$e.run( 'document/elements/import', {
					model: new Backbone.Model( { title: 'MMe AI: ' + ( result.title || '' ) } ),
					data: { content: elements },
					options: options,
				} );
			} else {
				elementor.previewView.addChildModel( elements, options );
			}
		} catch ( error ) {
			window.console && console.error( 'MMe AI insert failed', error );
			setStatus( t.failed, true );
			return;
		}

		// Close the inline "add section" box the button was clicked in.
		if ( state.$addSection && state.$addSection.hasClass( 'elementor-add-section-inline' ) ) {
			state.$addSection.find( '.elementor-add-section-close' ).trigger( 'click' );
		}
		close();
		toast( format( t.inserted, elements.length, result.title || '' ) );
	}

	function toast( message ) {
		var $toast = $( '<div class="mme-ai-toast" role="status">' ).text( message ).appendTo( document.body );
		setTimeout( function () {
			$toast.fadeOut( 300, function () {
				$toast.remove();
			} );
		}, 6000 );
	}

	/* ------------------------------------------------------------ boot */

	var booted = false;
	function boot() {
		if ( booted || ! window.elementor || ! elementor.on ) {
			return;
		}
		booted = true;
		elementor.on( 'document:loaded', watchPreview );
		elementor.on( 'preview:loaded', watchPreview );
		if ( elementor.$previewContents ) {
			watchPreview();
		}
	}
	$( window ).on( 'elementor:init', boot );
	boot();
} )( jQuery, window.Backbone );
