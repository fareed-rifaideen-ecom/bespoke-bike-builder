/* =========================================================
 * Bespoke Bike Builder — Notices & WhatsApp Layer
 * Additive script. Reads settings localized from PHP via
 * bbbNotices (wp_localize_script) — disclaimer text, checkbox
 * text, WhatsApp number and messages all come from the new
 * "BBB Notices" admin settings page, not hardcoded here.
 *
 * v4:
 *   - Fixed duplicate disclaimer bug on the confirmation step.
 *     Priority is now: success message banner > review banner >
 *     lead-form banner, checked in that order and stopping at
 *     the first match, since a submitted step can still have
 *     leftover (hidden) lead-form fields in the DOM alongside
 *     its now-visible success message.
 *   - Added a WhatsApp icon (inline SVG) to the Frame Size
 *     "Chat with us" link.
 * ========================================================= */

(function () {
  "use strict";

  var settings = window.bbbNotices || {};
  var whatsappNumber = (settings.whatsappNumber || "").replace(/[^\d+]/g, "");
  var disclaimerText =
    settings.disclaimerText ||
    "Your selected configuration is subject to availability, technical review and final confirmation by The Cycle Hub team. Submitting this request does not reserve any component or confirm an order.";
  var checkboxText =
    settings.checkboxText ||
    "I understand that this configuration is subject to availability and confirmation by The Cycle Hub team. Deposit terms apply.";
  var sizeMessage = settings.whatsappSizeMessage || "Hi, I need help choosing the right frame size for my Dogma F build.";

  var WHATSAPP_ICON_SVG =
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;">' +
    '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.149-.15.298-.347.446-.52.15-.174.199-.298.298-.497.099-.198.05-.372-.05-.52-.099-.148-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.076 4.487.709.306 1.262.489 1.694.626.712.226 1.36.194 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>' +
    '<path d="M12.012 2C6.486 2 2 6.486 2 12.012c0 1.987.582 3.833 1.586 5.386L2 22l4.706-1.552a9.94 9.94 0 0 0 5.306 1.516c5.526 0 10.012-4.486 10.012-10.012C22.024 6.486 17.538 2 12.012 2zm0 18.062a8.02 8.02 0 0 1-4.319-1.267l-.31-.192-3.06 1.01 1.02-2.98-.202-.32a8.01 8.01 0 0 1-1.24-4.29c0-4.436 3.612-8.048 8.05-8.048 4.436 0 8.048 3.612 8.048 8.048 0 4.437-3.612 8.04-8.048 8.04z"/>' +
    '</svg>';

  function getRoot() {
    return document.querySelector(".bbb-builder-placeholder");
  }

  function buildWhatsappLink(message) {
    if (!whatsappNumber) return null;
    var encoded = encodeURIComponent(message);
    return "https://wa.me/" + whatsappNumber.replace("+", "") + "?text=" + encoded;
  }

  function removeLegacyBanners(root) {
    if (!root) return;
    var legacyTop = root.querySelector(".bbb-notice-banner--top");
    if (legacyTop) legacyTop.parentNode.removeChild(legacyTop);
    var legacyFloating = document.querySelector(".bbb-whatsapp-floating");
    if (legacyFloating) legacyFloating.parentNode.removeChild(legacyFloating);
  }

  function insertBanner(container) {
    var banner = document.createElement("div");
    banner.className = "bbb-notice-banner";
    banner.textContent = disclaimerText;
    container.appendChild(banner);
    return banner;
  }

  function tagAndBannerSteps(root) {
    if (!root) return;
    root.querySelectorAll(".bbb-step").forEach(function (step) {
      var successMsg = step.querySelector(".bbb-success-message");
      var reviewContent = step.querySelector(".bbb-review-content");
      var leadFields = step.querySelectorAll(".bbb-lead-field:not(.bbb-lead-field--agreement)");

      if (successMsg) {
        step.querySelectorAll(".bbb-notice-banner").forEach(function (b) {
          if (b.parentNode !== successMsg) {
            b.parentNode.removeChild(b);
          }
        });
        if (!successMsg.querySelector(".bbb-notice-banner")) {
          insertBanner(successMsg);
        }
        return;
      }

      if (reviewContent) {
        if (!step.querySelector(".bbb-notice-banner")) {
          insertBanner(step);
        }
        return;
      }

      if (leadFields && leadFields.length > 0) {
        if (!step.querySelector(".bbb-notice-banner")) {
          insertBanner(step);
        }
      }
    });
  }

  function ensureFrameSizeHelp(root) {
    if (!root || !whatsappNumber) return;
    root.querySelectorAll(".bbb-dropdown").forEach(function (select) {
      if (select.dataset.bbbSizeHelpAdded) return;
      var heading = select.previousElementSibling;
      var headingText = heading && heading.textContent ? heading.textContent.trim().toLowerCase() : "";
      if (headingText.indexOf("frame size") === -1 && headingText.indexOf("size") === -1) return;

      select.dataset.bbbSizeHelpAdded = "true";
      var link = document.createElement("a");
      link.className = "bbb-whatsapp-inline";
      link.href = buildWhatsappLink(sizeMessage);
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      link.innerHTML = WHATSAPP_ICON_SVG + '<span>Not sure which size is right? Chat with us</span>';
      select.parentNode.insertBefore(link, select.nextSibling);
    });
  }

  function ensureAgreementCheckbox(root) {
    if (!root) return;
    root.querySelectorAll(".bbb-step").forEach(function (step) {
      if (step.dataset.bbbAgreementAdded) return;
      var textarea = step.querySelector("textarea");
      if (!textarea) return;

      step.dataset.bbbAgreementAdded = "true";

      var wrap = document.createElement("div");
      wrap.className = "bbb-lead-field bbb-lead-field--agreement";
      wrap.style.marginTop = "16px";
      wrap.innerHTML =
        '<label style="display:flex;align-items:flex-start;gap:10px;font-weight:normal;">' +
        '<input type="checkbox" class="bbb-agreement-checkbox" style="margin-top:4px;min-width:20px;min-height:20px;" />' +
        "<span>" + checkboxText + "</span>" +
        "</label>";

      textarea.parentNode.insertBefore(wrap, textarea.nextSibling);

      var checkbox = wrap.querySelector(".bbb-agreement-checkbox");
      var nav = step.parentNode ? step.parentNode.querySelector(".bbb-nav") : null;

      function syncButtonState() {
        var nextBtn = document.querySelector(".bbb-step-active .bbb-next-button") ||
          (nav ? nav.querySelector(".bbb-next-button") : null);
        if (!nextBtn) return;
        if (checkbox.checked) {
          nextBtn.removeAttribute("data-bbb-agreement-blocked");
        } else {
          nextBtn.setAttribute("data-bbb-agreement-blocked", "true");
        }
      }

      checkbox.addEventListener("change", syncButtonState);

      document.addEventListener(
        "click",
        function (e) {
          if (!step.classList.contains("bbb-step-active")) return;
          var target = e.target;
          if (target && target.classList && target.classList.contains("bbb-next-button")) {
            if (!checkbox.checked) {
              e.preventDefault();
              e.stopImmediatePropagation();
              checkbox.parentNode.style.color = "#ff6b6b";
            }
          }
        },
        true
      );
    });
  }

  function runEnhancements() {
    var root = getRoot();
    if (!root) return;
    removeLegacyBanners(root);
    tagAndBannerSteps(root);
    ensureFrameSizeHelp(root);
    ensureAgreementCheckbox(root);
  }

  document.addEventListener("DOMContentLoaded", function () {
    runEnhancements();
    var root = getRoot();
    if (root) {
      var observer = new MutationObserver(function () {
        runEnhancements();
      });
      observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ["class"] });
    }
  });
})();
