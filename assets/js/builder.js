/**
 * Handles the click interaction for the Bespoke Bike Builder tiles.
 *
 * For now, this only handles the first step (Build Type). Selecting
 * a tile highlights it and enables the Next button. Clicking Next
 * currently just shows a simple confirmation message - the real
 * Step 2 (Frame Colour) will replace this in a future step.
 */

// We wait for the whole page to finish loading before running our code,
// so we know all the tiles and buttons already exist on the page.
document.addEventListener( 'DOMContentLoaded', function () {

	// Find every tile inside every ".bbb-tile-group" on the page.
	var tileGroups = document.querySelectorAll( '.bbb-tile-group' );

	tileGroups.forEach( function ( group ) {

		var tiles = group.querySelectorAll( '.bbb-tile' );

		// The Next button is stored right after its tile group in the HTML.
		var nextButton = group.parentElement.querySelector( '.bbb-next-button' );

		tiles.forEach( function ( tile ) {

			tile.addEventListener( 'click', function () {

				// Remove the "selected" look from every tile in this group first.
				tiles.forEach( function ( otherTile ) {
					otherTile.classList.remove( 'bbb-tile-selected' );
				} );

				// Then mark only the one that was actually clicked.
				tile.classList.add( 'bbb-tile-selected' );

				// Now that something is selected, the Next button can be used.
				if ( nextButton ) {
					nextButton.disabled = false;
					nextButton.dataset.selectedLabel = tile.textContent.trim();
				}
			} );
		} );

		if ( nextButton ) {
			nextButton.addEventListener( 'click', function () {

				// This is a temporary placeholder confirmation.
				// In the next step, this will move the customer to the
				// Frame Colour step instead of showing this message.
				alert( 'You selected: ' + nextButton.dataset.selectedLabel );
			} );
		}
	} );
} );
