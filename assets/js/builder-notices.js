/* =========================================================
 * Bespoke Bike Builder — Notices & WhatsApp Layer
 * Additive script. Reads settings localized from PHP via
 * bbbNotices (wp_localize_script) — disclaimer text, checkbox
 * text, WhatsApp number and messages all come from the new
 * "BBB Notices" admin settings page, not hardcoded here.
 *
 * v2: removed the persistent top-of-builder disclaimer banner.
 * The disclaimer now only appears directly above the Back/
 * Continue buttons on the steps where it's relevant (Review,
 * lead form, confirmation) — not duplicated at the top of
 * every step.
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
  var defaultMessage = settings.whatsappMessage || "Hi, I need help with my Dogma F build.";
  var sizeMessage = settings.whatsappSizeMessage || "Hi, I need help choosing the right frame size for my Dogma F build.";

  function getRoot() {
    return document.querySelector(".bbb-builder-placeholder");
  }

  function buildWhatsappLink(message) {
    if (!whatsappNumber) return null;
    var encoded = encodeURIComponent(message);
    return "https://wa.me/" + whatsappNumber.replace("+", "") + "?text=" + encoded;
  }

  function removeLegacyTopBanner(root) {
    if (!root) return;
    var legacy = root.querySelector(".bbb-notice-banner--top");
    if (legacy) legacy.parentNode.removeChild(legacy);
  }

  function ensureStepBanner(step, marker) {
    if (!step || step.querySelector(".bbb-notice-banner--" + marker)) return;
    var banner = document.createElement("div");
    banner.className = "bbb-notice-banner bbb-notice-banner--" + marker;
    banner.textContent = disclaimerText;
    step.appendChild(banner);
  }

  function tagAndBannerSteps(root) {
    if (!root) return;
    root.querySelectorAll(".bbb-step").forEach(function (step) {
      var reviewContent = step.querySelector(".bbb-review-content");
      var leadFields = step.querySelectorAll(".bbb-lead-field");
      var successMsg = step.querySelector(".bbb-success-message");

      if (reviewContent) {
        ensureStepBanner(step, "review");
      }
      if (leadFields && leadFields.length > 0 && !reviewContent) {
        ensureStepBanner(step, "lead");
      }
      if (successMsg && !successMsg.querySelector(".bbb-notice-banner")) {
        var confirmBanner = document.createElement("div");
        confirmBanner.className = "bbb-notice-banner";
        confirmBanner.textContent = disclaimerText;
        successMsg.appendChild(confirmBanner);
      }
    });
  }

  function ensureFloatingWhatsapp() {
    if (!whatsappNumber || document.querySelector(".bbb-whatsapp-floating")) return;
    var link = document.createElement("a");
    link.className = "bbb-whatsapp-floating";
    link.href = buildWhatsappLink(defaultMessage);
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.innerHTML = "&#128172; Chat with us";
    document.body.appendChild(link);
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
      link.textContent = "Not sure which size is right? Chat with us";
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
      wrap.className = "bbb-lead-field";
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
    removeLegacyTopBanner(root);
    tagAndBannerSteps(root);
    ensureFloatingWhatsapp();
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
