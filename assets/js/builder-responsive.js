/* =========================================================
 * Bespoke Bike Builder — Responsive Layer (Group 1)
 * Additive script. Loads AFTER builder.js.
 * Does not modify or override any existing plugin logic.
 * ========================================================= */

(function () {
  "use strict";

  function findWizardRoot() {
    return (
      document.querySelector(".bbb-builder-placeholder") ||
      document.querySelector('[class*="bbb-builder-placeholder"]') ||
      document.querySelector('[class*="bbb-builder"]') ||
      document.querySelector("#bbb-builder")
    );
  }

  function findStepLabel(root) {
    if (!root) return null;
    var candidates = root.querySelectorAll("*");
    for (var i = 0; i < candidates.length; i++) {
      var text = (candidates[i].textContent || "").trim();
      if (/^Step\s+\d+\s+of\s+\d+$/i.test(text)) {
        return candidates[i];
      }
    }
    return null;
  }

  function ensureProgressBar(root) {
    if (!root || root.querySelector(".bbb-progress-track")) return;
    var track = document.createElement("div");
    track.className = "bbb-progress-track";
    var fill = document.createElement("div");
    fill.className = "bbb-progress-fill";
    track.appendChild(fill);
    var stepLabel = findStepLabel(root);
    if (stepLabel && stepLabel.parentNode) {
      stepLabel.parentNode.insertBefore(track, stepLabel.nextSibling);
    } else {
      root.insertBefore(track, root.firstChild);
    }
  }

  function updateProgressBar(root) {
    if (!root) return;
    var fill = root.querySelector(".bbb-progress-fill");
    var stepLabel = findStepLabel(root);
    if (!fill || !stepLabel) return;
    var match = stepLabel.textContent.match(/Step\s+(\d+)\s+of\s+(\d+)/i);
    if (!match) return;
    var current = parseInt(match[1], 10);
    var total = parseInt(match[2], 10);
    if (!total) return;
    var pct = Math.min(100, Math.round((current / total) * 100));
    fill.style.width = pct + "%";
  }

  function tagNavButtons(root) {
    if (!root) return;
    var buttons = root.querySelectorAll("button, a");
    var backBtn = null;
    var continueBtn = null;
    buttons.forEach(function (btn) {
      var t = (btn.textContent || "").trim().toLowerCase();
      if (t === "back" || t === "previous") backBtn = btn;
      if (
        t === "continue" ||
        t === "next" ||
        t.indexOf("continue to") === 0 ||
        t === "submit my build"
      ) {
        continueBtn = btn;
      }
    });
    if (backBtn && continueBtn && backBtn.parentNode === continueBtn.parentNode) {
      var wrap = backBtn.parentNode;
      if (wrap && wrap.className.indexOf("bbb-nav-buttons") === -1) {
        wrap.classList.add("bbb-nav-buttons");
      }
    }
  }

  function collectSelections(root) {
    var rows = [];
    if (!root) return rows;
    var groups = root.querySelectorAll('[class*="bbb-tile-group"]');
    groups.forEach(function (group) {
      var selected = group.querySelector(
        '.bbb-tile.selected, .bbb-tile.active, [aria-pressed="true"], input:checked'
      );
      if (!selected) return;
      var label =
        selected.getAttribute("data-label") ||
        (selected.textContent || "").trim().split("\n")[0].trim();
      var heading = group.previousElementSibling;
      var groupName =
        heading && heading.textContent ? heading.textContent.trim() : "Selection";
      if (label) rows.push({ group: groupName, value: label });
    });

    var select = root.querySelector("select");
    if (select && select.value) {
      var opt = select.options[select.selectedIndex];
      rows.push({ group: "Frame Size", value: opt ? opt.textContent.trim() : select.value });
    }
    return rows;
  }

  function ensureSummarySheet(root) {
    if (!root || document.querySelector(".bbb-summary-sheet")) return;

    var tab = document.createElement("div");
    tab.className = "bbb-summary-tab";
    tab.innerHTML =
      '<span>View your build so far</span><span class="bbb-summary-tab-arrow">&#9650;</span>';

    var sheet = document.createElement("div");
    sheet.className = "bbb-summary-sheet";
    sheet.innerHTML =
      '<button class="bbb-summary-sheet-close" aria-label="Close">&times;</button>' +
      '<h4 style="margin-top:0;">Your build so far</h4>' +
      '<div class="bbb-summary-sheet-body"></div>';

    document.body.appendChild(tab);
    document.body.appendChild(sheet);

    function toggle() {
      tab.classList.toggle("open");
      sheet.classList.toggle("open");
      if (sheet.classList.contains("open")) {
        renderSummary(root, sheet);
      }
    }

    tab.addEventListener("click", toggle);
    sheet.querySelector(".bbb-summary-sheet-close").addEventListener("click", toggle);
  }

  function renderSummary(root, sheet) {
    var body = sheet.querySelector(".bbb-summary-sheet-body");
    if (!body) return;
    var rows = collectSelections(root);
    if (!rows.length) {
      body.innerHTML = '<p style="color:#999;">No selections yet.</p>';
      return;
    }
    body.innerHTML = rows
      .map(function (r) {
        return (
          '<div class="bbb-summary-sheet-row"><span class="label">' +
          r.group +
          "</span><span>" +
          r.value +
          "</span></div>"
        );
      })
      .join("");
  }

  function enhanceCockpitStep(root) {
    if (!root) return;
    var group = root.querySelector('[class*="bbb-tile-group"]');
    if (!group || group.dataset.bbbCockpitEnhanced) return;

    var tiles = Array.prototype.slice.call(group.children);
    var pattern = /^\s*(\d+)\s*\/\s*(\d+)\s*$/;
    var combos = [];

    tiles.forEach(function (tile) {
      var text = (tile.textContent || "").trim();
      var match = text.match(pattern);
      if (match) {
        combos.push({
          width: match[1],
          stem: match[2],
          el: tile,
        });
      }
    });

    if (combos.length < 3) return;

    group.dataset.bbbCockpitEnhanced = "true";
    group.classList.add("bbb-cockpit-original-hidden");

    var widths = [];
    combos.forEach(function (c) {
      if (widths.indexOf(c.width) === -1) widths.push(c.width);
    });
    widths.sort(function (a, b) { return a - b; });

    var wrap = document.createElement("div");
    wrap.className = "bbb-cockpit-split";
    wrap.innerHTML =
      '<div><label for="bbb-cockpit-width">Handlebar Width</label>' +
      '<select id="bbb-cockpit-width"><option value="">Select width</option>' +
      widths.map(function (w) { return '<option value="' + w + '">' + w + " mm</option>"; }).join("") +
      "</select></div>" +
      '<div><label for="bbb-cockpit-stem">Stem Length</label>' +
      '<select id="bbb-cockpit-stem" disabled><option value="">Select width first</option></select></div>';

    group.parentNode.insertBefore(wrap, group);

    var widthSelect = wrap.querySelector("#bbb-cockpit-width");
    var stemSelect = wrap.querySelector("#bbb-cockpit-stem");

    widthSelect.addEventListener("change", function () {
      var w = widthSelect.value;
      stemSelect.innerHTML = "";
      if (!w) {
        stemSelect.disabled = true;
        stemSelect.innerHTML = '<option value="">Select width first</option>';
        return;
      }
      var stems = combos.filter(function (c) { return c.width === w; });
      stemSelect.disabled = false;
      stemSelect.innerHTML =
        '<option value="">Select stem length</option>' +
        stems
          .map(function (c) { return '<option value="' + c.stem + '">' + c.stem + " mm</option>"; })
          .join("");
    });

    stemSelect.addEventListener("change", function () {
      var w = widthSelect.value;
      var s = stemSelect.value;
      if (!w || !s) return;
      var match = combos.find(function (c) { return c.width === w && c.stem === s; });
      if (match && match.el) {
        match.el.click();
      }
    });
  }

  function runEnhancements() {
    var root = findWizardRoot();
    if (!root) return;
    ensureProgressBar(root);
    updateProgressBar(root);
    tagNavButtons(root);
    ensureSummarySheet(root);
    enhanceCockpitStep(root);
  }

  document.addEventListener("DOMContentLoaded", function () {
    runEnhancements();
    var root = findWizardRoot();
    if (root) {
      var observer = new MutationObserver(function () {
        runEnhancements();
      });
      observer.observe(root, { childList: true, subtree: true });
    }
  });
})();
