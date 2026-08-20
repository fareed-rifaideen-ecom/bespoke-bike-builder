/**
 * Runs the Bespoke Bike Builder step-by-step wizard, including the
 * Review screen, the lead capture form, and the real save-to-database
 * submission via WordPress AJAX.
 *
 * This handles moving between steps, remembering each step's
 * selection (including each option's real database ID), enabling
 * or disabling the Next button, building the Review summary,
 * validating the Name/Email/Phone fields, and finally sending
 * everything to includes/class-bbb-ajax.php to be saved.
 */

document.addEventListener( 'DOMContentLoaded', function () {

	var wizard = document.querySelector( '.bbb-builder-placeholder' );

	// If this page doesn't contain our shortcode, do nothing.
	if ( ! wizard ) {
		return;
	}

	var steps           = wizard.querySelectorAll( '.bbb-step' );
	var progressText    = wizard.querySelector( '.bbb-progress' );
	var nextButton      = wizard.querySelector( '.bbb-next-button' );
	var backButton      = wizard.querySelector( '.bbb-back-button' );
	var reviewContent   = wizard.querySelector( '.bbb-review-content' );
	var leadFormFields  = wizard.querySelector( '.bbb-lead-form-fields' );
	var errorMessage    = wizard.querySelector( '.bbb-lead-error' );
	var successMessage  = wizard.querySelector( '.bbb-success-message' );

	var nameInput  = wizard.querySelector( '#bbb-lead-name' );
	var emailInput = wizard.querySelector( '#bbb-lead-email' );
	var phoneInput = wizard.querySelector( '#bbb-lead-phone' );

	// These come from PHP via data-* attributes on the wizard container,
	// so JavaScript can safely talk to WordPress in the background.
	var templateId = wizard.dataset.templateId;
	var ajaxUrl    = wizard.dataset.ajaxUrl;
	var nonce      = wizard.dataset.nonce;

	// steps includes every option group, PLUS the Review step,
	// PLUS the lead capture step - in that order.
	var totalSteps       = steps.length;
	var leadStepIndex     = totalSteps - 1;
	var reviewStepIndex   = totalSteps - 2;
	var totalOptionSteps  = reviewStepIndex;

	// This keeps track of the customer's answer for every option step.
	// Each entry stores both the option's real database ID (needed to
	// save correctly) and its label (needed to display on the Review
	// screen), e.g. selections[0] = { optionId: '4', label: 'Black' }.
	var selections = {};

	var currentIndex = 0;

	/**
	 * Checks whether the Name, Email, and Phone fields are all
	 * filled in, and that the email looks like a valid address.
	 */
	function isLeadFormValid() {

		var name  = nameInput.value.trim();
		var email = emailInput.value.trim();
		var phone = phoneInput.value.trim();

		var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

		return ( name !== '' ) && emailPattern.test( email ) && ( phone !== '' );
	}

	/**
	 * Builds the Review screen's summary rows from every stored
	 * selection, using each step's group label.
	 */
	function populateReview() {

		var rowsHtml = '';

		for ( var i = 0; i < totalOptionSteps; i++ ) {

			var step      = steps[ i ];
			var label     = step.dataset.groupLabel;
			var selection = selections[ i ];
			var value     = selection ? selection.label : 'Not selected';

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

		if ( index === leadStepIndex ) {

			progressText.textContent = 'Your Details';
			nextButton.textContent   = 'Submit Build Request';
			nextButton.disabled      = ! isLeadFormValid();

		} else if ( index === reviewStepIndex ) {

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

				selections[ index ] = {
					optionId: tile.dataset.optionId,
					label: tile.dataset.value
				};

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

				var selectedOption = dropdown.options[ dropdown.selectedIndex ];

				selections[ index ] = {
					optionId: dropdown.value,
					label: selectedOption.dataset.label
				};

			} else {
				delete selections[ index ];
			}

			if ( index === currentIndex ) {
				nextButton.disabled = ! selections[ index ];
			}
		} );
	}

	// Set up every option step once, when the page first loads.
	// The Review and lead capture steps (the last two) have no tiles
	// or dropdown, so they are simply skipped here.
	for ( var i = 0; i < totalOptionSteps; i++ ) {

		var step = steps[ i ];

		if ( step.querySelector( '.bbb-dropdown' ) ) {
			setupDropdownStep( step, i );
		} else {
			setupTileStep( step, i );
		}
	}

	// Re-check the lead form's validity every time the customer types
	// in any of the three fields, but only while that step is visible.
	[ nameInput, emailInput, phoneInput ].forEach( function ( field ) {

		field.addEventListener( 'input', function () {

			if ( currentIndex === leadStepIndex ) {
				nextButton.disabled = ! isLeadFormValid();
			}
		} );
	} );

	/**
	 * Sends the completed build request to WordPress in the background
	 * (via AJAX) to be saved into the database, then shows either a
	 * success message or an error message right on the page.
	 */
	function submitBuildRequest() {

		if ( errorMessage ) {
			errorMessage.style.display = 'none';
			errorMessage.textContent   = '';
		}

		var optionsPayload = [];

		for ( var i = 0; i < totalOptionSteps; i++ ) {

			var step      = steps[ i ];
			var selection = selections[ i ];

			if ( selection ) {
				optionsPayload.push( {
					group_id: step.dataset.groupId,
					option_id: selection.optionId
				} );
			}
		}

		var formData = new FormData();
		formData.append( 'action', 'bbb_submit_build' );
		formData.append( 'nonce', nonce );
		formData.append( 'template_id', templateId );
		formData.append( 'customer_name', nameInput.value.trim() );
		formData.append( 'customer_email', emailInput.value.trim() );
		formData.append( 'customer_whatsapp', phoneInput.value.trim() );
		formData.append( 'options', JSON.stringify( optionsPayload ) );

		nextButton.disabled    = true;
		nextButton.textContent = 'Submitting...';

		fetch( ajaxUrl, {
			method: 'POST',
			body: formData
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {

				if ( data.success ) {

					var customerName = nameInput.value.trim();

					leadFormFields.style.display = 'none';

					successMessage.innerHTML = '<p>Thank you, ' + customerName + '! We have received your build request ' +
						'(Reference: ' + data.data.reference_code + ') and will contact you shortly.</p>';
					successMessage.style.display = 'block';

					backButton.style.display = 'none';
					nextButton.style.display = 'none';

				} else {

					showSubmitError( data.data && data.data.message ? data.data.message : 'Something went wrong. Please try again.' );
				}
			} )
			.catch( function () {
				showSubmitError( 'Something went wrong. Please check your connection and try again.' );
			} );
	}

	/**
	 * Shows an error message on the lead capture step and re-enables
	 * the Submit button, so the customer can correct something and
	 * try again.
	 */
	function showSubmitError( message ) {

		if ( errorMessage ) {
			errorMessage.textContent   = message;
			errorMessage.style.display = 'block';
		}

		nextButton.disabled    = false;
		nextButton.textContent = 'Submit Build Request';
	}

	nextButton.addEventListener( 'click', function () {

		// If we are already on the lead capture step, this is the real
		// submission click.
		if ( currentIndex === leadStepIndex ) {

			if ( ! isLeadFormValid() ) {
				return;
			}

			submitBuildRequest();
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
