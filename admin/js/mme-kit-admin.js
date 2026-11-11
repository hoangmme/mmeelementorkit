(function( $ ) {
	'use strict';

	/**
	 * Admin scripts for the Master and Client pages.
	 */

	function escapeHtml( text ) {
		return $( '<div>' ).text( text == null ? '' : String( text ) ).html();
	}

	function markDirty() {
		$( '#ems-master-form .ems-savebar' ).addClass( 'is-dirty' );
	}

	function toggleEmptyState() {
		var hasKeys = $( '.ems-keys-list .ems-key-card' ).length > 0;
		$( '#ems-master-form .ems-empty' ).toggleClass( 'is-hidden', hasKeys );
	}

	function updateUsage( card ) {
		var used    = parseInt( card.find( '.ems-usage__used' ).text(), 10 ) || 0;
		var max     = parseInt( card.find( '.ems-max-site-input' ).val(), 10 ) || 0;
		var percent = max > 0 ? Math.min( 100, Math.round( used / max * 100 ) ) : 0;

		card.find( '.ems-usage__max' ).text( max );
		card.find( '.ems-progress > span' ).css( 'width', percent + '%' );
		card.find( '.ems-progress' ).toggleClass( 'is-full', max > 0 && used >= max );
	}

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		var temp = $( '<textarea>' ).val( text ).css( { position: 'fixed', opacity: 0 } ).appendTo( 'body' );
		temp[0].select();
		document.execCommand( 'copy' );
		temp.remove();
		return Promise.resolve();
	}

	function renderTemplates( container, data ) {
		var templates = data.templates || [];
		$( '.ems-remote-summary' ).text( mme_kit.templates_count.replace( '%d', templates.length ) + ' · ' + data.checked_at );

		if ( ! templates.length ) {
			container.html( '<div class="ems-empty"><span class="dashicons dashicons-layout"></span><p>' + escapeHtml( mme_kit.no_templates ) + '</p></div>' );
			return;
		}

		var html = '';
		$.each( templates, function( i, template ) {
			html += '<div class="ems-template">' +
				'<img class="ems-template__thumb" loading="lazy" src="' + escapeHtml( template.thumbnail ) + '" alt="">' +
				'<div class="ems-template__body">' +
					'<span class="ems-template__title" title="' + escapeHtml( template.title ) + '">' + escapeHtml( template.title ) + '</span>' +
					'<span class="ems-template__cats">' + escapeHtml( ( template.categories || [] ).join( ', ' ) || '—' ) + '</span>' +
				'</div>' +
			'</div>';
		} );
		container.html( html );
	}

	function loadRemoteTemplates() {
		var container = $( '#ems-remote-templates' );
		if ( ! container.length ) {
			return;
		}

		var button = $( '.ems-refresh-templates' );
		button.prop( 'disabled', true ).find( '.dashicons' ).addClass( 'is-spinning' );
		container.html( '<div class="ems-empty"><span class="dashicons dashicons-update is-spinning"></span><p>' + escapeHtml( mme_kit.loading ) + '</p></div>' );

		$.post( ajaxurl, { action: 'mme_kit_fetch_remote_templates', nonce: mme_kit.nonce } )
			.done( function( response ) {
				if ( response && response.success ) {
					renderTemplates( container, response.data );
				} else {
					var message = response && response.data && response.data.message ? response.data.message : mme_kit.request_failed;
					container.html( '<div class="ems-alert ems-alert--error"><span class="dashicons dashicons-warning"></span>' + escapeHtml( message ) + '</div>' );
				}
			} )
			.fail( function() {
				container.html( '<div class="ems-alert ems-alert--error"><span class="dashicons dashicons-warning"></span>' + escapeHtml( mme_kit.request_failed ) + '</div>' );
			} )
			.always( function() {
				button.prop( 'disabled', false ).find( '.dashicons' ).removeClass( 'is-spinning' );
			} );
	}

	$(document).ready(function(){
		$('.ems-app select.multi').select2();

		/*
		 * Master page
		 */
		$(document).on('click', '.addnew-key-btn', function(e){
			e.preventDefault();

			var max_id = -1;
			$('.ems-keys-list .ems-key-card').each(function(){
				var id = parseInt($(this).attr('id').replace('rule-', ''), 10);
				if ( id > max_id ) {
					max_id = id;
				}
			});

			var data = {
				action: 'mme_kit_add_new_key',
				nonce: mme_kit.nonce,
				new_id: max_id + 1
			};
			$.post(ajaxurl, data, function(response){
				if ( response.length ) {
					var card = $(response);
					$('.ems-keys-list').prepend(card);
					card.find('select.multi').select2();
					card.find('.ems-reference-input').trigger('focus');
					toggleEmptyState();
					markDirty();
				}
			});
		});

		$(document).on('input change', '#ems-master-form :input', markDirty);

		$(document).on('input', '.ems-reference-input', function(){
			var name = $(this).closest('.ems-key-card').find('.ems-key-card__name');
			name.text( $(this).val() || name.data('placeholder') );
		});

		$(document).on('input change', '.ems-max-site-input', function(){
			updateUsage( $(this).closest('.ems-key-card') );
		});

		$(document).on('change', 'select.template-picker-options', function(){
			var card = $(this).closest('.ems-key-card');
			var value = $(this).val();

			card.find('.ems-key-card__scope').text( $(this).find('option:selected').text() );
			card.find('.single-picker').hide();
			switch (value) {
				case 'templates':
					card.find('.template-picker').show();
					break;
				case 'categories':
					card.find('.category-picker').show();
					break;
				default:
					break;
			}
		});

		$(document).on('click', '.ems-key-card .trash-btn', function(){
			var card = $(this).closest('.ems-key-card');
			Swal.fire({
				title: mme_kit.delete_confirm,
				text: mme_kit.delete_text,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#dc2626',
				cancelButtonColor: '#64748b',
				confirmButtonText: mme_kit.delete_btn,
				cancelButtonText: mme_kit.cancel_btn,
			}).then((result) => {
				if (result.isConfirmed) {
					//Send ajax request to delete the key usage data
					var data = {
						action : 'mme_kit_delete_key',
						nonce: mme_kit.nonce,
						secret_key: card.find('.ems-key-input').val()
					};
					$.post(ajaxurl, data);

					card.remove();
					toggleEmptyState();
					markDirty();
				}
			});
		});

		$(document).on('click', '.ems-key-card .ems-reset-usage', function(){
			var card = $(this).closest('.ems-key-card');
			Swal.fire({
				title: mme_kit.reset_confirm,
				text: mme_kit.reset_text,
				icon: 'question',
				showCancelButton: true,
				confirmButtonColor: '#4f46e5',
				cancelButtonColor: '#64748b',
				confirmButtonText: mme_kit.reset_btn,
				cancelButtonText: mme_kit.cancel_btn,
			}).then((result) => {
				if (result.isConfirmed) {
					var data = {
						action : 'mme_kit_reset_key_usage',
						nonce: mme_kit.nonce,
						secret_key: card.find('.ems-key-input').val()
					};
					$.post(ajaxurl, data, function(response){
						if ( response && response.success ) {
							card.find('.ems-usage__used').text( response.data.site_used );
							updateUsage( card );
						}
					});
				}
			});
		});

		$(document).on('submit', '.ems-import-form', function(e){
			var form = this;
			if ( $(form).find('input[name="mme_kit_import_mode"]:checked').val() !== 'replace' ) {
				return;
			}
			e.preventDefault();
			Swal.fire({
				title: mme_kit.replace_confirm,
				text: mme_kit.replace_text,
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#dc2626',
				cancelButtonColor: '#64748b',
				confirmButtonText: mme_kit.replace_btn,
				cancelButtonText: mme_kit.cancel_btn,
			}).then((result) => {
				if (result.isConfirmed) {
					// Native submit skips this handler.
					form.submit();
				}
			});
		});

		/*
		 * Widgets page
		 */
		$(document).on('change', '.ems-switch input', function(){
			$(this).closest('.ems-widget').toggleClass('is-disabled', !this.checked);
		});

		/*
		 * Areas page
		 */
		$(document).on('click', '.ems-add-area', function(){
			var index = Date.now();
			var html = $('#ems-area-row-template').html().replace(/__i__/g, index);
			$('.ems-area-rows').append(html);
			$('.ems-area-rows .ems-area-row:last input:first').trigger('focus');
		});

		$(document).on('click', '.ems-remove-area', function(){
			$(this).closest('.ems-area-row').remove();
		});

		/*
		 * Shared helpers
		 */
		$(document).on('click', '[data-ems-copy]', function(){
			var button = $(this);
			copyText( $( button.data('ems-copy') ).val() ).then(function(){
				button.addClass('is-copied').find('.ems-copy-label').text( mme_kit.copied );
				setTimeout(function(){
					button.removeClass('is-copied').find('.ems-copy-label').text( mme_kit.copy );
				}, 1500);
			});
		});

		/*
		 * Client page
		 */
		$(document).on('click', '.ems-toggle-secret', function(){
			var input = $( $(this).data('target') );
			var hidden = input.attr('type') === 'password';
			input.attr('type', hidden ? 'text' : 'password');
			$(this).find('.dashicons').toggleClass('dashicons-visibility', !hidden).toggleClass('dashicons-hidden', hidden);
		});

		$(document).on('click', '.ems-refresh-templates', loadRemoteTemplates);

		loadRemoteTemplates();
		$('.ems-key-card').each(function(){
			updateUsage( $(this) );
		});
	});

})( jQuery );
