<?php
// template/pages/alliance_war_history.php
$active_page = 'alliance_war_history.php';
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: /index.php"); exit; }

// Fetch archived wars (latest first)
$sql_history = "SELECT * FROM war_history ORDER BY end_date DESC LIMIT 100";
$war_history_result = $link->query($sql_history);
$war_history = $war_history_result ? $war_history_result->fetch_all(MYSQLI_ASSOC) : [];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function metric_label($m){
    $m = strtolower((string)$m);
    if ($m === 'structure_damage') return 'Structure Damage';
    if ($m === 'units_killed')     return 'Units Killed';
    return 'Credits Plundered';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Starlight Dominion - Alliance War Archives</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="text-gray-400 antialiased">
    <div class="min-h-screen bg-cover bg-center bg-fixed" style="background-image: url('/assets/img/backgroundAlt.avif');">
        <div class="container mx-auto p-4 md:p-8">
            <?php include_once __DIR__ . '/../includes/navigation.php'; ?>

            <main class="content-box rounded-lg p-6 mt-4">
                <h1 class="font-title text-3xl text-white mb-4 border-b border-gray-700 pb-3">Alliance War Archives</h1>
                <p class="text-sm text-gray-400 mb-4">A historical record of major conflicts that have concluded in the galaxy.</p>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-800/80 text-gray-300">
                            <tr>
                                <th class="p-3">Conflict</th>
                                <th class="p-3">Casus Belli</th>
                                <th class="p-3">Outcome</th>
                                <th class="p-3">Goals</th>
                                <th class="p-3">Prestige</th>
                                <th class="p-3">Duration</th>
                                <th class="p-3 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/70">
                        <?php if (empty($war_history)): ?>
                            <tr><td colspan="7" class="p-4 text-gray-400">The archives are empty. No major wars have concluded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($war_history as $war):
                                $fs = json_decode($war['final_stats'] ?? '[]', true) ?: [];
                                $metric = $fs['metric'] ?? 'credits_plundered';
                                $th     = (int)($fs['threshold'] ?? 0);
                                $defTh  = (int)($fs['defender_threshold'] ?? 0);
                                $det    = $fs['details'] ?? [];

                                $goals = metric_label($metric) . ': ' . number_format($th);
                                if ($defTh) { $goals .= ' (Defender: ' . number_format($defTh) . ')'; }

                                $pre   = $det['prestige_awarded'] ?? null;
                                $prestige = $pre ? ('+' . (int)$pre['winner'] . ' / ' . (int)$pre['loser']) : '—';

                                $conflict = h($war['declarer_alliance_name']) . ' vs. ' . h($war['declared_against_alliance_name']);
                                $rowId = 'warrow-' . (int)$war['id'];

                                $ba = $det['biggest_attack'] ?? ['declarer'=>null,'declared_against'=>null];
                                $ta = $det['top_attacker']   ?? ['declarer'=>null,'declared_against'=>null];
                                $xp = $det['xp_gained']      ?? ['declarer'=>0,'declared_against'=>0];
                                $bp = $det['biggest_plunderer'] ?? null;

                                $fmtEvent = function($ev){
                                    if (!$ev) return '—';
                                    return sprintf("%s (%s) — %s",
                                        h($ev['name']),
                                        h($ev['source']),
                                        number_format((int)$ev['value'])
                                    );
                                };
                                $fmtTop = function($ev){ return $ev ? (h($ev['name']).' — '.number_format((int)$ev['value'])) : '—'; };
                            ?>
                            <tr class="hover:bg-gray-700/30 transition">
                                <td class="p-3 font-bold text-gray-200"><?= $conflict ?></td>
                                <td class="p-3"><?= h($war['casus_belli_text']) ?></td>
                                <td class="p-3 font-semibold">
                                    <span class="inline-block px-2 py-0.5 rounded bg-gray-800/70">
                                        <?= h($war['outcome']) ?>
                                    </span>
                                </td>
                                <td class="p-3"><?= h($goals) ?></td>
                                <td class="p-3"><?= h($prestige) ?></td>
                                <td class="p-3 text-xs text-gray-400">
                                    <?= h(date('Y-m-d', strtotime($war['start_date']))) ?> to <?= h(date('Y-m-d', strtotime($war['end_date']))) ?>
                                </td>
                                <td class="p-3 text-right">
                                    <button class="px-2 py-1 text-xs rounded bg-gray-700 hover:bg-gray-600 text-gray-200"
                                            onclick="toggleDetails('<?= $rowId ?>')">View</button>
                                </td>
                            </tr>
                            <tr id="<?= $rowId ?>" class="hidden">
                                <td colspan="7" class="p-0 bg-gray-900/60">
                                    <div class="grid md:grid-cols-2 gap-4 p-4">
                                        <div class="rounded-lg border border-gray-800 p-3">
                                            <div class="text-gray-300 font-semibold mb-2">Declarer Highlights</div>
                                            <div class="space-y-1 text-sm">
                                                <div><span class="text-gray-400">Biggest Attack:</span> <?= $fmtEvent($ba['declarer'] ?? null) ?></div>
                                                <div><span class="text-gray-400">Top Attacker:</span> <?= $fmtTop($ta['declarer'] ?? null) ?></div>
                                                <div><span class="text-gray-400">XP Gained:</span> <?= number_format((int)($xp['declarer'] ?? 0)) ?></div>
                                                <?php if ($metric === 'credits_plundered'): ?>
                                                    <div><span class="text-gray-400">Biggest Plunderer:</span>
                                                        <?= $fmtTop($bp['declarer'] ?? ($ta['declarer'] ?? null)) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="rounded-lg border border-gray-800 p-3">
                                            <div class="text-gray-300 font-semibold mb-2">Defender Highlights</div>
                                            <div class="space-y-1 text-sm">
                                                <div><span class="text-gray-400">Biggest Attack:</span> <?= $fmtEvent($ba['declared_against'] ?? null) ?></div>
                                                <div><span class="text-gray-400">Top Attacker:</span> <?= $fmtTop($ta['declared_against'] ?? null) ?></div>
                                                <div><span class="text-gray-400">XP Gained:</span> <?= number_format((int)($xp['declared_against'] ?? 0)) ?></div>
                                                <?php if ($metric === 'credits_plundered'): ?>
                                                    <div><span class="text-gray-400">Biggest Plunderer:</span>
                                                        <?= $fmtTop($bp['declared_against'] ?? ($ta['declared_against'] ?? null)) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script>
    function toggleDetails(id){
        var el = document.getElementById(id);
        if (!el) return;
        if (el.classList.contains('hidden')) el.classList.remove('hidden');
        else el.classList.add('hidden');
    }
    </script>
</body>
</html>
