/**
 * Bespoke Bike Builder - Thumbnail Gallery + Click-to-Enlarge Lightbox
 *
 * This is a fully additive, self-contained companion to builder.js.
 * It never edits builder.js or the markup rendered by
 * class-bbb-shortcodes.php - it only reads the DOM that already
 * exists and adds new elements alongside it. This means it is safe
 * to enqueue independently of the main builder script, and a bug
 * here cannot break navigation, the Review step, or the real
 * AJAX submission.
 *
 * What it does (per the "Variant 1 + lightbox" brainstorm):
 *
 * 1. Thumbnail strip - directly under the preview image, shows one
 *    small thumbnail per option in the CURRENTLY ACTIVE tile step
 *    (e.g. while the customer is on "Frame Colour", it shows every
 *    colour photo available in that step, not just the one they've
 *    picked). This helps compare options before deciding. It has
 *    nothing to show on dropdown-based steps or steps with no
 *    photos, and is simply hidden then.
 *
 * 2. Click-to-enlarge lightbox - clicking any thumbnail in that
 *    strip, OR any of the small chips in the existing "selected
 *    summary" list (Option C), opens a full-viewport dark overlay
 *    with the photo shown large. Closes on the "x" button, clicking
 *    the backdrop, or pressing Escape.
 *
 * How it detects step changes: builder.js toggles the
 * "bbb-step-active" class on whichever .bbb-step should currently
 * show. Rather than hook into builder.js's internals, this file
 * watches for that class change with a MutationObserver and
 * re-renders the thumbnail strip whenever it fires. It also
 * re-renders whenever the selected-summary list's content changes
 * (so newly-added chips immediately become clickable).
 */

document.addEventListener( 'DOMContentLoaded', function () {

var wizard = document.querySelector( '.bbb-builder-placeholder' );

// If this page doesn't contain our shortcode, do nothing.
if ( ! wizard ) {
return;
}

var imagePanel = wizard.querySelector( '.bbb-builder-image-panel' );

if ( ! imagePanel ) {
return;
}

var summaryList = imagePanel.querySelector( '.bbb-selected-summary' );

/* -----------------------------------------------------------
   1. Inject this feature's own CSS, once.
   ----------------------------------------------------------- */

var style = document.createElement( 'style' );
style.id = 'bbb-thumbnail-gallery-styles';
style.textContent =
'.bbb-thumb-strip{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;}' +
'.bbb-thumb-strip-item{width:52px;height:52px;border-radius:6px;background-size:cover;background-position:center;background-color:#0d0d0d;border:2px solid #333333;cursor:pointer;transition:border-color 0.15s ease;padding:0;}' +
'.bbb-thumb-strip-item:hover{border-color:#3f6bab;}' +
'.bbb-thumb-strip-item--active{border-color:#3f6bab;box-shadow:0 0 0 1px #3f6bab;}' +
'.bbb-summary-row-chip{cursor:pointer;}' +
'.bbb-lightbox-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:20000;display:flex;align-items:center;justify-content:center;padding:32px;box-sizing:border-box;}' +
'.bbb-lightbox-image{max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,0.6);}' +
'.bbb-lightbox-close{position:absolute;top:20px;right:24px;background:none;border:none;color:#ffffff;font-size:32px;line-height:1;cursor:pointer;padding:4px 10px;}' +
'.bbb-lightbox-close:hover{color:#9aa5b1;}';
document.head.appendChild( style );

/* -----------------------------------------------------------
   2. Thumbnail strip element, inserted right after the
      preview image (and before the selected-summary list, if
      it exists, so the visual order stays: image, thumbnails,
      summary).
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

var lightboxClose = document.createElement( 'button' );
lightboxClose.type = 'button';
lightboxClose.className = 'bbb-lightbox-close';
lightboxClose.setAttribute( 'aria-label', 'Close' );
lightboxClose.textContent = '\u00D7';
lightbox.appendChild( lightboxClose );

document.body.appendChild( lightbox );

function openLightbox( imageUrl ) {

if ( ! imageUrl ) {
return;
}

lightboxImage.src = imageUrl;
lightbox.style.display = 'flex';
}

function closeLightbox() {

lightbox.style.display = 'none';
lightboxImage.src = '';
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
   4. Building the thumbnail strip for whichever step is
      currently active.
   ----------------------------------------------------------- */

function getLargeUrlForTile( tile ) {

// data-image-url holds the "large" preview image builder.js
// already uses for the main preview photo; falls back to the
// smaller card photo if that attribute isn't present for some
// reason, so a thumbnail is still clickable either way.
var large = tile.getAttribute( 'data-image-url' );

if ( large ) {
return large;
}

var imageDiv = tile.querySelector( '.bbb-tile-image' );

if ( ! imageDiv ) {
return '';
}

var style = imageDiv.style.backgroundImage || '';
var match = style.match( /url\((?:"|')?([^"')]+)(?:"|')?\)/ );

return match ? match[ 1 ] : '';
}

function refreshThumbnailStrip() {

var activeStep = wizard.querySelector( '.bbb-step.bbb-step-active' );

thumbStrip.innerHTML = '';

if ( ! activeStep ) {
thumbStrip.style.display = 'none';
return;
}

var tiles = activeStep.querySelectorAll( '.bbb-tile-with-image' );

if ( ! tiles.length ) {
thumbStrip.style.display = 'none';
return;
}

tiles.forEach( function ( tile ) {

var smallImageDiv = tile.querySelector( '.bbb-tile-image' );
var smallStyle = smallImageDiv ? smallImageDiv.style.backgroundImage : '';
var largeUrl = getLargeUrlForTile( tile );

var thumbButton = document.createElement( 'button' );
thumbButton.type = 'button';
thumbButton.className = 'bbb-thumb-strip-item';

if ( tile.classList.contains( 'bbb-tile-selected' ) ) {
thumbButton.classList.add( 'bbb-thumb-strip-item--active' );
}

thumbButton.style.backgroundImage = smallStyle;
thumbButton.setAttribute( 'aria-label', tile.getAttribute( 'data-value' ) || 'View larger image' );

thumbButton.addEventListener( 'click', function ( event ) {
// Stop this click from bubbling up into the tile's own
// click handler (which would change the customer's
// selection just from viewing a larger photo).
event.stopPropagation();
openLightbox( largeUrl || smallStyle );
} );

thumbStrip.appendChild( thumbButton );
} );

thumbStrip.style.display = 'flex';
}

/* -----------------------------------------------------------
   5. Making the existing selected-summary chips clickable too
      (the small bonus from the brainstorm). Delegated so it
      keeps working as rows are re-rendered by builder.js.
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
   6. Watch for step changes (and tile selection changes, which
      also toggle bbb-tile-selected) and re-render the strip.
   ----------------------------------------------------------- */

var stepsContainer = wizard.querySelector( '.bbb-builder-form-panel' ) || wizard;

var observer = new MutationObserver( function () {
refreshThumbnailStrip();
} );

observer.observe( stepsContainer, {
attributes: true,
attributeFilter: [ 'class' ],
subtree: true
} );

// Render once immediately for the first step shown on page load.
refreshThumbnailStrip();
} );
