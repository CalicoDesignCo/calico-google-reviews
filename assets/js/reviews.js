( function () {
	'use strict';

	function setTriggerState( trigger, loading ) {
		if ( ! trigger ) { return; }
		trigger.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
		trigger.setAttribute( 'aria-disabled', loading ? 'true' : 'false' );
		if ( 'disabled' in trigger ) { trigger.disabled = loading; }
	}

	function layoutMasonry( widget ) {
		if ( ! widget || widget.dataset.cgrMasonry !== '1' ) { return; }
		var list = widget.querySelector( '.cgr-review-list' );
		if ( ! list ) { return; }
		var styles = getComputedStyle( list );
		var rowHeight = parseFloat( styles.gridAutoRows ) || 1;
		var rowGap = parseFloat( styles.rowGap ) || 0;
		Array.prototype.forEach.call( list.children, function ( card ) {
			card.style.gridRowEnd = 'auto';
		} );
		Array.prototype.forEach.call( list.children, function ( card ) {
			var height = card.getBoundingClientRect().height;
			var span = Math.max( 1, Math.ceil( ( height + rowGap ) / ( rowHeight + rowGap ) ) );
			card.style.gridRowEnd = 'span ' + span;
		} );
	}

	function initMasonry( widget ) {
		if ( widget.dataset.cgrMasonryReady === '1' ) { layoutMasonry( widget ); return; }
		widget.dataset.cgrMasonryReady = '1';
		var timer;
		function schedule() {
			clearTimeout( timer );
			timer = setTimeout( function () { layoutMasonry( widget ); }, 20 );
		}
		widget.addEventListener( 'toggle', schedule, true );
		widget.addEventListener( 'load', schedule, true );
		window.addEventListener( 'resize', schedule );
		layoutMasonry( widget );
	}

	function request( widget, config, values, append, trigger ) {
		if ( widget.dataset.cgrBusy === '1' ) { return; }
		widget.dataset.cgrBusy = '1';
		widget.setAttribute( 'aria-busy', 'true' );
		setTriggerState( trigger, true );
		var status = widget.querySelector( '.cgr-loading-status' );
		if ( status ) { status.textContent = config.loading_label; }
		var body = new FormData();
		body.append( 'action', 'cgr_load_reviews' );
		body.append( 'nonce', cgrReviews.nonce );
		body.append( 'config', JSON.stringify( config ) );
		Object.keys( values ).forEach( function ( key ) { body.append( key, values[ key ] ); } );

		fetch( cgrReviews.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( response ) {
				if ( ! response.success ) { throw new Error( response.data && response.data.message ? response.data.message : config.error_label ); }
				var list = widget.querySelector( '.cgr-review-list' );
				if ( append ) { list.insertAdjacentHTML( 'beforeend', response.data.html ); } else { list.innerHTML = response.data.html; }
				layoutMasonry( widget );
				widget.dataset.cgrOffset = response.data.offset;
				widget.dataset.cgrHasMore = response.data.hasMore ? '1' : '0';
				if ( config.loading_method === 'pagination' ) {
					var pagination = widget.querySelector( '.cgr-pagination' );
					if ( pagination && response.data.pagination ) { pagination.outerHTML = response.data.pagination; }
					if ( status ) { status.textContent = ( cgrReviews.pageLoaded || 'Review page %d loaded.' ).replace( '%d', response.data.page ); }
				} else if ( ! response.data.hasMore ) {
					if ( trigger ) { trigger.hidden = true; }
					if ( status ) { status.textContent = config.complete_label; }
				} else if ( status ) {
					status.textContent = '';
				}
			} )
			.catch( function () { if ( status ) { status.textContent = config.error_label; } } )
			.finally( function () {
				widget.dataset.cgrBusy = '0';
				widget.setAttribute( 'aria-busy', 'false' );
				setTriggerState( trigger, false );
			} );
	}

	function initWidget( widget ) {
		if ( widget.dataset.cgrReady === '1' ) { return; }
		widget.dataset.cgrReady = '1';
		var config;
		try { config = JSON.parse( widget.dataset.cgrConfig || '{}' ); } catch ( error ) { return; }
		if ( config.loading_method === 'pagination' ) {
			widget.addEventListener( 'click', function ( event ) {
				var button = event.target.closest( '.cgr-page-button' );
				if ( ! button || button.disabled ) { return; }
				request( widget, config, { page: button.dataset.page }, false, button );
			} );
			return;
		}
		if ( config.loading_method !== 'load_more' ) { return; }
		var trigger;
		try { trigger = document.querySelector( config.trigger_selector ); } catch ( error ) { return; }
		if ( ! trigger ) { return; }
		if ( widget.dataset.cgrHasMore !== '1' ) { trigger.hidden = true; }
		trigger.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( widget.dataset.cgrHasMore !== '1' ) { return; }
			request( widget, config, { offset: widget.dataset.cgrOffset || config.limit }, true, trigger );
		} );
	}

	function initCarousel( widget ) {
		if ( widget.dataset.cgrCarouselReady === '1' ) { return; }
		widget.dataset.cgrCarouselReady = '1';
		var config;
		try { config = JSON.parse( widget.dataset.cgrCarouselConfig || '{}' ); } catch ( error ) { config = {}; }
		var scrollCount = Math.max( 1, parseInt( config.scroll, 10 ) || 1 );
		var autoplay = config.autoplay === true;
		var speed = Math.max( 1000, parseInt( config.speed, 10 ) || 5000 );
		var viewport = widget.querySelector( '.cgr-carousel-viewport' );
		var track = widget.querySelector( '.cgr-review-list' );
		var slides = track ? Array.prototype.slice.call( track.children ) : [];
		var dots = widget.querySelector( '.cgr-carousel-dots' );
		var previous = widget.querySelector( '.cgr-carousel-previous' );
		var next = widget.querySelector( '.cgr-carousel-next' );
		if ( ! viewport || ! track || ! slides.length ) { return; }
		var current = 0;
		var positions = [ 0 ];
		var maxStart = 0;
		var baseOffset = 0;
		var startX = 0;
		var dragging = false;
		var dragged = false;
		var autoplayTimer = null;

		function visibleSlides() {
			var styles = getComputedStyle( widget );
			var property = window.matchMedia( '(max-width: 600px)' ).matches ? '--cgr-cols-mobile' : ( window.matchMedia( '(max-width: 1024px)' ).matches ? '--cgr-cols-tablet' : '--cgr-cols' );
			return Math.max( 1, parseInt( styles.getPropertyValue( property ), 10 ) || 1 );
		}

		function buildDots() {
			if ( ! dots ) { return; }
			dots.innerHTML = '';
			dots.hidden = positions.length < 2;
			positions.forEach( function ( position, index ) {
				var dot = document.createElement( 'button' );
				dot.type = 'button';
				dot.className = 'cgr-carousel-dot' + ( position === current ? ' is-active' : '' );
				dot.dataset.position = position;
				dots.appendChild( dot );
			} );
		}

		function offsetForPosition( position ) {
			var gap = parseFloat( getComputedStyle( track ).columnGap ) || 0;
			var slideWidth = slides[ 0 ] ? slides[ 0 ].getBoundingClientRect().width : viewport.clientWidth;
			return position * ( slideWidth + gap );
		}

		function apply( animate ) {
			current = Math.max( 0, Math.min( current, maxStart ) );
			baseOffset = offsetForPosition( current );
			track.style.transition = animate ? '' : 'none';
			track.style.transform = 'translate3d(' + ( -baseOffset ) + 'px,0,0)';
			if ( ! animate ) { requestAnimationFrame( function () { track.style.transition = ''; } ); }
			if ( previous ) { previous.disabled = current === 0; previous.hidden = maxStart < 1; }
			if ( next ) { next.disabled = current === maxStart; next.hidden = maxStart < 1; }
			if ( dots ) {
				var active = 0;
				positions.forEach( function ( position, index ) { if ( position <= current ) { active = index; } } );
				dots.querySelectorAll( '.cgr-carousel-dot' ).forEach( function ( dot, index ) { dot.classList.toggle( 'is-active', index === active ); } );
			}
		}

		function refresh() {
			maxStart = Math.max( 0, slides.length - visibleSlides() );
			positions = [];
			for ( var position = 0; position < maxStart; position += scrollCount ) { positions.push( position ); }
			if ( ! positions.length || positions[ positions.length - 1 ] !== maxStart ) { positions.push( maxStart ); }
			current = Math.min( current, maxStart );
			buildDots();
			apply( false );
		}

		function go( position ) { current = position; apply( true ); }
		function restartAutoplay() {
			if ( autoplayTimer ) { clearInterval( autoplayTimer ); autoplayTimer = null; }
			if ( autoplay && maxStart > 0 ) {
				autoplayTimer = setInterval( function () { go( current >= maxStart ? 0 : Math.min( maxStart, current + scrollCount ) ); }, speed );
			}
		}
		if ( previous ) { previous.addEventListener( 'click', function () { go( current - scrollCount ); restartAutoplay(); } ); }
		if ( next ) { next.addEventListener( 'click', function () { go( current + scrollCount ); restartAutoplay(); } ); }
		if ( dots ) { dots.addEventListener( 'click', function ( event ) { var dot = event.target.closest( '.cgr-carousel-dot' ); if ( dot ) { go( parseInt( dot.dataset.position, 10 ) || 0 ); restartAutoplay(); } } ); }

		viewport.addEventListener( 'pointerdown', function ( event ) {
			if ( event.button !== 0 ) { return; }
			if ( event.target.closest( 'a, button, summary, details, input, select, textarea, label' ) ) { dragged = false; return; }
			dragging = true;
			dragged = false;
			startX = event.clientX;
			viewport.classList.add( 'is-dragging' );
			viewport.setPointerCapture( event.pointerId );
		} );
		viewport.addEventListener( 'pointermove', function ( event ) {
			if ( ! dragging ) { return; }
			var delta = event.clientX - startX;
			if ( Math.abs( delta ) > 5 ) { dragged = true; }
			track.style.transform = 'translate3d(' + ( -baseOffset + delta ) + 'px,0,0)';
		} );
		function finishDrag( event ) {
			if ( ! dragging ) { return; }
			dragging = false;
			viewport.classList.remove( 'is-dragging' );
			var delta = event.clientX - startX;
			if ( Math.abs( delta ) > Math.min( 80, viewport.clientWidth * .15 ) ) { current += delta < 0 ? scrollCount : -scrollCount; }
			apply( true );
			restartAutoplay();
		}
		viewport.addEventListener( 'pointerup', finishDrag );
		viewport.addEventListener( 'pointercancel', finishDrag );
		viewport.addEventListener( 'click', function ( event ) { if ( dragged ) { event.preventDefault(); event.stopPropagation(); dragged = false; } }, true );
		viewport.addEventListener( 'dragstart', function ( event ) { event.preventDefault(); } );
		var resizeTimer;
		window.addEventListener( 'resize', function () { clearTimeout( resizeTimer ); resizeTimer = setTimeout( function () { refresh(); restartAutoplay(); }, 100 ); } );
		refresh();
		restartAutoplay();
	}

	function init( root ) {
		if ( root.matches && root.matches( '.cgr-reviews[data-cgr-masonry="1"]' ) ) { initMasonry( root ); }
		root.querySelectorAll( '.cgr-reviews[data-cgr-masonry="1"]' ).forEach( initMasonry );
		if ( root.matches && root.matches( '.cgr-reviews[data-cgr-loading]' ) ) { initWidget( root ); }
		root.querySelectorAll( '.cgr-reviews[data-cgr-loading]' ).forEach( initWidget );
		if ( root.matches && root.matches( '.cgr-reviews[data-cgr-carousel="1"]' ) ) { initCarousel( root ); }
		root.querySelectorAll( '.cgr-reviews[data-cgr-carousel="1"]' ).forEach( initCarousel );
	}

	document.addEventListener( 'DOMContentLoaded', function () { init( document ); } );
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/calico-google-reviews.default', function ( scope ) { init( scope[ 0 ] || scope ); } );
		}
	} );
}() );
