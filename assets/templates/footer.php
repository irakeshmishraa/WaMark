<?php
/**
 * WaMark - Footer Template
 */
if (!defined('WAMARK_VERSION')) exit;
$hideBranding = get_setting('hide_branding', '0') === '1';
?>
        </div><!-- /.app-content -->

        <!-- Footer -->
        <footer class="app-footer">
            <div class="d-flex justify-content-between align-items-center">
                <span>&copy; <?= date('Y') ?> <?= sanitize(get_setting('site_name', APP_NAME)) ?></span>
                <?php if (!$hideBranding): ?>
                <span class="text-muted small">v<?= WAMARK_VERSION ?></span>
                <?php endif; ?>
            </div>
        </footer>
    </main>
</div>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSETS_URL ?>/js/app.js"></script>
<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
