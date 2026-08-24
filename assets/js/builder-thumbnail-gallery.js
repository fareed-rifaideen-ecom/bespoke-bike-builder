/**
 * Bespoke Bike Builder - Frame Colour Default Image + Hover Preview
 *
 * This is a fully additive, self-contained companion to builder.js.
 * It never edits builder.js or the markup rendered by
 * class-bbb-shortcodes.php - it only reads the DOM that already
 * exists and reacts to it. A bug here cannot break navigation, the
 * Review step, or the real AJAX submission.
 *
 * Behaviour (no gallery grid under the main image at all):
 *
 * 1. DEFAULT main image is always the customer's Frame Colour
 *    selection - this is "the bike" they're building. builder.js
 *    itself updates the same image element on every photo-bearing
 *    tile click; whenever that happens (and the customer isn't
 *    actively hovering something), this script restores the Frame
 *    Colour photo as the main image. If no colour has been picked
 *    yet, the original placeholder text is shown instead, exactly
 *    as builder.js already renders it.
 *
 * 2. HOVER PREVIEW works in two places:
 *
 *    a) Any photo-bearing option tile anywhere in the wizard's
 *       right-hand form panel (e.g. Cockpit, Groupset, Wheelset
 *       tiles) - hovering shows that option's photo in the main
 *       image area.
 *
 *    b) Each row of the "selected summary" list (Build Type, Frame
 *       Colour, Frame Size, Cockpit, Groupset, Wheelset, etc., shown
 *       under the main image with a small icon chip) - hovering a
 *       row shows that row's photo in the main image area too.
 *       Because builder.js rebuilds this list's rows every time a
 *       selection changes, listeners are attached once to the list's
 *       container and delegated to whichever row the mouse is over,
 *       so they keep working even after the rows are replaced.
 *
 *    In both cases, moving the mouse away restores the default Frame
 *    Colour image (or the placeholder, if no colour is selected
 *    yet). This is a pure preview - it never changes the customer's
 *    actual selection; clicking a tile still works exactly as
 *    builder.js already handles it.
 *
 * How it detects changes: rather than hook into builder.js's
 * internals, this file watches the whole wizard with a
 * MutationObserver (class changes -> tile selected/unselected, style
 * changes -> builder.js's own main-image updates) and restores the
 * Frame Colour default whenever something changes outside of an
 * active hover. A short guard is used while this script itself
 * writes to the main image, so it never triggers its own mutation
 * callback in a loop.
 */

