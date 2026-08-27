/**
 * Bespoke Bike Builder - Estimated Pricing Display
 *
 * This is a fully additive, self-contained companion to builder.js.
 * It is only ever enqueued when BBB_Pricing_Settings::is_enabled()
 * is true (Settings > BBB Pricing, off by default) - see
 * bespoke-bike-builder.php. It never edits builder.js or the markup
 * rendered by class-bbb-shortcodes.php; it only reads data-price
 * attributes that file already outputs (from each option's existing
 * price_delta field) and adds new elements alongside the existing
 * ones. A bug here cannot break navigation, the Review step's own
 * content, or the real AJAX submission.
 *
 * What it shows:
 *
 * 1. A running "Estimated Price" row in the left/image column,
 *    directly above the existing "selected summary" list, showing
 *    the sum of every currently selected option's price_delta. This
 *    updates live as the customer picks tiles or changes dropdowns.
 *
 * 2. On the Review step, the price (or "Included") is appended
 *    directly onto each row that builder.js already renders inside
 *    .bbb-review-content - there is no separate, duplicate list.
 *    A single bold "Estimated Total" row is added at the very end of
 *    that same card. Because builder.js fully rebuilds
 *    .bbb-review-content's innerHTML every time the Review step is
 *    shown (see its populateReview function), this script re-applies
 *    the price annotations every time that happens, using a
 *    MutationObserver scoped to childList changes on that element -
 *    builder.js's own row markup, classes and text are never altered,
 *    only appended to.
 *
 * All totals shown here are estimates for the customer's convenience
 * only. The authoritative total used internally is always
 * recalculated server-side from the database when the build request
 * is actually submitted (see class-bbb-ajax.php) - this script's
 * numbers are never sent to or trusted by the server.
 *
 * How it detects selection changes: since dropdown selections don't
 * toggle any CSS class, this uses both a MutationObserver (for tile
 * clicks, which do toggle bbb-tile-selected) and a 'change' listener
 * on every dropdown, then recomputes the total from scratch each
 * time by reading the DOM - it never needs access to builder.js's
 * internal selection state.
 */

