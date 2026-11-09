( function( $ ) {
	var WidgetHeaderBarHandler = function( $scope, $ ) {
		
		$('.site-header--elementor .header-search-link').on('click', function (e) {
			e.preventDefault();
			$('body').toggleClass('search-visible-el');
			if ( ! navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
				setTimeout(function () {
					$('body').find('.header-search-wrap--elementor .search-field-top').focus();
				}, 90);
			}
		});

		// Hide Search
		$('.site-header--elementor .close-search').on('click', function (e) {
			e.preventDefault();
			$('body').removeClass('search-visible-el');
			$('.header-search-wrap--elementor input.search-field-top').val('');
		});

		$(document).click(function (e) {
			var container = $('.header-search-wrap--elementor, .site-header--elementor .header-search-link');
			if (!container.is(e.target) && container.has(e.target).length === 0) {
				$('body').removeClass('search-visible-el');
			}
		});

		$(document).keyup(function (e) {
			if (e.keyCode === 27) {
				$('body').removeClass('search-visible-el');
			}
		});

		//Replace icons
		function iconReplace( iSelector, iClass, data ) {
			var iVar = $( iSelector );
			var dataSearchValue = $( '.site-header--elementor' ).data( data );
			if ( $( '.site-header--elementor' ).data( data ) !== '' ) {
				iVar.removeClass( iClass );
				iVar.addClass( 'ems-el-i ' + dataSearchValue );
			} else {
				iVar.addClass( 'ems-el-i ' + iClass );
			}
		}

		iconReplace( '.site-header--elementor .header-search-link i', 'ems-icon-search', 'search-icon' );
		iconReplace( '.site-header--elementor #header-messages-dropdown-elem .notification-link i', 'ems-icon-inbox-small', 'messages-icon' );
		iconReplace( '.site-header--elementor #header-notifications-dropdown-elem .notification-link i', 'ems-icon-bell-small', 'notifications-icon' );
		iconReplace( '.site-header--elementor a.header-cart-link i', 'ems-icon-shopping-cart', 'cart-icon' );
		iconReplace( 'body:not(.ems-dark-theme) .site-header--elementor a#ems-toggle-theme i', 'ems-icon-moon-circle', 'dark-icon' );
		iconReplace( '.ems-dark-theme .site-header--elementor a#ems-toggle-theme i', 'ems-icon-sun', 'dark-icon' );
		iconReplace( '.site-header--elementor a.header-maximize-link i', 'ems-icon-maximize', 'sidebartoggle-icon' );
		iconReplace( '.site-header--elementor a.header-minimize-link i', 'ems-icon-minimize', 'sidebartoggle-icon' );


		// NB - Duplicated as per theme main.js sidePanel()
		// whenever we hover over a menu item that has a submenu
		$('.user-wrap li.parent, .user-wrap .menu-item-has-children').on('mouseover', function() {
			var $menuItem = $(this),
				$submenuWrapper = $('> .wrapper', $menuItem);

			// grab the menu item's position relative to its positioned parent
			var menuItemPos = $menuItem.position();

			// place the submenu in the correct position relevant to the menu item
			$submenuWrapper.css({
				top: menuItemPos.top
			});
		});

		// Fix user mention position
		$(document).ready(function() {
			if ( $( '.site-header--elementor .bp-suggestions-mention' ).length ) {
				var userMentionText = $( '.site-header--elementor .bp-suggestions-mention' ).text();
				$( '.site-header--elementor .sub-menu .user-mention' ).append( document.createTextNode( userMentionText ) );
				$( '.site-header--elementor .bp-suggestions-mention' ).hide();
			}
		});

		// User sub menu dropdown on smaller screens
		$( '.site-header--elementor .user-wrap > .sub-menu .ab-sub-wrapper' ).on( 'click', function (e) {
			var window_width = $( window ).width();
			if ( window_width < 380 ) {
				$( this ).find( '.ab-submenu' ).slideToggle();
				$( this ).parent().siblings().find( '.ab-submenu' ).slideUp();
        		return false;
			}
		});

	};

	var WidgetEMSP_MembersHandler = function( $scope, $ ) {
		
		$('.ems-members .ems-members__tab').on('click', function (e) {
			e.preventDefault();
			var $tabItem = $(this);
			var $mType = $tabItem.data('type');
			var $emsContainer = $tabItem.closest('.ems-members')
			$('.ems-members .ems-members__tab').removeClass('selected');
			$tabItem.toggleClass('selected');

			$emsContainer.find('.ems-members-list').removeClass('active');
			$emsContainer.find('.ems-members-list--' + $mType + '').addClass('active');
		});

	};

	var WidgetEMSP_Profile_CompletionHandler = function( $scope, $ ) {

		var readyStateProfile = true;

		$('.profile_bit').click(function(event) {
			event.stopPropagation();
			
			if ( !$( this ).find( '.profile_bit__details' ).is(':visible') && readyStateProfile ) {
				$( this ).find( '.profile_bit__details' ).slideDown();
				$( this ).addClass('active');
				setTimeout( function(){
					readyStateProfile = false;
				},300);
			} else if( $( this ).find( '.profile_bit__details' ).is(':visible') && !readyStateProfile ) {
				$( this ).find( '.profile_bit__details' ).slideUp();
				$( this ).removeClass('active');
				setTimeout( function(){
					readyStateProfile = true;
				},300);
			}
		});

		$('.profile_bit').hover(function(){
			if ( ! $( this ).find( '.profile_bit__details' ).is(':visible') && readyStateProfile ) {
				$( this ).find( '.profile_bit__details' ).slideDown();
				$( this ).addClass('active');
				setTimeout( function(){
					readyStateProfile = false;
				},300);
			}
		}, function(){
			if ($( this ).find( '.profile_bit__details' ).is(':visible') && !readyStateProfile ) {
				$( this ).find( '.profile_bit__details' ).slideUp();
				$( this ).removeClass('active');
				setTimeout( function(){
					readyStateProfile = true;
				},300);
			}
		});

	};

	var WidgetLd_ActivityHandler = function( $scope, $ ) {

		$('.ems-ldactivity').each(function(){

			var $slickIns = $(this);
			var $slickRun = $slickIns.find('.ems-la.ems-la--isslick');
			var $switchDots = $slickIns.find('.ems-la').data('dots');

			$slickRun.not('.slick-initialized').slick({
				infinite: true,
				slidesToShow: 1,
				slidesToScroll: 1,
				dots: $switchDots,
				fade: !0,
				cssEase: 'linear',
				prevArrow: '<a class="ems-slide-prev"><i class="ems-icon-angle-left"></i></a>',
				nextArrow: '<a class="ems-slide-next"><i class="ems-icon-angle-right"></i></a>',
			});

		});

	};

	var WidgetEMS_TabsHandler = function( $scope, $ ) {

		$('.ems-tabs').each(function(){
			
			var $slickIns = $(this);
			var $slickRun = $slickIns.find('.ems-tabs__run');
			var $slickNav = $slickIns.find('.ems-tabs__nav');
			var $tabsNum = $slickIns.find('.ems-tabs__nav').data('num');
			var $switchNav = $slickIns.find('.ems-tabs__run').data('nav');
			var $switchDots = $slickIns.find('.ems-tabs__run').data('dots');

			$slickRun.not('.slick-initialized').slick({
				slidesToShow: 1,
				slidesToScroll: 1,
				arrows: $switchNav,
				dots: $switchDots,
				fade: true,
				asNavFor: $slickNav,
				prevArrow: '<a class="ems-slide-prev"><i class="ems-icon-arrow-left"></i></a>',
				nextArrow: '<a class="ems-slide-next"><i class="ems-icon-arrow-right"></i></a>',
				rtl: false,
			});

			$slickNav.not('.slick-initialized').slick({
				slidesToShow: $tabsNum,
				slidesToScroll: 1,
				asNavFor: $slickRun,
				dots: true,
				focusOnSelect: true,
				variableWidth: true,
				rtl: false,
			});

		});

	};

	var WidgetEMS_GalleryHandler = function( $scope, $ ) {

		$('.ems-gallery').each(function(){
			
			var $slickIns = $(this);
			var $slickRun = $slickIns.find('.ems-gallery__run');
			var $switchNav = $slickIns.find('.ems-gallery__run').data('nav');
			var $switchDots = $slickIns.find('.ems-gallery__run').data('dots');
			var $switchLoop = $slickIns.find('.ems-gallery__run').data('loop');
			var $slMargin = $slickIns.find('.ems-gallery__run').data('margin');

			$slickRun.not('.slick-initialized').slick({
				centerMode: true,
				centerPadding: $slMargin,
				slidesToShow: 1,
				prevArrow: '<a class="ems-slide-prev"><i class="ems-icon-angle-left"></i></a>',
				nextArrow: '<a class="ems-slide-next"><i class="ems-icon-angle-right"></i></a>',
				arrows: $switchNav,
				dots: $switchDots,
				infinite: $switchLoop,
				rtl: false,
				responsive: [
					{
						breakpoint: 768,
						settings: {
							centerPadding: '0px',
						}
					},
				],
			});

		});

		$('.ems-gallery__image.is-video').on('click', function (e) {
			e.preventDefault();
			var $imgOverLay = $(this);
			var $mediaVideo = $imgOverLay.find('.ems-gallery__video');
			var $slideBody = $imgOverLay.closest('.ems-gallery__block').find('.ems-gallery__body');
			$imgOverLay.addClass('is-active');
			$imgOverLay.find('.media-container').fadeTo( 'slow', 0 );
			$mediaVideo.css({'z-index': 10});
			$slideBody.fadeTo( 'slow', 0 );
			$slideBody.css({'z-index': 5});
		});

		$('.ems-gallery').on('beforeChange', function (event, slick, currentSlide, nextSlide) {
			var $slickCurrent = $('.slick-current');
			var $slickBody = $slickCurrent.find('.ems-gallery__body');
			player = $slickCurrent.find('iframe').get(0);
			slideType = $slickCurrent.find('.ems-gallery__image').attr('class').split(' ')[0];
      
			if (slideType == 'vimeo') {
			  command = {
				'method': 'pause',
				'value': 'true'
			  };
			} else {
			  command = {
				'event': 'command',
				'func': 'pauseVideo'
			  };
			}

			if (player != undefined) {
				player.contentWindow.postMessage(JSON.stringify(command), '*');
			}

			$('.slick-current iframe').attr('src', $('.slick-current iframe').attr('src'));
			$slickCurrent.find('.ems-gallery__image').removeClass('is-active');
			$slickCurrent.find('.media-container').fadeTo( 'slow', 1 );
			$slickCurrent.find('.ems-gallery__video').css({'z-index': 5});
			$slickBody.fadeTo( 'slow', 1 );
			$slickBody.css({'z-index': 15});
		});

	};

	var WidgetEMS_ReviewHandler = function( $scope, $ ) {

		$('.ems-review__image-overlay').on('click', function (e) {
			e.preventDefault();
			var $imgOverLay = $(this);
			var $mediaVideo = $imgOverLay.closest('.media-video');
			var $video = $mediaVideo.find('iframe.elementor-video-iframe');
			$imgOverLay.remove();
			$video[0].src += "&autoplay=1";
		});

	};

	var WidgetEMSP_ActivityHandler = function( $scope, $ ) {
		$('.elementor-activity-widget li.activity-item').each(function(){
			var _findtext  = $( this ).find( '.activity-inner > p' ).removeAttr( 'br' ).removeAttr( 'a' ).text();
			var	_url       = '',
				_newString = '',
				startIndex = '',
				_is_exist  = 0;
			if ( 0 <= _findtext.indexOf( 'http://' )) {
				startIndex = _findtext.indexOf( 'http://' );
				_is_exist  = 1;
			} else if (0 <= _findtext.indexOf( 'https://' )) {
				startIndex = _findtext.indexOf( 'https://' );
				_is_exist  = 1;
			} else if (0 <= _findtext.indexOf( 'www.' )) {
				startIndex = _findtext.indexOf( 'www' );
				_is_exist  = 1;
			}
			if ( 1 === _is_exist ) {
				for ( var i = startIndex; i < _findtext.length; i ++ ) {
					if ( _findtext[i] === ' ' || _findtext[i] === '\n' ) {
						break;
					} else {
						_url += _findtext[i];
					}
				}

				if ( _url !== '' ) {
					_newString = $.trim( _findtext.replace( _url, '' ) );
				}
				if (0 >= _newString.length) {
					if ( $( this ).find( '.activity-inner > .activity-link-preview-container ' ).length || $( this ).hasClass( 'wp-link-embed' ) ) {
						$( this ).find( '.activity-inner > p:first a' ).hide();
					}
				}
			}
		});

		//Replace dummy image with original image by faking scroll event
		$(document).ready(function() {
			$( window ).scroll();
			$( '.emsel-list-flow' ).scroll(function() {
				$( window ).scroll();
			});
		});

	};

	var WidgetEMS_GroupsHandler = function( $scope, $ ) {
		
		$('.ems-groups .ems-groups__tab').on('click', function (e) {
			e.preventDefault();
			var $tabItem = $(this);
			var $gType = $tabItem.data('type');
			var $emsContainer = $tabItem.closest('.ems-groups')
			$('.ems-groups .ems-groups__tab').removeClass('selected');
			$tabItem.toggleClass('selected');

			$emsContainer.find('.ems-groups-list').removeClass('active');
			$emsContainer.find('.ems-groups-list--' + $gType + '').addClass('active');
		});

	};

	//Fix for floating buttons in "Learndash Activity" and "Forum Activity"
	var ElementorClasses = function( $scope, $ ) {
		$( '.ems-ldactivity' ).closest( 'section.elementor-element' ).addClass('ems-ldactivity-main-section');
		$( '.ems-forums-activity-wrapper' ).closest( 'section.elementor-element' ).addClass('ems-forums-activity-main-section');
		$( '.elementor-heading-title' ).each( function() {
			if( !$( this ).closest( '.elementor-element' ).siblings().length ) {
				$( this ).closest( 'section.elementor-element' ).addClass('elementor-heading-title-main-section');
			}
		});
		$( '.ems-ldactivity' ).closest( 'section.elementor-element' ).prev('.elementor-heading-title-main-section').addClass('elementor-max-50');
		$( '.ems-forums-activity-wrapper' ).closest( 'section.elementor-element' ).prev('.elementor-heading-title-main-section').addClass('elementor-max-50');
	};



	// Make sure you run this code under Elementor..
	$( window ).on( 'elementor/frontend/init', function() {
		elementorFrontend.hooks.addAction( 'frontend/element_ready/header-bar.default', WidgetHeaderBarHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/emsp-members.default', WidgetEMSP_MembersHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/emsp-profile-completion.default', WidgetEMSP_Profile_CompletionHandler );
		// elementorFrontend.hooks.addAction( 'frontend/element_ready/ld-activity.default', WidgetLd_ActivityHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/global', WidgetLd_ActivityHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/ems-tabs.default', WidgetEMS_TabsHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/ems-gallery.default', WidgetEMS_GalleryHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/ems-review.default', WidgetEMS_ReviewHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/emsp-activity.default', WidgetEMSP_ActivityHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/ems-groups.default', WidgetEMS_GroupsHandler );
		elementorFrontend.hooks.addAction( 'frontend/element_ready/widget', ElementorClasses );

	} );
} )( jQuery );