document.addEventListener( 'DOMContentLoaded', function () {

var wizard = document.querySelector( '.bbb-builder-placeholder' );

// If this page doesn't contain our shortcode, do nothing.
if ( ! wizard ) {
return;
}

var mainImage = wizard.querySelector( '.bbb-selected-image' );

if ( ! mainImage ) {
return;
}

var placeholder = mainImage.querySelector( '.bbb-image-panel-placeholder' );

var summaryList = wizard.querySelector( '.bbb-selected-summary' );

// Every option step, in order, as rendered by class-bbb-shortcodes.php.
// Review and lead-capture steps don't carry a data-group-label, so
// they're naturally excluded from anything below that reads it.
var optionSteps = wizard.querySelectorAll( '.bbb-step[data-group-label]' );

/* -----------------------------------------------------------
   1. Helpers.
   ----------------------------------------------------------- */

function isColourGroup( label ) {

if ( ! label ) {
return false;
}

var normalised = label.toLowerCase();

return normalised.indexOf( 'colour' ) !== -1 || normalised.indexOf( 'color' ) !== -1;
}

function extractUrlFromCss( cssValue ) {

if ( ! cssValue ) {
return '';
}

var match = cssValue.match( /url\((?:"|')?([^"')]+)(?:"|')?\)/ );

return match ? match[ 1 ] : '';
}

function getTileImageUrl( tile ) {

// data-image-url holds the "large" preview image builder.js
// already uses for its own preview swap; falls back to the
// smaller card photo if that attribute isn't present.
var large = tile.getAttribute( 'data-image-url' );

if ( large ) {
return large;
}

var imageDiv = tile.querySelector( '.bbb-tile-image' );

if ( ! imageDiv ) {
return '';
}

return extractUrlFromCss( imageDiv.style.backgroundImage );
}

function getSelectedColourPhotoUrl() {

var colourUrl = null;

optionSteps.forEach( function ( step ) {

if ( ! isColourGroup( step.getAttribute( 'data-group-label' ) ) ) {
return;
}

var selectedTile = step.querySelector( '.bbb-tile.bbb-tile-selected' );

if ( selectedTile ) {
colourUrl = getTileImageUrl( selectedTile ) || null;
}
} );

return colourUrl;
}

/* -----------------------------------------------------------
   2. Writing to the main image (shared by both the default
      state and hover previews), with a guard so our own writes
      never re-trigger the MutationObserver below in a loop.
   ----------------------------------------------------------- */

var applyingImage = false;

function setMainImage( url ) {

applyingImage = true;

if ( url ) {

mainImage.style.backgroundImage = 'url("' + url + '")';
mainImage.classList.remove( 'bbb-image-panel--empty' );

if ( placeholder ) {
placeholder.style.display = 'none';
}

} else {

mainImage.style.backgroundImage = '';
mainImage.classList.add( 'bbb-image-panel--empty' );

if ( placeholder ) {
placeholder.style.display = '';
}
}

// Let the mutation this triggers flush before re-enabling the
// guard, so our own change doesn't cause an infinite loop.
window.setTimeout( function () {
applyingImage = false;
}, 0 );
}

/* -----------------------------------------------------------
   3. Restoring the Frame Colour default (or placeholder),
      skipped entirely while the customer is hovering something.
   ----------------------------------------------------------- */

var hoverActive = false;

function restoreDefaultImage() {

if ( hoverActive ) {
return;
}

setMainImage( getSelectedColourPhotoUrl() );
}

/* -----------------------------------------------------------
   4a. Hover preview on the option tiles themselves (Cockpit,
       Groupset, Wheelset, etc., wherever the customer is
       currently choosing).
   ----------------------------------------------------------- */

var photoTiles = wizard.querySelectorAll( '.bbb-tile-with-image' );

photoTiles.forEach( function ( tile ) {

tile.addEventListener( 'mouseenter', function () {

var url = getTileImageUrl( tile );

if ( ! url ) {
return;
}

hoverActive = true;
setMainImage( url );
} );

tile.addEventListener( 'mouseleave', function () {

hoverActive = false;
restoreDefaultImage();
} );
} );

/* -----------------------------------------------------------
   4b. Hover preview on the "selected summary" list rows
       (Build Type, Frame Colour, Frame Size, Cockpit,
       Groupset, Wheelset, etc.). builder.js rebuilds this
       list's rows every time a selection changes, so listeners
       are delegated from the stable list container down to
       whichever row the mouse is currently over, rather than
       attached to the rows directly (which would be destroyed
       and silently stop working after the next selection).
   ----------------------------------------------------------- */

if ( summaryList ) {

var hoveredSummaryRow = null;

summaryList.addEventListener( 'mouseover', function ( event ) {

var row = event.target.closest( '.bbb-summary-row' );

if ( ! row || row === hoveredSummaryRow ) {
return;
}

var chip = row.querySelector( '.bbb-summary-row-chip' );
var url = chip ? extractUrlFromCss( chip.style.backgroundImage ) : '';

if ( ! url ) {
return;
}

hoveredSummaryRow = row;
hoverActive = true;
setMainImage( url );
} );

summaryList.addEventListener( 'mouseout', function ( event ) {

var row = event.target.closest( '.bbb-summary-row' );

if ( ! row || row !== hoveredSummaryRow ) {
return;
}

// Only treat this as "leaving the row" if the mouse has moved
// somewhere outside of it, not just to another element inside it.
if ( row.contains( event.relatedTarget ) ) {
return;
}

hoveredSummaryRow = null;
hoverActive = false;
restoreDefaultImage();
} );
}

/* -----------------------------------------------------------
   5. Watch the wizard for tile selection changes (class) and
      builder.js's own main-image updates (style), and restore
      the Frame Colour default whenever either fires (unless a
      hover preview is currently active).
   ----------------------------------------------------------- */

var observer = new MutationObserver( function () {

if ( applyingImage ) {
return;
}

restoreDefaultImage();
} );

observer.observe( wizard, {
attributes: true,
attributeFilter: [ 'class', 'style' ],
subtree: true
} );

// Apply once immediately, in case a draft resume already has a
// Frame Colour selection in place on page load.
restoreDefaultImage();
} );
