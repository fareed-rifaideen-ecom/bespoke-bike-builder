/**
 * Bespoke Bike Builder - Frame Colour Hero Image + Other Options
 * Gallery + Click-to-Enlarge Lightbox
 *
 * This is a fully additive, self-contained companion to builder.js.
 * It never edits builder.js or the markup rendered by
 * class-bbb-shortcodes.php - it only reads the DOM that already
 * exists and reacts to it. A bug here cannot break navigation, the
 * Review step, or the real AJAX submission.
 *
 * Behaviour (per client request, replacing the earlier "current step
 * alternatives" version of this file):
 *
 * 1. Main preview image is LOCKED to whatever the customer has
 *    picked in the "Frame Colour" step - this is "the bike" the
 *    customer is building, so it should always be what's shown
 *    large, no matter what step they're currently looking at or
 *    what they pick afterwards (Cockpit, Groupset, Wheelset, etc.).
 *    builder.js itself updates the same image element on every
 *    photo-bearing tile click; this script watches for that and, if
 *    the change wasn't the Frame Colour photo, immediately restores
 *    the Frame Colour photo instead.
 *
 * 2. A gallery strip directly under the main image shows a small
 *    thumbnail for every OTHER option group the customer has already
 *    answered that has a photo (e.g. once they've picked a Cockpit
 *    and a Groupset, both thumbnails appear there, growing as they
 *    progress). Frame Colour itself is excluded from this strip,
 *    since it's already the main image.
 *
 * 3. Clicking any thumbnail here, or any of the small chips in the
 *    existing "selected summary" list (Option C), opens a
 *    full-viewport dark lightbox with that photo shown large.
 *    Closes on the "x" button, clicking the backdrop, or Escape.
 *
 * How it detects changes: rather than hook into builder.js's
 * internals, this file watches the whole wizard with a
 * MutationObserver (class changes -> tile selected/unselected, style
 * changes -> builder.js updating the main image) and recomputes
 * both the hero image and the gallery strip whenever anything
 * relevant changes. A short observer disconnect/reconnect guard is
 * used while this script itself writes to the main image, so it
 * never triggers its own mutation callback in a loop.
 */

