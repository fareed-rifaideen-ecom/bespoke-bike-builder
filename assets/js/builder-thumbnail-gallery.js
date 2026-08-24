/**
 * Bespoke Bike Builder - Frame Colour Default Image + Hover Preview
 *
 * This is a fully additive, self-contained companion to builder.js.
 * It never edits builder.js or the markup rendered by
 * class-bbb-shortcodes.php - it only reads the DOM that already
 * exists and reacts to it. A bug here cannot break navigation, the
 * Review step, or the real AJAX submission.
 *
 * Behaviour (per client request - replaces the earlier gallery/tile
 * grid version of this file entirely; there is no gallery under the
 * main image any more):
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
 * 2. HOVER PREVIEW: hovering the mouse over any photo-bearing option
 *    tile anywhere in the wizard (on the right-hand form panel)
 *    immediately swaps the main image to show that option's photo,
 *    for a quick look before deciding. Moving the mouse away
 *    (mouseleave) restores the default Frame Colour image (or the
 *    placeholder, if no colour is selected yet). This is a pure
 *    preview - it never changes the customer's actual selection;
 *    clicking a tile still works exactly as builder.js already
 *    handles it.
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

var bg = imageDiv.style.backgroundImage || '';
var match = bg.match( /url\((?:"|')?([^"')]+)(?:"|')?\)/ );

return match ? match[ 1 ] : '';
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
      skipped entirely while the customer is hovering a tile.
   ----------------------------------------------------------- */

var hoverActive = false;

function restoreDefaultImage() {

if ( hoverActive ) {
return;
}

setMainImage( getSelectedColourPhotoUrl() );
}

/* -----------------------------------------------------------
   4. Hover preview: every photo-bearing tile in the wizard
      temporarily shows its own photo on mouseenter, and
      restores the Frame Colour default on mouseleave.
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
