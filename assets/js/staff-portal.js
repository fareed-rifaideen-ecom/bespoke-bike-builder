/* =========================================================
 * Bespoke Bike Builder — Staff Portal Frontend Script
 * Handles: logout button, inline status dropdown updates.
 * Talks to bbb_staff_logout / bbb_staff_update_status AJAX actions
 * registered by class-bbb-staff-portal.php.
 * ========================================================= */

(function () {
  "use strict";

  var settings = window.bbbStaffPortal || {};
  var ajaxUrl = settings.ajaxUrl || "/wp-admin/admin-ajax.php";
  var nonce = settings.nonce || "";

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

  document.addEventListener("DOMContentLoaded", function () {
    var logoutBtn = document.querySelector(".bbb-staff-logout-btn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function () {
        ajaxPost("bbb_staff_logout", {}, function () {
          window.location.reload();
        });
      });
    }

    document.querySelectorAll(".bbb-staff-status-select").forEach(function (select) {
      select.addEventListener("change", function () {
        var id = select.getAttribute("data-submission-id");
        var status = select.value;
        select.disabled = true;

        ajaxPost(
          "bbb_staff_update_status",
          { submission_id: id, status: status },
          function () {
            select.disabled = false;
            select.style.borderColor = "#2fa84f";
            window.setTimeout(function () {
              select.style.borderColor = "";
            }, 1200);
          },
          function (msg) {
            select.disabled = false;
            alert(msg || "Could not update status.");
          }
        );
      });
    });
  });
})();
