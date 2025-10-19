<!-- /template/includes/template/alliance_structures/main_card.php -->
<div class="lg:col-span-3 space-y-4">

  <div class="content-box rounded-lg p-6">
    <h2 class="font-title text-2xl text-cyan-400 mb-4">Alliance Structures</h2>

    <!-- 3 columns desktop, 1 column mobile -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <?php foreach ($cards as $card) { ?>
        <div class="bg-gray-800/60 rounded-lg p-4 border border-gray-700 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between">
              <h3 class="font-title text-white text-xl">Slot <?= (int)$card['slot']; ?></h3>
              <span class="text-xs px-2 py-1 rounded bg-gray-900 border border-gray-700">
                Level <?= (int)$card['level']; ?>/<?= (int)$card['tiers']; ?>
              </span>
            </div>

            <div class="mt-2 grid grid-cols-1 gap-3">
              <div class="bg-gray-900/60 rounded p-3 border border-gray-700">
                <p class="text-sm text-gray-400">Current:</p>
                <?php if ($card['current']) { ?>
                  <div class="flex items-center justify-between">
                    <span class="font-semibold text-white"><?= htmlspecialchars($card['current']['name']); ?></span>
                    <span class="text-xs text-gray-400">Tier <?= (int)$card['level']; ?>/<?= (int)$card['tiers']; ?></span>
                  </div>
                  <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($card['current']['description']); ?></p>
                  <p class="text-xs mt-1">Bonus:
                    <span class="text-green-300"><?= htmlspecialchars($card['current']['bonus_text']); ?></span>
                  </p>
                <?php } else { ?>
                  <p class="text-xs text-gray-400 italic">None owned yet.</p>
                <?php } ?>
              </div>

              <div class="flex flex-col">
                <p class="text-sm text-gray-400"><?= $card['maxed'] ? 'Status:' : 'Next Upgrade:'; ?></p>
                <div class="bg-gray-900/60 rounded p-3 border border-gray-700">
                  <?php if ($card['maxed']) { ?>
                    <div class="flex items-center justify-between">
                      <span class="font-semibold text-white">MAXED</span>
                      <span class="text-yellow-300 text-xs font-bold">Tier <?= (int)$card['tiers']; ?> Reached</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">This slot has reached its final tier.</p>
                  <?php } else { ?>
                    <div class="flex items-center justify-between">
                      <span class="font-semibold text-white"><?= htmlspecialchars($card['next']['name']); ?></span>
                      <span class="text-xs text-gray-400">Tier <?= (int)$card['level'] + 1; ?>/<?= (int)$card['tiers']; ?></span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($card['next']['description']); ?></p>
                    <p class="text-xs mt-1">
                      Bonus: <span class="text-green-300"><?= htmlspecialchars($card['next']['bonus_text']); ?></span><br>
                      Cost: <span class="text-yellow-300"><?= number_format((int)$card['next']['cost']); ?></span>
                    </p>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4">
            <?php if ($card['maxed']) { ?>
              <button class="w-full bg-gray-800/60 border border-gray-700 text-gray-400 py-2 rounded-lg cursor-not-allowed" disabled>Max Level</button>
            <?php } elseif (!$can_manage_structures) { ?>
              <p class="text-sm text-gray-400 text-center py-2 italic">Requires “Manage Structures” permission.</p>
            <?php } elseif (!$card['affordable']) { ?>
              <button class="w-full bg-gray-800/60 border border-gray-700 text-gray-400 py-2 rounded-lg cursor-not-allowed" disabled>
                Insufficient Credits
              </button>
            <?php } else { ?>
              <form action="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?')); ?>" method="POST" class="flex items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="action" value="buy_structure">
                <input type="hidden" name="slot" value="<?= (int)$card['slot']; ?>">
                <input type="hidden" name="structure_key" value="<?= htmlspecialchars($card['next_key']); ?>">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white py-2 rounded-lg border border-blue-500">
                  Purchase Upgrade
                </button>
              </form>
            <?php } ?>
          </div>
        </div>
      <?php } ?>
    </div>

    <p class="text-xs text-gray-500 mt-4">
      Tip: Each slot progresses linearly. Once you purchase a tier, the next one unlocks. You’ll only ever see the next available upgrade per slot.
    </p>
  </div>
</div>
<script> if (window.lucide) { lucide.createIcons(); } </script>