document.addEventListener( 'DOMContentLoaded', function () {

var wizard = document.querySelector( '.bbb-builder-placeholder' );

// If this page doesn't contain our shortcode, do nothing.
if ( ! wizard ) {
return;
}

var imagePanel = wizard.querySelector( '.bbb-builder-image-panel' );
var mainImage  = wizard.querySelector( '.bbb-selected-image' );

if ( ! imagePanel || ! mainImage ) {
return;
}

var summaryList = imagePanel.querySelector( '.bbb-selected-summary' );

// Every option step, in order, as rendered by class-bbb-shortcodes.php.
// Review and lead-capture steps don't carry a data-group-label, so
// they're naturally excluded from anything below that reads it.
var optionSteps = wizard.querySelectorAll( '.bbb-step[data-group-label]' );

/* -----------------------------------------------------------
   1. Inject this feature's own CSS, once.
   ----------------------------------------------------------- */

var style = document.createElement( 'style' );
style.id = 'bbb-thumbnail-gallery-styles';
style.textContent =
'.bbb-thumb-strip{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;}' +
'.bbb-thumb-strip-item{width:52px;height:52px;border-radius:6px;background-size:cover;background-position:center;background-color:#0d0d0d;border:2px solid #333333;cursor:pointer;transition:border-color 0.15s ease;padding:0;}' +
'.bbb-thumb-strip-item:hover{border-color:#3f6bab;}' +
'.bbb-summary-row-chip{cursor:pointer;}' +
'.bbb-lightbox-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:20000;display:flex;align-items:center;justify-content:center;padding:32px;box-sizing:border-box;}' +
'.bbb-lightbox-image{max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,0.6);}' +
'.bbb-lightbox-caption{position:absolute;bottom:24px;left:0;right:0;text-align:center;color:#e8e8e8;font-size:13px;letter-spacing:0.03em;}' +
'.bbb-lightbox-close{position:absolute;top:20px;right:24px;background:none;border:none;color:#ffffff;font-size:32px;line-height:1;cursor:pointer;padding:4px 10px;}' +
'.bbb-lightbox-close:hover{color:#9aa5b1;}';
document.head.appendChild( style );

/* -----------------------------------------------------------
   2. Thumbnail strip element, inserted right after the main
      image (and before the selected-summary list, if it
      exists, so the visual order stays: image, gallery, list).
   ----------------------------------------------------------- */

var thumbStrip = document.createElement( 'div' );
thumbStrip.className = 'bbb-thumb-strip';
thumbStrip.style.display = 'none';

if ( summaryList ) {
imagePanel.insertBefore( thumbStrip, summaryList );
} else {
imagePanel.appendChild( thumbStrip );
}

/* -----------------------------------------------------------
   3. Lightbox: a single reusable overlay, created once and
      shown/hidden as needed.
   ----------------------------------------------------------- */

var lightbox = document.createElement( 'div' );
lightbox.className = 'bbb-lightbox-overlay';
lightbox.style.display = 'none';

var lightboxImage = document.createElement( 'img' );
lightboxImage.className = 'bbb-lightbox-image';
lightbox.appendChild( lightboxImage );

var lightboxCaption = document.createElement( 'div' );
lightboxCaption.className = 'bbb-lightbox-caption';
lightbox.appendChild( lightboxCaption );

var lightboxClose = document.createElement( 'button' );
lightboxClose.type = 'button';
lightboxClose.className = 'bbb-lightbox-close';
lightboxClose.setAttribute( 'aria-label', 'Close' );
lightboxClose.textContent = '\u00D7';
lightbox.appendChild( lightboxClose );

document.body.appendChild( lightbox );

function openLightbox( imageUrl, caption ) {

if ( ! imageUrl ) {
return;
}

lightboxImage.src = imageUrl;
lightboxCaption.textContent = caption || '';
lightbox.style.display = 'flex';
}

function closeLightbox() {

lightbox.style.display = 'none';
lightboxImage.src = '';
lightboxCaption.textContent = '';
}

lightboxClose.addEventListener( 'click', closeLightbox );

// Clicking the dark backdrop (but not the image itself) also closes it.
lightbox.addEventListener( 'click', function ( event ) {

if ( event.target === lightbox ) {
closeLightbox();
}
} );

document.addEventListener( 'keydown', function ( event ) {

if ( 'Escape' === event.key && 'none' !== lightbox.style.display ) {
closeLightbox();
}
} );

/* -----------------------------------------------------------
   4. Helpers for reading a step's currently selected photo.
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

function getSelectedPhotoForStep( step ) {

var selectedTile = step.querySelector( '.bbb-tile.bbb-tile-selected' );

if ( ! selectedTile ) {
return null;
}

var url = getTileImageUrl( selectedTile );

if ( ! url ) {
return null;
}

return {
url: url,
label: selectedTile.getAttribute( 'data-value' ) || step.getAttribute( 'data-group-label' ) || ''
};
}

/* -----------------------------------------------------------
   5. Core refresh: locks the main image to Frame Colour, and
      rebuilds the "other options" gallery strip.
   ----------------------------------------------------------- */

var applyingHeroImage = false;

function refreshHeroAndGallery() {

var colourPhoto = null;
var otherPhotos = [];

optionSteps.forEach( function ( step ) {

var label = step.getAttribute( 'data-group-label' );
var photo = getSelectedPhotoForStep( step );

if ( ! photo ) {
return;
}

if ( isColourGroup( label ) ) {
colourPhoto = photo;
} else {
otherPhotos.push( photo );
}
} );

// --- Lock the main image to the Frame Colour selection. ---

if ( colourPhoto ) {

var desiredBg = 'url("' + colourPhoto.url + '")';

if ( mainImage.style.backgroundImage !== desiredBg ) {

applyingHeroImage = true;
mainImage.style.backgroundImage = desiredBg;
mainImage.classList.remove( 'bbb-image-panel--empty' );

var placeholder = mainImage.querySelector( '.bbb-image-panel-placeholder' );

if ( placeholder ) {
placeholder.style.display = 'none';
}

// Let the mutation this triggers flush before re-enabling the
// guard, so our own change doesn't cause an infinite loop.
window.setTimeout( function () {
applyingHeroImage = false;
}, 0 );
}
}

// --- Rebuild the "other options" thumbnail strip. ---

thumbStrip.innerHTML = '';

if ( ! otherPhotos.length ) {
thumbStrip.style.display = 'none';
return;
}

otherPhotos.forEach( function ( photo ) {

var thumbButton = document.createElement( 'button' );
thumbButton.type = 'button';
thumbButton.className = 'bbb-thumb-strip-item';
thumbButton.style.backgroundImage = 'url("' + photo.url + '")';
thumbButton.setAttribute( 'aria-label', photo.label || 'View larger image' );

thumbButton.addEventListener( 'click', function ( event ) {
event.stopPropagation();
openLightbox( photo.url, photo.label );
} );

thumbStrip.appendChild( thumbButton );
} );

thumbStrip.style.display = 'flex';
}

/* -----------------------------------------------------------
   6. Making the existing selected-summary chips clickable too.
   ----------------------------------------------------------- */

if ( summaryList ) {

summaryList.addEventListener( 'click', function ( event ) {

var chip = event.target.closest( '.bbb-summary-row-chip' );

if ( ! chip ) {
return;
}

var chipStyle = chip.style.backgroundImage || '';
var match = chipStyle.match( /url\((?:"|')?([^"')]+)(?:"|')?\)/ );

if ( match ) {
openLightbox( match[ 1 ] );
}
} );
}

/* -----------------------------------------------------------
   7. Watch the wizard for tile selection changes (class) and
      builder.js's own main-image updates (style), and
      recompute the hero image + gallery whenever either fires.
   ----------------------------------------------------------- */

var observer = new MutationObserver( function () {

if ( applyingHeroImage ) {
return;
}

refreshHeroAndGallery();
} );

observer.observe( wizard, {
attributes: true,
attributeFilter: [ 'class', 'style' ],
subtree: true
} );

// Render once immediately, in case a draft resume already has a
// Frame Colour (and other) selections in place on page load.
refreshHeroAndGallery();
} );
