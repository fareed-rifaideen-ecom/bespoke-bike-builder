/**
 * Runs the Bespoke Bike Builder step-by-step wizard, including the
 * final Review screen.
 *
 * This handles moving between steps, remembering each step's
 * selection, enabling/disabling the Next button, and building the
 * Review summary right before the customer confirms their build.
 */

document.addEventListener( 'DOMContentLoaded', function () {

	var wizard = document.querySelector( '.bbb-builder-placeholder' );

	// If this page doesn't contain our shortcode, do nothing.
	if ( ! wizard ) {
		return;
	}

	var steps          = wizard.querySelectorAll( '.bbb-step' );
	var progressText   = wizard.querySelector( '.bbb-progress' );
	var nextButton     = wizard.querySelector( '.bbb-next-button' );
	var backButton     = wizard.querySelector( '.bbb-back-button' );
	var reviewContent  = wizard.querySelector( '.bbb-review-content' );

	// steps includes every option group PLUS the Review step at the end.
	var totalSteps      = steps.length;
	var reviewStepIndex = totalSteps - 1;
	var totalOptionSteps = reviewStepIndex;

	// This keeps track of the customer's answer for every option step,
	// using the step's index number as the key, e.g. selections[0].
	var selections = {};

	var currentIndex = 0;

	/**
	 * Builds the Review screen's summary rows from every stored
	 * selection, using each step's group label.
	 */
	function populateReview() {

		var rowsHtml = '';

		for ( var i = 0; i < totalOptionSteps; i++ ) {

			var step  = steps[ i ];
			var label = step.dataset.groupLabel;
			var value = selections[ i ] ? selections[ i ] : 'Not selected';

			rowsHtml += '<div class="bbb-review-row">' +
				'<span class="bbb-review-label">' + label + '</span>' +
				'<span class="bbb-review-value">' + value + '</span>' +
				'</div>';
		}

		reviewContent.innerHTML = rowsHtml;
	}

	/**
	 * Shows only the step matching "index" and hides every other one.
	 * Also updates the progress text and the Back/Next button states.
	 */
	function showStep( index ) {

		steps.forEach( function ( step, stepIndex ) {
			if ( stepIndex === index ) {
				step.classList.add( 'bbb-step-active' );
			} else {
				step.classList.remove( 'bbb-step-active' );
			}
		} );

		// Hide the Back button on the very first step - there is
		// nothing to go back to yet.
		backButton.style.display = ( index === 0 ) ? 'none' : 'inline-block';

		if ( index === reviewStepIndex ) {

			progressText.textContent = 'Review Your Build';
			nextButton.textContent   = 'Confirm & Continue';
			// The Review screen has nothing new to select, so Next is
			// always available here.
			nextButton.disabled      = false;

			populateReview();

		} else {

			progressText.textContent = 'Step ' + ( index + 1 ) + ' of ' + totalOptionSteps;
			nextButton.textContent   = 'Next';

			// If this step was already answered before (e.g. the customer
			// clicked Back and is now looking at it again), Next should
			// already be enabled.
			nextButton.disabled = ! selections[ index ];
		}
	}

	/**
	 * Sets up click handling for a tile-based step.
	 */
	function setupTileStep( step, index ) {

		var tiles = step.querySelectorAll( '.bbb-tile' );

		tiles.forEach( function ( tile ) {

			tile.addEventListener( 'click', function () {

				tiles.forEach( function ( otherTile ) {
					otherTile.classList.remove( 'bbb-tile-selected' );
				} );

				tile.classList.add( 'bbb-tile-selected' );

				selections[ index ] = tile.dataset.value;

				// Only enable Next if this tile's step is the one
				// currently visible on screen.
				if ( index === currentIndex ) {
					nextButton.disabled = false;
				}
			} );
		} );
	}

	/**
	 * Sets up change handling for a dropdown-based step.
	 */
	function setupDropdownStep( step, index ) {

		var dropdown = step.querySelector( '.bbb-dropdown' );

		if ( ! dropdown ) {
			return;
		}

		dropdown.addEventListener( 'change', function () {

			if ( dropdown.value ) {
				selections[ index ] = dropdown.value;
			} else {
				delete selections[ index ];
			}

			if ( index === currentIndex ) {
				nextButton.disabled = ! selections[ index ];
			}
		} );
	}

	// Set up every option step once, when the page first loads.
	// The Review step (the last one) has no tiles or dropdown, so it
	// is simply skipped here.
	for ( var i = 0; i < totalOptionSteps; i++ ) {

		var step = steps[ i ];

		if ( step.querySelector( '.bbb-dropdown' ) ) {
			setupDropdownStep( step, i );
		} else {
			setupTileStep( step, i );
		}
	}

	nextButton.addEventListener( 'click', function () {

		// If we are already on the Review screen, this is the final
		// confirmation click. The real lead-capture form will replace
		// this placeholder in a later step.
		if ( currentIndex === reviewStepIndex ) {
			alert( 'This is a placeholder - the lead capture form will be built next!' );
			return;
		}

		currentIndex++;
		showStep( currentIndex );
	} );

	backButton.addEventListener( 'click', function () {

		if ( currentIndex === 0 ) {
			return;
		}

		currentIndex--;
		showStep( currentIndex );
	} );

	// Show the very first step when the page loads.
	showStep( currentIndex );
} );
