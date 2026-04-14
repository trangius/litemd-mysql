<?php
// MySQL plugin settings panel (rendered inside the Advanced page)
$mysqlConfig = \LiteMD\Config::getPluginConfig('mysql', []);
?>
            <div class="advanced-form" style="max-width:400px;padding:1.25rem">
                <h2 class="advanced-section-title" style="margin-top:0">MySQL Settings</h2>
                <p class="advanced-section-desc">Database connection credentials. Changes take effect on next page load.</p>

                <label class="advanced-field">
                    <span class="advanced-label">Database Name</span>
                    <input type="text" id="mysql-db-name" class="advanced-input" value="<?= htmlspecialchars($mysqlConfig['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">Host</span>
                    <input type="text" id="mysql-db-host" class="advanced-input" value="<?= htmlspecialchars($mysqlConfig['host'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">User</span>
                    <input type="text" id="mysql-db-user" class="advanced-input" value="<?= htmlspecialchars($mysqlConfig['user'] ?? 'root', ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <label class="advanced-field">
                    <span class="advanced-label">Password</span>
                    <input type="password" id="mysql-db-pass" class="advanced-input" value="<?= htmlspecialchars($mysqlConfig['pass'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>

                <div class="advanced-actions">
                    <button class="advanced-btn advanced-btn-primary" id="mysql-settings-save">Save</button>
                </div>
            </div>

<script>
(function () {
    var saveBtn = document.getElementById("mysql-settings-save");
    if (!saveBtn) return;

    saveBtn.addEventListener("click", function () {
        EditorUtils.apiPost("mysql-settings-save", {
            host: document.getElementById("mysql-db-host").value,
            name: document.getElementById("mysql-db-name").value,
            user: document.getElementById("mysql-db-user").value,
            pass: document.getElementById("mysql-db-pass").value,
            csrf: (window.EDITOR_CONFIG || {}).csrfToken || ""
        }).then(function () {
            alert("MySQL settings saved.");
        }).catch(function (err) {
            alert(err.message || "Failed to save.");
        });
    });
})();
</script>
