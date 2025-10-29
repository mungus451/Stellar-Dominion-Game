<?php
/**
 * Starlight Dominion - A.I. Advisor & Stats Module (DROP-IN)
 *
 * Expects (when available) from parent page:
 * - $active_page (string)
 * - $user_stats (array) OR $me (attack page) providing at least credits/level/xp/attack_turns/last_updated
 * - $user_xp (int)
 * - $user_level (int)
 * - $minutes_until_next_turn (int)
 * - $seconds_remainder (int)
 * - $now (DateTime, UTC)
 */

// Determine the stats source gracefully (dashboard uses $user_stats; attack.php often has $me)
$__stats = [];
if (isset($user_stats) && is_array($user_stats)) {
    $__stats = $user_stats;
} elseif (isset($me) && is_array($me)) {
    $__stats = $me;
}

// Advice copy (includes pages from your snippet)
$advice_repository = [
    'dashboard.php' => [
        "Your central command hub. Monitor your resources and fleet status from here.",
        "A strong economy is the backbone of any successful empire.",
        "Keep an eye on your Dominion Time; it's synchronized across the galaxy."
    ],
    'attack.php' => [
        "Choose your targets wisely. Attacking stronger opponents yields greater rewards, but carries higher risk.",
        "The more turns you use in an attack, the more credits you can plunder on a victory.",
        "Check a target's level. A higher level may indicate a more formidable opponent."
    ],
    'spy.php' => [
        "Intelligence is key. Use spy missions to gain an advantage over your opponents.",
        "A successful assassination can cripple an opponent's economy or military.",
        "Sabotage missions can weaken an empire's foundations, making them vulnerable to attack."
    ],
    'battle.php' => [
        "Train your untrained citizens into specialized units to expand your dominion.",
        "Workers increase your income, while Soldiers and Guards form your military might.",
        "Don't forget to balance your army. A strong offense is nothing without a solid defense."
    ],
    'levels.php' => [
        "Spend proficiency points to permanently enhance your dominion's capabilities.",
        "Strength increases your fleet's Offense Power in battle.",
        "Constitution boosts your Defense Rating, making you a harder target."
    ],
    'war_history.php' => [
        "Review your past engagements to learn from victories and defeats.",
        "Analyze your defense logs to identify your most frequent attackers.",
        "A victory is sweet, but a lesson learned from defeat is invaluable."
    ],
    'structures.php' => [
        "This is where you can spend points to upgrade your core units.",
        "Upgrading soldiers will make your attacks more potent.",
        "Investing in guards will bolster your empire's defenses."
    ],
    'profile.php' => [
        "Express yourself. Your avatar and biography are visible to other commanders.",
        "A picture is worth a thousand words, or in this galaxy, a thousand credits.",
        "Remember to save your changes after updating your profile."
    ],
    'settings.php' => [
        "Secure your account by regularly changing your password.",
        "Vacation mode protects your empire from attacks while you are away. Use it wisely.",
        "Account settings are critical. Double-check your entries before saving."
    ],
    'bank.php' => [
        "Store your credits in the bank to keep them safe from plunder. Banked credits cannot be stolen.",
        "You have a limited number of deposits each day. Plan your finances carefully.",
        "Remember to withdraw credits before you can spend them on units or structures."
    ],
    'community.php' => [
        "Join our Discord to stay up-to-date with the latest game news and announcements.",
        "Community is key. Share your strategies and learn from fellow commanders.",
        "Your feedback during this development phase is invaluable to us."
    ],
    'inspiration.php' => [
        "Greatness is built upon the foundations laid by others. It's always good to acknowledge our roots.",
        "Exploring open-source projects is a great way to learn and contribute to the community.",
        "Every great game has a story. This one is no different."
    ],
];

$current_advice_list = isset($advice_repository[$active_page]) ? $advice_repository[$active_page] : ["Welcome to Starlight Dominion."];
$advice_json = htmlspecialchars(json_encode(array_values($current_advice_list)), ENT_QUOTES, 'UTF-8');

// XP bar
$xp_for_next_level = 0;
$xp_progress_pct   = 0;
if (isset($user_xp, $user_level)) {
    $lvl = (int)$user_level;
    $xp_for_next_level = floor(1000 * pow($lvl, 1.5));
    $xp_progress_pct   = $xp_for_next_level > 0 ? min(100, floor(((int)$user_xp / $xp_for_next_level) * 100)) : 100;
}

// Turn timer hydrate:
// Prefer explicit minutes/seconds. If absent, derive from last_updated (server says turn math is correct).
$TURN_INTERVAL = 600; // 10 minutes
$seconds_until_next_turn = null;

