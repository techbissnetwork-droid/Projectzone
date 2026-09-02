<?php if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
?>
  </main>
  <footer class="botline">
    <span><?= e(Settings::get('site_name', 'TECHBISS')) ?> · <?= date('Y') ?></span>
    <span>Signed in as <?= e(Auth::user()['email'] ?? '') ?></span>
  </footer>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
