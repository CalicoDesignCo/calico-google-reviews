( function () {
	'use strict';

	function updateReviewLink() {
		var place = document.querySelector( '#cgr-place-id' );
		var override = document.querySelector( '#cgr-review-request-override' );
		var output = document.querySelector( '#cgr-review-request-link' );
		var test = document.querySelector( '#cgr-review-request-test' );
		if ( ! place || ! override || ! output || ! test ) { return; }
		var link = override.value.trim();
		if ( ! link && place.value.trim() ) {
			link = 'https://search.google.com/local/writereview?placeid=' + encodeURIComponent( place.value.trim() );
		}
		output.value = link;
		test.href = link || '#';
		test.setAttribute( 'aria-disabled', link ? 'false' : 'true' );
	}

	document.addEventListener( 'input', function ( event ) {
		if ( event.target.matches( '#cgr-place-id, #cgr-review-request-override' ) ) { updateReviewLink(); }
	} );
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.cgr-copy-button' );
		if ( ! button ) { return; }
		var target = document.querySelector( button.dataset.copyTarget );
		if ( ! target || ! target.value ) { return; }
		function showCopied() {
			var original = button.textContent;
			button.textContent = cgrAdmin.copied;
			setTimeout( function () { button.textContent = original; }, 1500 );
		}
		function fallbackCopy() {
			target.focus();
			target.select();
			if ( document.execCommand( 'copy' ) ) { showCopied(); }
		}
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( target.value ).then( showCopied ).catch( fallbackCopy );
		} else {
			fallbackCopy();
		}
	} );
	document.addEventListener( 'DOMContentLoaded', updateReviewLink );
}() );
