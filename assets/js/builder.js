/**
 * Runs the Bespoke Bike Builder step-by-step wizard.
 *
 * This handles moving between steps, remembering each step's
 * selection, and enabling/disabling the Next button based on
 * whether the currently visible step has a valid answer yet.
 */

document.addEventListener( 'DOMContentLoaded', function () {

	var wizard = document.querySelector( '.bbb-builder-placeholder' );

	// If this page doesn't contain our shortcode, do nothing.
	if ( ! wizard ) {
		return;
	}

	var steps        = wizard.querySelectorAll( '.bbb-step' );
	var progressText = wizard.querySelector( '.bbb-progress' );
	var nextButton   = wizard.querySelector( '.bbb-next-button' );
	var backButton   = wizard.querySelector( '.bbb-back-button' );
	var totalSteps   = steps.length;

	// This keeps track of the customer's answer for every step,
	// using the step's index number as the key, e.g. selections[0].
	var selections = {};

	var currentIndex = 0;

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

		progressText.textContent = 'Step ' + ( index + 1 ) + ' of ' + totalSteps;

		// Hide the Back button on the very first step - there is
		// nothing to go back to yet.
		backButton.style.display = ( index === 0 ) ? 'none' : 'inline-block';

		// If this step was already answered before (e.g. the customer
		// clicked Back and is now looking at it again), Next should
		// already be enabled.
		nextButton.disabled = ! selections[ index ];
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

	// Set up every step once, when the page first loads.
	steps.forEach( function ( step, index ) {

		if ( step.querySelector( '.bbb-dropdown' ) ) {
			setupDropdownStep( step, index );
		} else {
			setupTileStep( step, index );
		}
	} );

	nextButton.addEventListener( 'click', function () {

		// If we are already on the last step, there is nothing further
		// to move to yet - the real Review screen will replace this in
		// the next step of development.
		if ( currentIndex === totalSteps - 1 ) {
			alert( 'All steps complete! The Review screen will be built next.' );
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
