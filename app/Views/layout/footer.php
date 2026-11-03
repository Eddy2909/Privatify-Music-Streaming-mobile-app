<?php declare(strict_types=1); ?>
<dialog id="pwaInstallDialog" class="modal pwa-install-dialog">
    <form method="dialog" class="modal-card pwa-install-text">
        <h3>App installieren</h3>
        <p><strong>Android (Chrome):</strong> Menü ⋮ oben rechts → „App installieren" bzw. „Zum Startbildschirm hinzufügen".</p>
        <p><strong>iPhone (Safari):</strong> Teilen-Symbol → „Zum Home-Bildschirm". (Chrome unter iOS kann keine PWA installieren.)</p>
        <p><strong>Desktop (Chrome/Edge):</strong> Symbol in der Adressleiste → „Installieren".</p>
        <div class="modal-actions">
            <button class="btn primary" value="ok">Verstanden</button>
        </div>
    </form>
</dialog>
<script src="assets/js/app.js" defer></script>
<script src="assets/js/pwa.js" defer></script>
</body>
</html>