document.addEventListener( 'DOMContentLoaded', function () {

var wizard = document.querySelector( '.bbb-builder-placeholder' );

// If this page doesn't contain our shortcode, or pricing wasn't
// actually enabled for this render, do nothing.
if ( ! wizard || ! wizard.hasAttribute( 'data-pricing-enabled' ) ) {
return;
}

var imagePanel  = wizard.querySelector( '.bbb-builder-image-panel' );
var summaryList = wizard.querySelector( '.bbb-selected-summary' );
var reviewContent = wizard.querySelector( '.bbb-review-content' );

// Every option step, in order, as rendered by class-bbb-shortcodes.php.
var optionSteps = wizard.querySelectorAll( '.bbb-step[data-group-label]' );

/* -----------------------------------------------------------
   1. Inject this feature's own CSS, once.
   ----------------------------------------------------------- */

var style = document.createElement( 'style' );
style.id = 'bbb-pricing-styles';
style.textContent =
'.bbb-price-summary{background:#1b2634;border:1px solid #3f6bab;border-radius:8px;padding:12px 16px;margin-top:14px;display:flex;justify-content:space-between;align-items:center;}' +
'.bbb-price-summary-label{color:#9aa5b1;font-size:13px;text-transform:uppercase;letter-spacing:0.05em;}' +
'.bbb-price-summary-value{color:#ffffff;font-size:18px;font-weight:bold;}' +
'.bbb-review-row-price{color:#7fe0a0;font-weight:bold;margin-left:10px;white-space:nowrap;}' +
'.bbb-review-row-price.bbb-review-row-price--included{color:#9aa5b1;font-weight:normal;font-style:italic;}' +
'.bbb-review-total-row{display:flex;justify-content:space-between;padding:14px 0 4px;margin-top:4px;border-top:1px solid #2c2c2c;font-size:16px;}' +
'.bbb-review-total-row .bbb-review-total-label{color:#ffffff;font-weight:bold;}' +
'.bbb-review-total-row .bbb-review-total-value{color:#7fe0a0;font-size:18px;font-weight:bold;}';
document.head.appendChild( style );

/* -----------------------------------------------------------
   2. Formatting helper.
   ----------------------------------------------------------- */

function formatPrice( amount ) {

var rounded = Math.round( amount * 100 ) / 100;
var hasDecimals = rounded % 1 !== 0;

return 'AED ' + rounded.toLocaleString( undefined, {
minimumFractionDigits: hasDecimals ? 2 : 0,
maximumFractionDigits: 2
} );
}

/* -----------------------------------------------------------
   3. Reading the current selections purely from the DOM, so
      this never needs access to builder.js's internal state.
   ----------------------------------------------------------- */

function getCurrentSelections() {

var selections = [];

optionSteps.forEach( function ( step ) {

var groupLabel = step.getAttribute( 'data-group-label' ) || '';
var dropdown = step.querySelector( '.bbb-dropdown' );

if ( dropdown ) {

if ( ! dropdown.value ) {
return;
}

var chosenOption = dropdown.options[ dropdown.selectedIndex ];
var price = chosenOption.hasAttribute( 'data-price' ) ? parseFloat( chosenOption.getAttribute( 'data-price' ) ) : 0;

selections.push( {
groupLabel: groupLabel,
optionLabel: chosenOption.getAttribute( 'data-label' ) || chosenOption.textContent.trim(),
price: isNaN( price ) ? 0 : price
} );

return;
}

var selectedTile = step.querySelector( '.bbb-tile.bbb-tile-selected' );

if ( ! selectedTile ) {
return;
}

var tilePrice = selectedTile.hasAttribute( 'data-price' ) ? parseFloat( selectedTile.getAttribute( 'data-price' ) ) : 0;

selections.push( {
groupLabel: groupLabel,
optionLabel: selectedTile.getAttribute( 'data-value' ) || '',
price: isNaN( tilePrice ) ? 0 : tilePrice
} );
} );

return selections;
}

function getCurrentTotal() {

var total = 0;

getCurrentSelections().forEach( function ( selection ) {
total += selection.price;
} );

return total;
}

/* -----------------------------------------------------------
   4. The running "Estimated Price" row in the left column,
      inserted once, directly above the existing selected
      summary list.
   ----------------------------------------------------------- */

var priceSummary = null;
var priceSummaryValue = null;

if ( imagePanel ) {

priceSummary = document.createElement( 'div' );
priceSummary.className = 'bbb-price-summary';

var priceSummaryLabel = document.createElement( 'span' );
priceSummaryLabel.className = 'bbb-price-summary-label';
priceSummaryLabel.textContent = 'Estimated Price';
priceSummary.appendChild( priceSummaryLabel );

priceSummaryValue = document.createElement( 'span' );
priceSummaryValue.className = 'bbb-price-summary-value';
priceSummary.appendChild( priceSummaryValue );

if ( summaryList ) {
imagePanel.insertBefore( priceSummary, summaryList );
} else {
imagePanel.appendChild( priceSummary );
}
}

function refreshPriceSummary() {

if ( priceSummaryValue ) {
priceSummaryValue.textContent = formatPrice( getCurrentTotal() );
}
}

/* -----------------------------------------------------------
   5. Annotating the Review step's OWN rows with a price, and
      appending a single total row at the end of that same
      card - instead of building a separate, duplicate list.

      builder.js fully replaces .bbb-review-content's innerHTML
      every time the Review step is shown (see its populateReview
      function), so this re-applies on every such rebuild via a
      childList MutationObserver scoped to that one element. The
      observer is disconnected while this function itself edits
      the DOM, so its own additions never re-trigger it.
   ----------------------------------------------------------- */

var reviewObserver = null;

function annotateReviewRows() {

if ( ! reviewContent ) {
return;
}

// Nothing to annotate if builder.js hasn't rendered any rows yet
// (e.g. the Review step has never been visited).
var rows = reviewContent.querySelectorAll( '.bbb-review-row' );

if ( ! rows.length ) {
return;
}

if ( reviewObserver ) {
reviewObserver.disconnect();
}

var selections = getCurrentSelections();
var total = 0;

rows.forEach( function ( row ) {

// Guard against double-annotating a row this script already
// touched (defensive only - rows are normally rebuilt fresh by
// builder.js before this runs).
var existingPriceEl = row.querySelector( '.bbb-review-row-price' );
if ( existingPriceEl ) {
existingPriceEl.parentNode.removeChild( existingPriceEl );
}

var labelEl = row.querySelector( '.bbb-review-label' );
var valueEl = row.querySelector( '.bbb-review-value' );

if ( ! labelEl || ! valueEl ) {
return;
}

var rowLabel = labelEl.textContent.trim();

var matchingSelection = null;
for ( var i = 0; i < selections.length; i++ ) {
if ( selections[ i ].groupLabel === rowLabel ) {
matchingSelection = selections[ i ];
break;
}
}

if ( ! matchingSelection ) {
return;
}

total += matchingSelection.price;

var priceEl = document.createElement( 'span' );
priceEl.className = 'bbb-review-row-price';

if ( matchingSelection.price ) {
priceEl.textContent = formatPrice( matchingSelection.price );
} else {
priceEl.className += ' bbb-review-row-price--included';
priceEl.textContent = 'Included';
}

valueEl.appendChild( priceEl );
} );

var existingTotalRow = reviewContent.querySelector( '.bbb-review-total-row' );
if ( existingTotalRow ) {
existingTotalRow.parentNode.removeChild( existingTotalRow );
}

var totalRow = document.createElement( 'div' );
totalRow.className = 'bbb-review-total-row';

var totalLabel = document.createElement( 'span' );
totalLabel.className = 'bbb-review-total-label';
totalLabel.textContent = 'Estimated Total';
totalRow.appendChild( totalLabel );

var totalValue = document.createElement( 'span' );
totalValue.className = 'bbb-review-total-value';
totalValue.textContent = formatPrice( total );
totalRow.appendChild( totalValue );

reviewContent.appendChild( totalRow );

if ( reviewObserver ) {
reviewObserver.observe( reviewContent, { childList: true } );
}
}

if ( reviewContent ) {
reviewObserver = new MutationObserver( function () {
annotateReviewRows();
} );
reviewObserver.observe( reviewContent, { childList: true } );
}

/* -----------------------------------------------------------
   6. Wiring up selection-change detection: a MutationObserver
      for tile clicks (which toggle bbb-tile-selected), plus a
      direct 'change' listener on every dropdown (which don't
      toggle any class at all). Both simply refresh the running
      total and re-annotate the Review rows if they're currently
      rendered - cheap no-ops otherwise.
   ----------------------------------------------------------- */

function refreshPricing() {
refreshPriceSummary();
annotateReviewRows();
}

var selectionObserver = new MutationObserver( function () {
refreshPricing();
} );

selectionObserver.observe( wizard, {
attributes: true,
attributeFilter: [ 'class' ],
subtree: true
} );

wizard.querySelectorAll( '.bbb-dropdown' ).forEach( function ( dropdown ) {
dropdown.addEventListener( 'change', refreshPricing );
} );

// Render once immediately, in case a draft resume already has
// selections in place on page load.
refreshPricing();
} );
