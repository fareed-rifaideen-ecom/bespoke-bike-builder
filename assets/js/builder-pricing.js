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
 * 2. A price breakdown appended just below the existing Review step
 *    content (builder.js's own review rows are never touched or
 *    replaced) - one line per selected option that carries a
 *    nonzero price, "Included" for anything selected with a price of
 *    zero, and a bold grand total at the bottom.
 *
 * All totals shown here are estimates for the customer's convenience
 * only. The authoritative total used internally is always
 * recalculated server-side from the database when the build request
 * is actually submitted (see class-bbb-ajax.php) - this script's
 * numbers are never sent to or trusted by the server.
 *
 * How it detects changes: since dropdown selections don't toggle any
 * CSS class, this uses both a MutationObserver (for tile clicks,
 * which do toggle bbb-tile-selected) and a 'change' listener on every
 * dropdown, then recomputes the total from scratch each time by
 * reading the DOM - it never needs access to builder.js's internal
 * selection state.
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
var reviewStep  = wizard.querySelector( '.bbb-review-step' );
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
'.bbb-price-breakdown{background:#1b1b1b;border:1px solid #2c2c2c;border-radius:8px;padding:6px 20px;margin-top:16px;}' +
'.bbb-price-breakdown-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #2c2c2c;font-size:14px;}' +
'.bbb-price-breakdown-row:last-child{border-bottom:none;}' +
'.bbb-price-breakdown-label{color:#9aa5b1;}' +
'.bbb-price-breakdown-value{color:#ffffff;font-weight:bold;}' +
'.bbb-price-breakdown-total{display:flex;justify-content:space-between;padding:14px 0 4px;font-size:16px;}' +
'.bbb-price-breakdown-total .bbb-price-breakdown-label{color:#ffffff;font-weight:bold;}' +
'.bbb-price-breakdown-total .bbb-price-breakdown-value{color:#7fe0a0;font-size:18px;}';
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

/* -----------------------------------------------------------
   5. The price breakdown appended below the existing Review
      step content, created once and updated in place.
   ----------------------------------------------------------- */

var priceBreakdown = null;

if ( reviewStep ) {

priceBreakdown = document.createElement( 'div' );
priceBreakdown.className = 'bbb-price-breakdown';
reviewStep.appendChild( priceBreakdown );
}

/* -----------------------------------------------------------
   6. Recomputing everything whenever a selection changes.
   ----------------------------------------------------------- */

function refreshPricing() {

var selections = getCurrentSelections();
var total = 0;

selections.forEach( function ( selection ) {
total += selection.price;
} );

if ( priceSummaryValue ) {
priceSummaryValue.textContent = formatPrice( total );
}

if ( priceBreakdown ) {

priceBreakdown.innerHTML = '';

selections.forEach( function ( selection ) {

var row = document.createElement( 'div' );
row.className = 'bbb-price-breakdown-row';

var label = document.createElement( 'span' );
label.className = 'bbb-price-breakdown-label';
label.textContent = selection.groupLabel + ': ' + selection.optionLabel;
row.appendChild( label );

var value = document.createElement( 'span' );
value.className = 'bbb-price-breakdown-value';
value.textContent = selection.price ? formatPrice( selection.price ) : 'Included';
row.appendChild( value );

priceBreakdown.appendChild( row );
} );

var totalRow = document.createElement( 'div' );
totalRow.className = 'bbb-price-breakdown-total';

var totalLabel = document.createElement( 'span' );
totalLabel.className = 'bbb-price-breakdown-label';
totalLabel.textContent = 'Estimated Total';
totalRow.appendChild( totalLabel );

var totalValue = document.createElement( 'span' );
totalValue.className = 'bbb-price-breakdown-value';
totalValue.textContent = formatPrice( total );
totalRow.appendChild( totalValue );

priceBreakdown.appendChild( totalRow );
}
}

/* -----------------------------------------------------------
   7. Wiring up change detection: a MutationObserver for tile
      clicks (which toggle bbb-tile-selected), plus a direct
      'change' listener on every dropdown (which don't toggle
      any class at all).
   ----------------------------------------------------------- */

var observer = new MutationObserver( function () {
refreshPricing();
} );

observer.observe( wizard, {
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
