<!-- /template/includes/alliance_structures/inline_toast.php -->
<?php if (!empty($_SESSION['alliance_error']) || !empty($_SESSION['alliance_success'])): ?>
  <div class="fixed z-50 top-4 inset-x-0 flex justify-center px-4">
    <?php if (!empty($_SESSION['alliance_error'])): ?>
      <div class="sd-toast max-w-xl w-full bg-red-900/80 border border-red-600 text-red-100 px-4 py-3 rounded-lg shadow-lg flex items-start gap-3">
        <span class="mt-0.5 inline-flex"><i data-lucide="alert-triangle"></i></span>
        <div class="flex-1">
          <p class="font-semibold">Purchase failed</p>
          <p class="text-sm"><?= htmlspecialchars((string)$_SESSION['alliance_error']); ?></p>
        </div>
        <button type="button" class="ml-3 text-red-200 hover:text-white" onclick="this.closest('.sd-toast')?.remove()">×</button>
      </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['alliance_success'])): ?>
      <div class="sd-toast max-w-xl w-full bg-emerald-900/80 border border-emerald-600 text-emerald-100 px-4 py-3 rounded-lg shadow-lg flex items-start gap-3">
        <span class="mt-0.5 inline-flex"><i data-lucide="check-circle-2"></i></span>
        <div class="flex-1">
          <p class="font-semibold">Success</p>
          <p class="text-sm"><?= htmlspecialchars((string)$_SESSION['alliance_success']); ?></p>
        </div>
        <button type="button" class="ml-3 text-emerald-200 hover:text-white" onclick="this.closest('.sd-toast')?.remove()">×</button>
      </div>
    <?php endif; ?>
  </div>
  <?php
    // Clear flash so it doesn't persist on refresh
    unset($_SESSION['alliance_error'], $_SESSION['alliance_success']);
  ?>
  <script>
    setTimeout(() => document.querySelectorAll('.sd-toast').forEach(el => el.remove()), 7000);
  </script>
<?php endif; ?>