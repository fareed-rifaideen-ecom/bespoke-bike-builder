/* =========================================================
 * Bespoke Bike Builder — Draft & Resume Layer
 * Additive script. Talks to the new bbb_save_draft / bbb_get_draft /
 * bbb_email_draft_link AJAX actions (wp-admin/admin-ajax.php),
 * registered by class-bbb-draft-resume.php. Does not modify or
 * depend on the internals of builder.js.
 * ========================================================= */

(function () {
  "use strict";

  var settings = window.bbbDraftResume || {};
  var ajaxUrl = settings.ajaxUrl || "/wp-admin/admin-ajax.php";
  var nonce = settings.nonce || "";
  var currentToken = null;
  var saveTimer = null;

  function getRoot() {
    return document.querySelector(".bbb-builder-placeholder");
  }

  function getUrlParam(name) {
    var params = new URLSearchParams(window.location.search);
    return params.get(name);
  }

  function collectSelections(root) {
    var data = { tiles: [], dropdowns: [] };
    if (!root) return data;

    root.querySelectorAll(".bbb-tile-group").forEach(function (group, groupIndex) {
      var selected = group.querySelector(".bbb-tile-selected");
      if (!selected) return;
      var tiles = Array.prototype.slice.call(group.querySelectorAll(".bbb-tile"));
      var tileIndex = tiles.indexOf(selected);
      if (tileIndex > -1) {
        data.tiles.push({ groupIndex: groupIndex, tileIndex: tileIndex });
      }
    });

    root.querySelectorAll(".bbb-dropdown").forEach(function (select, selectIndex) {
      if (select.tagName === "SELECT" && select.value) {
        data.dropdowns.push({ selectIndex: selectIndex, value: select.value });
      }
    });

    return data;
  }

  function applySelections(root, data) {
    if (!root || !data) return;

    var groups = root.querySelectorAll(".bbb-tile-group");
    (data.tiles || []).forEach(function (entry) {
      var group = groups[entry.groupIndex];
      if (!group) return;
      var tiles = group.querySelectorAll(".bbb-tile");
      var tile = tiles[entry.tileIndex];
      if (tile) {
        tile.click();
      }
    });

    var selects = root.querySelectorAll(".bbb-dropdown");
    (data.dropdowns || []).forEach(function (entry) {
      var select = selects[entry.selectIndex];
      if (select && select.tagName === "SELECT") {
        select.value = entry.value;
        select.dispatchEvent(new Event("change", { bubbles: true }));
      }
    });
  }

  function ajaxPost(action, params, onSuccess, onError) {
    var body = new URLSearchParams(Object.assign({ action: action, nonce: nonce }, params));
    fetch(ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (json) {
        if (json && json.success) {
          onSuccess(json.data);
        } else if (onError) {
          onError(json && json.data ? json.data.message : "Request failed.");
        }
      })
      .catch(function () {
        if (onError) onError("Network error.");
      });
  }

  function saveDraft(root) {
    if (!root) return;
    var selections = collectSelections(root);
    if (!selections.tiles.length && !selections.dropdowns.length) return;

    ajaxPost(
      "bbb_save_draft",
      {
        token: currentToken || "",
        selections: JSON.stringify(selections),
      },
      function (data) {
        currentToken = data.token;
      }
    );
  }

  function scheduleAutoSave(root) {
    if (saveTimer) return;
    saveTimer = window.setInterval(function () {
      saveDraft(root);
    }, 4000);
  }

  function ensureSaveLink(root) {
    if (!root || document.querySelector(".bbb-save-draft-link")) return;
    var nav = root.querySelector(".bbb-nav");
    if (!nav) return;

    var link = document.createElement("a");
    link.className = "bbb-save-draft-link";
    link.href = "#";
    link.textContent = "Save & continue later";
    nav.parentNode.insertBefore(link, nav.nextSibling);

    link.addEventListener("click", function (e) {
      e.preventDefault();
      openSaveDialog(root);
    });
  }

  function openSaveDialog(root) {
    if (document.querySelector(".bbb-save-draft-overlay")) return;

    saveDraft(root);

    var overlay = document.createElement("div");
    overlay.className = "bbb-save-draft-overlay";
    overlay.innerHTML =
      '<div class="bbb-save-draft-box">' +
      "<h3>Save your build</h3>" +
      "<p>Enter your email and we'll send you a link to continue where you left off.</p>" +
      '<input type="email" class="bbb-save-draft-email" placeholder="you@example.com" />' +
      '<div class="bbb-save-draft-status"></div>' +
      '<div class="bbb-save-draft-actions">' +
      '<button type="button" class="bbb-back-button bbb-save-draft-cancel">Cancel</button>' +
      '<button type="button" class="bbb-next-button bbb-save-draft-send">Send Link</button>' +
      "</div></div>";

    document.body.appendChild(overlay);

    overlay.querySelector(".bbb-save-draft-cancel").addEventListener("click", function () {
      overlay.parentNode.removeChild(overlay);
    });

    overlay.querySelector(".bbb-save-draft-send").addEventListener("click", function () {
      var emailInput = overlay.querySelector(".bbb-save-draft-email");
      var status = overlay.querySelector(".bbb-save-draft-status");
      var email = emailInput.value.trim();

      if (!email || !currentToken) {
        status.textContent = "Please enter a valid email address.";
        status.style.color = "#ff6b6b";
        return;
      }

      status.textContent = "Sending...";
      status.style.color = "#9aa5b1";

      ajaxPost(
        "bbb_email_draft_link",
        { token: currentToken, email: email },
        function () {
          status.textContent = "Link sent! Check your inbox.";
          status.style.color = "#7fe0a0";
        },
        function (msg) {
          status.textContent = msg || "Something went wrong.";
          status.style.color = "#ff6b6b";
        }
      );
    });
  }

  function maybeResumeDraft(root) {
    var token = getUrlParam("bbb_resume");
    if (!token || !root) return;

    currentToken = token;

    ajaxPost(
      "bbb_get_draft",
      { token: token },
      function (data) {
        try {
          var selections = JSON.parse(data.selections);
          applySelections(root, selections);
        } catch (e) {
          /* If parsing fails, silently ignore — customer just starts fresh. */
        }
      },
      function () {
        /* Expired or missing token — no error shown, customer just starts fresh. */
      }
    );
  }

  function runInit() {
    var root = getRoot();
    if (!root) return;
    ensureSaveLink(root);
    scheduleAutoSave(root);
    maybeResumeDraft(root);
  }

  document.addEventListener("DOMContentLoaded", function () {
    runInit();
    var root = getRoot();
    if (root) {
      var observer = new MutationObserver(function () {
        ensureSaveLink(root);
      });
      observer.observe(root, { childList: true, subtree: true });
    }
  });
})();