if (isset($minutes_until_next_turn, $seconds_remainder) && is_numeric($minutes_until_next_turn) && is_numeric($seconds_remainder)) {
    $seconds_until_next_turn = ((int)$minutes_until_next_turn * 60) + (int)$seconds_remainder;
} else {
    try {
        if (!empty($__stats['last_updated'])) {
            $last = new DateTime($__stats['last_updated'], new DateTimeZone('UTC'));
            $cur  = (isset($now) && $now instanceof DateTime) ? $now : new DateTime('now', new DateTimeZone('UTC'));
            $elapsed = max(0, $cur->getTimestamp() - $last->getTimestamp());
            $seconds_until_next_turn = $TURN_INTERVAL - ($elapsed % $TURN_INTERVAL);
        }
    } catch (Throwable $e) { /* leave null */ }
}
if ($seconds_until_next_turn !== null) {
    $seconds_until_next_turn = max(0, min($TURN_INTERVAL, (int)$seconds_until_next_turn));
    $minutes_until_next_turn = intdiv($seconds_until_next_turn, 60);
    $seconds_remainder       = $seconds_until_next_turn % 60;
}

// Dominion Time (server-anchored, display only). If $now provided, honor it; otherwise use ET "now".
try {
    if (isset($now) && $now instanceof DateTime) {
        $nowEt = clone $now;
        $nowEt->setTimezone(new DateTimeZone('America/New_York'));
        $__now_et = $nowEt;
    } else {
        $__now_et = new DateTime('now', new DateTimeZone('America/New_York'));
    }
} catch (Throwable $e) {
    $__now_et = new DateTime('now', new DateTimeZone('UTC'));
}
$__now_et_epoch = $__now_et->getTimestamp();
?>
<!-- Advisor -->
<div class="content-box rounded-lg p-4 advisor-container">
    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-2">A.I. Advisor</h3>
    <button id="toggle-advisor-btn" class="mobile-only-button">-</button>

    <div id="advisor-content">
        <p id="advisor-text" class="text-sm transition-opacity duration-500" data-advice='<?php echo $advice_json; ?>'>
            <?php echo $current_advice_list[0]; ?>
        </p>

        <?php if (isset($user_xp, $user_level)): ?>
        <div class="mt-3 pt-3 border-t border-gray-600">
            <div class="flex justify-between text-xs mb-1">
                <span id="advisor-level-display" class="text-white font-semibold">Level <?php echo (int)$user_level; ?> Progress</span>
                <span id="advisor-xp-display" class="text-gray-400"><?php echo number_format((int)$user_xp) . ' / ' . number_format((int)$xp_for_next_level); ?> XP</span>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-2.5" title="<?php echo (int)$xp_progress_pct; ?>%">
                <div id="advisor-xp-bar" class="bg-cyan-500 h-2.5 rounded-full" style="width: <?php echo (int)$xp_progress_pct; ?>%"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="content-box rounded-lg p-4 stats-container">
    <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">Stats</h3>
    <button id="toggle-stats-btn" class="mobile-only-button">-</button>

    <div id="stats-content">
        <ul class="space-y-2 text-sm">
            <?php if(isset($__stats['credits'])): ?>
                <li class="flex justify-between">
                    <span>Credits:</span>
                    <span id="advisor-credits-display" class="text-white font-semibold" data-amount="<?php echo (int)$__stats['credits']; ?>">
                        <?php echo number_format((int)$__stats['credits']); ?>
                    </span>
                </li>
            <?php endif; ?>

            <?php if(isset($__stats['banked_credits'])): ?>
                <li class="flex justify-between">
                    <span>Banked Credits:</span>
                    <span class="text-white font-semibold"><?php echo number_format((int)$__stats['banked_credits']); ?></span>
                </li>
            <?php endif; ?>

            <?php $untrained_ready = isset($__stats['untrained_citizens']) ? (int)$__stats['untrained_citizens'] : null; ?>
            <?php if($untrained_ready !== null): ?>
                <li class="flex justify-between">
                    <span>Untrained Citizens (ready):</span>
                    <span id="advisor-untrained-display" class="text-white font-semibold"><?php echo number_format($untrained_ready); ?></span>
                </li>
            <?php endif; ?>

            <?php if(isset($__stats['level'])): ?>
                <li class="flex justify-between">
                    <span>Level:</span>
                    <span id="advisor-level-value" class="text-white font-semibold"><?php echo (int)$__stats['level']; ?></span>
                </li>
            <?php endif; ?>

            <?php if(isset($__stats['attack_turns'])): ?>
                <li class="flex justify-between">
                    <span>Attack Turns:</span>
                    <span id="advisor-attack-turns" class="text-white font-semibold"><?php echo (int)$__stats['attack_turns']; ?></span>
                </li>
            <?php endif; ?>

            <li class="flex justify-between border-t border-gray-600 pt-2 mt-2">
                <span>Next Turn In:</span>
                <span
                    id="next-turn-timer"
                    class="text-cyan-300 font-bold"
                    <?php if ($seconds_until_next_turn !== null): ?>
                        data-seconds-until-next-turn="<?php echo (int)$seconds_until_next_turn; ?>"
                    <?php endif; ?>
                    data-turn-interval="600"
                >
                    <?php
                    if ($seconds_until_next_turn !== null) {
                        echo sprintf('%02d:%02d', (int)$minutes_until_next_turn, (int)$seconds_remainder);
                    } else {
                        echo '—';
                    }
                    ?>
                </span>
            </li>

            <li class="flex justify-between">
                <span>Dominion Time (ET):</span>
                <span id="dominion-time"
                      class="text-white font-semibold"
                      data-epoch="<?php echo (int)$__now_et_epoch; ?>"
                      data-tz="America/New_York">
                    <?php echo htmlspecialchars($__now_et->format('H:i:s')); ?>
                </span>
            </li>
        </ul>
    </div>
