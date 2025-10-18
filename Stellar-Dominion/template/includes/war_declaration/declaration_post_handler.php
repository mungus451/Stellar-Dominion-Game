<?php

//template/includes/declaration_post_handler.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!CSRFProtection::getInstance()->validateToken($token, 'war_declare')) {
            throw new Exception('Security check failed. Please try again.');
        }

        $war_name = trim((string)($_POST['war_name'] ?? ''));
        if ($war_name === '') { $war_name = 'Unnamed Conflict'; }

        // PvP paused: force alliance scope, reject any 'player'
        $posted_scope = strtolower(trim((string)($_POST['scope'] ?? 'alliance')));
        if ($posted_scope !== 'alliance') {
            throw new Exception('Player-vs-Player declarations are temporarily disabled.');
        }
        $scope = 'alliance';

        $war_type = strtolower(trim((string)($_POST['war_type'] ?? 'skirmish')));
        if (!in_array($war_type, ['skirmish','war'], true)) { throw new Exception('Invalid war type.'); }

        $cb_key   = strtolower(trim((string)($_POST['casus_belli'] ?? 'humiliation')));
        if (!in_array($cb_key, ['humiliation','dignity','custom'], true)) { $cb_key = 'humiliation'; }
        if ($cb_key === 'custom') { throw new Exception('Custom Casus Belli is currently unavailable.'); } // Server-side check
        $cb_custom = null;

        // Alliance perms only (leader/diplomat roles 1 or 2)
        if ($my_alliance_id <= 0) { throw new Exception('You must belong to an alliance to declare an alliance war.'); }
        if (!in_array($my_hierarchy, [1,2], true)) {
            throw new Exception('Only alliance leaders or diplomats can declare alliance wars.');
        }

        // Optional Custom Badge (when casus=custom) — disabled above, but keep code for future
        $customBadgeName = null;
        $customBadgeDesc = null;
        $customBadgePath = null;

        // Opponent selection — Alliance only
        $targetAllianceId = (int)($_POST['alliance_id'] ?? 0);
        if ($targetAllianceId <= 0) { throw new Exception('Please choose a target alliance.'); }
        if ($my_alliance_id > 0 && $targetAllianceId === $my_alliance_id) { throw new Exception('You cannot declare war on your own alliance.'); }

        // Early duplicate check (UX)
        if (sd_active_war_exists_alliance($link, $my_alliance_id, $targetAllianceId)) {
            throw new Exception('There is already an active alliance war between these alliances.');
        }
        // Validate target exists
        if (!sd_alliance_by_id($link, $targetAllianceId)) {
            throw new Exception('Target alliance not found.');
        }

        // Prepare CSRF for API fallback (single-use token in $_SESSION['csrf_token'])
        $_SESSION['csrf_token'] = $token;

        // --- Build API payload (Alliance-only)
        $payload = [
            'action'                   => 'declare_war',
            'csrf_token'               => $token,
            'initiator_user_id'        => $user_id,          // harmless if API derives from session
            'scope'                    => 'alliance',
            'war_type'                 => $war_type,
            'name'                     => $war_name,
            'casus_belli_key'          => $cb_key,
            'casus_belli_custom'       => $cb_custom,
            'custom_badge_name'        => $customBadgeName,
            'custom_badge_description' => $customBadgeDesc,
            'custom_badge_icon_path'   => $customBadgePath,
            'target_alliance_id'       => $targetAllianceId,
            'declared_against_alliance_id' => $targetAllianceId, // compatibility
        ];

        // POST to API with current session
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url    = $scheme . '://' . $host . '/api/war_declare.php';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Expect:',
            'Accept: application/json'
        ]);
        // Carry PHP session to API
        curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            throw new Exception('API request failed: ' . ($curlErr ?: 'unknown error'));
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            error_log('[war_declaration] Unexpected API response: ' . substr($resp, 0, 500));
            throw new Exception('Unexpected API response.');
        }
        if (empty($json['ok'])) {
            $apiErr = isset($json['error']) ? (string)$json['error'] : 'Declaration failed.';
            error_log('[war_declaration] API error: ' . $apiErr . ' | scope=alliance target=' . $targetAllianceId);
            throw new Exception($apiErr);
        }

        $endHuman = isset($json['end_date']) ? date('Y-m-d H:i', strtotime($json['end_date'] . ' UTC')) . ' UTC' : 'scheduled';
        $_SESSION['war_message'] = 'War declared successfully. Ends: ' . $endHuman . '.';
        header('Location: /realm_war.php'); exit;

    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

?>