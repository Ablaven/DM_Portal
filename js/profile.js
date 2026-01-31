(function () {
  "use strict";

  const { fetchJson } = window.dmportal || {};

  function initProfilePage() {
    const form = document.getElementById("changePasswordForm");
    if (!form) return;

    const status = document.getElementById("changePasswordStatus");

    function setStatus(msg, ok) {
      if (!status) return;
      status.textContent = msg || "";
      const rootStyles = getComputedStyle(document.documentElement);
      status.style.color = ok ? rootStyles.getPropertyValue('--success') : rootStyles.getPropertyValue('--danger');
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      setStatus("Updating…", true);

      const current = String(document.getElementById("current_password")?.value || "");
      const next = String(document.getElementById("new_password")?.value || "");
      const confirm = String(document.getElementById("confirm_password")?.value || "");

      if (!current || !next || !confirm) {
        setStatus("All fields are required.", false);
        return;
      }
      if (next.length < 6) {
        setStatus("New password must be at least 6 characters.", false);
        return;
      }
      if (next !== confirm) {
        setStatus("New passwords do not match.", false);
        return;
      }

      try {
        const fd = new FormData();
        fd.append("current_password", current);
        fd.append("new_password", next);
        fd.append("confirm_password", confirm);

        const payload = await fetchJson("php/auth_change_password.php", { method: "POST", body: fd });
        if (!payload?.success) throw new Error(payload?.error || "Failed to update password.");

        form.reset();
        setStatus("Password updated.", true);
      } catch (err) {
        setStatus(err.message || "Failed to update password.", false);
      }
    });
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initProfilePage = initProfilePage;
})();