</div>

<script>
(function(){
    // ELEMENTS
    const elCredits   = document.getElementById('advisor-credits-display');
    const elUntrained = document.getElementById('advisor-untrained-display');
    const elTurns     = document.getElementById('advisor-attack-turns');
    const elNextTurn  = document.getElementById('next-turn-timer');
    const elDomTime   = document.getElementById('dominion-time');

    // -------------------------------
    // DOMINION CLOCK (display only; optional re-anchor via API)
    // -------------------------------
    let domEpoch = elDomTime ? parseInt(elDomTime.getAttribute('data-epoch') || '0', 10) : 0;
    const tz = elDomTime ? (elDomTime.getAttribute('data-tz') || 'America/New_York') : 'America/New_York';

    function renderDomTime(epoch){
        if (!elDomTime) return;
        const d = new Date(epoch * 1000);
        const formatted = new Intl.DateTimeFormat('en-GB', {
            timeZone: tz,
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        }).format(d);
        elDomTime.textContent = formatted;
    }

    if (domEpoch > 0) {
        setInterval(function(){
            domEpoch += 1;
            renderDomTime(domEpoch);
        }, 1000);
        renderDomTime(domEpoch);
    }

    // ---------------------------------------------------
    // COUNTDOWN (incorporated & upgraded): auto-cycling, monotonic, AJAX-agnostic
    // ---------------------------------------------------
    // hydrate once from server; then:
    // remaining = (initialSeconds - elapsed) mod TURN_INTERVAL
    if (!window.__advisorCountdownInit) {
        window.__advisorCountdownInit = true;

        if (elNextTurn) {
            const attrSecs = elNextTurn.getAttribute('data-seconds-until-next-turn');
            const initialSeconds = attrSecs ? parseInt(attrSecs, 10) : NaN;

            const attrInterval = elNextTurn.getAttribute('data-turn-interval');
            const TURN_INTERVAL = (attrInterval && !isNaN(parseInt(attrInterval, 10)))
                ? Math.max(1, parseInt(attrInterval, 10))
                : 600;

            if (Number.isFinite(initialSeconds) && initialSeconds >= 0) {
                const havePerf = (typeof performance !== 'undefined' && typeof performance.now === 'function');
                const startMono = havePerf ? performance.now() : Date.now();

                const fmt = (secs) => {
                    secs = Math.max(0, Math.floor(secs));
                    const m = Math.floor(secs / 60);
                    const s = secs % 60;
                    return (m < 10 ? '0' + m : '' + m) + ':' + (s < 10 ? '0' + s : '' + s);
                };

                let lastWhole = -1;

                function tick(){
                    const nowMono = havePerf ? performance.now() : Date.now();
                    const elapsed = (nowMono - startMono) / 1000;

                    // continuous wrap into [0, TURN_INTERVAL)
                    let remaining = initialSeconds - elapsed;
                    remaining = ((remaining % TURN_INTERVAL) + TURN_INTERVAL) % TURN_INTERVAL;

                    const whole = Math.floor(remaining);

                    if (whole !== lastWhole) {
                        lastWhole = whole;

                        // Optional visual cue at 00:00
                        if (whole === 0) {
                            elNextTurn.classList.add('turn-ready');
                            setTimeout(() => elNextTurn.classList.remove('turn-ready'), 900);
                        }
                        elNextTurn.textContent = fmt(whole);
                    }
                    requestAnimationFrame(tick);
                }
                requestAnimationFrame(tick);
            } else {
                elNextTurn.textContent = '—';
            }
        }
    }

    // -----------------------------------------------
    // 10s POLL: STATS ONLY (does not touch countdown)
    // -----------------------------------------------
    /* async function pollAdvisor(){
        try {
            const res = await fetch('/api/advisor_poll.php', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            if (!res.ok) return;
            const data = await res.json();

            if (elCredits && typeof data.credits === 'number') {
                elCredits.textContent = new Intl.NumberFormat().format(data.credits);
                elCredits.setAttribute('data-amount', String(data.credits));
            }
            if (elUntrained && typeof data.untrained_citizens === 'number') {
                elUntrained.textContent = new Intl.NumberFormat().format(data.untrained_citizens);
            }
            if (elTurns && typeof data.attack_turns === 'number') {
                elTurns.textContent = String(data.attack_turns);
            }
            // Allow clock re-anchor if backend includes it
            if (elDomTime && typeof data.server_time_unix === 'number') {
                domEpoch = parseInt(data.server_time_unix, 10);
                renderDomTime(domEpoch);
            }
        } catch (_) { /* silent */ }
    }

    /*pollAdvisor(); */
    /*setInterval(pollAdvisor, 10000); */
})();
</script>
