/* =========================================================
 * Bespoke Bike Builder — Responsive Layer (Group 1, v2)
 * Corrected against real class names. Since assets/js/builder.js
 * is currently empty, all step-switching/tile-click logic lives
 * in an inline <script> block elsewhere (likely inside the PHP
 * shortcode template). This script does NOT assume anything
 * about how that inline logic works internally — it only:
 *   (a) observes .bbb-step / .bbb-step-active to know the current
 *       step and total steps (both are real, confirmed classes),
 *   (b) reads .bbb-tile-selected to know current selections,
 *   (c) for the Cockpit split, calls .click() on the real tile
 *       element so whatever inline handler exists still fires
 *       exactly as if the user clicked it directly.
 * ========================================================= */

(function () {
  "use strict";

  function getRoot() {
    return document.querySelector(".bbb-builder-placeholder");
  }

  function getSteps(root) {
    return root ? Array.prototype.slice.call(root.querySelectorAll(".bbb-step")) : [];
  }

  function getActiveStepIndex(steps) {
    for (var i = 0; i < steps.length; i++) {
      if (steps[i].classList.contains("bbb-step-active")) return i;
    }
    return -1;
  }

  function ensureProgressBar(root) {
    if (!root || root.querySelector(".bbb-progress-track")) return;
    var progressLabel = root.querySelector(".bbb-progress");
    var track = document.createElement("div");
    track.className = "bbb-progress-track";
    var fill = document.createElement("div");
    fill.className = "bbb-progress-fill";
    track.appendChild(fill);

    if (progressLabel && progressLabel.parentNode) {
      progressLabel.parentNode.insertBefore(track, progressLabel.nextSibling);
    } else {
      root.insertBefore(track, root.firstChild);
    }
  }

  function updateProgressBar(root) {
    if (!root) return;
    var fill = root.querySelector(".bbb-progress-fill");
    if (!fill) return;
    var steps = getSteps(root);
    var activeIndex = getActiveStepIndex(steps);
    if (activeIndex === -1 || steps.length === 0) return;
    var pct = Math.round(((activeIndex + 1) / steps.length) * 100);
    fill.style.width = pct + "%";
  }

  function collectSelections(root) {
    var rows = [];
    if (!root) return rows;

    root.querySelectorAll(".bbb-tile-group").forEach(function (group) {
      var selected = group.querySelector(".bbb-tile-selected");
      if (!selected) return;
      var labelEl = selected.querySelector(".bbb-tile-label");
      var label = labelEl ? labelEl.textContent.trim() : selected.textContent.trim();
      var heading = group.previousElementSibling;
      var groupName = heading && heading.textContent ? heading.textContent.trim() : "Selection";
      rows.push({ group: groupName, value: label });
    });

    root.querySelectorAll(".bbb-dropdown").forEach(function (select) {
      if (select.tagName === "SELECT" && select.value) {
        var opt = select.options[select.selectedIndex];
        var heading = select.previousElementSibling;
        var groupName = heading && heading.textContent ? heading.textContent.trim() : "Selection";
        rows.push({ group: groupName, value: opt ? opt.textContent.trim() : select.value });
      }
    });

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
      '<h3 style="margin-top:0;color:#ffffff;">Your build so far</h3>' +
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
      body.innerHTML = '<p style="color:#9aa5b1;">No selections yet.</p>';
      return;
    }
    body.innerHTML = rows
      .map(function (r) {
        return (
          '<div class="bbb-summary-sheet-row"><span class="label">' +
          r.group +
          '</span><span class="value">' +
          r.value +
          "</span></div>"
        );
      })
      .join("");
  }

  function enhanceCockpitStep(root) {
    if (!root) return;
    var groups = root.querySelectorAll(".bbb-tile-group");

    groups.forEach(function (group) {
      if (group.dataset.bbbCockpitEnhanced) return;

      var tiles = Array.prototype.slice.call(group.querySelectorAll(".bbb-tile"));
      var pattern = /^\s*(\d+)\s*\/\s*(\d+)\s*$/;
      var combos = [];

      tiles.forEach(function (tile) {
        var labelEl = tile.querySelector(".bbb-tile-label");
        var text = (labelEl ? labelEl.textContent : tile.textContent || "").trim();
        var match = text.match(pattern);
        if (match) {
          combos.push({ width: match[1], stem: match[2], el: tile });
        }
      });

      if (combos.length < 3) return;

      group.dataset.bbbCockpitEnhanced = "true";
      group.classList.add("bbb-cockpit-original-hidden");

      var widths = [];
      combos.forEach(function (c) {
        if (widths.indexOf(c.width) === -1) widths.push(c.width);
      });
      widths.sort(function (a, b) {
        return a - b;
      });

      var wrap = document.createElement("div");
      wrap.className = "bbb-cockpit-split";
      wrap.innerHTML =
        '<div><label for="bbb-cockpit-width">Handlebar Width</label>' +
        '<select id="bbb-cockpit-width" class="bbb-dropdown"><option value="">Select width</option>' +
        widths
          .map(function (w) {
            return '<option value="' + w + '">' + w + " mm</option>";
          })
          .join("") +
        "</select></div>" +
        '<div><label for="bbb-cockpit-stem">Stem Length</label>' +
        '<select id="bbb-cockpit-stem" class="bbb-dropdown" disabled><option value="">Select width first</option></select></div>';

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
        var stems = combos.filter(function (c) {
          return c.width === w;
        });
        stemSelect.disabled = false;
        stemSelect.innerHTML =
          '<option value="">Select stem length</option>' +
          stems
            .map(function (c) {
              return '<option value="' + c.stem + '">' + c.stem + " mm</option>";
            })
            .join("");
      });

      stemSelect.addEventListener("change", function () {
        var w = widthSelect.value;
        var s = stemSelect.value;
        if (!w || !s) return;
        var match = combos.find(function (c) {
          return c.width === w && c.stem === s;
        });
        if (match && match.el) {
          match.el.click();
        }
      });
    });
  }

  function runEnhancements() {
    var root = getRoot();
    if (!root) return;
    ensureProgressBar(root);
    updateProgressBar(root);
    ensureSummarySheet(root);
    enhanceCockpitStep(root);
  }

  document.addEventListener("DOMContentLoaded", function () {
    runEnhancements();
    var root = getRoot();
    if (root) {
      var observer = new MutationObserver(function () {
        runEnhancements();
      });
      observer.observe(root, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["class"],
      });
    }
  });
})();
