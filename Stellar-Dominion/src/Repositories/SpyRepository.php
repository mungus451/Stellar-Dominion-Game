<?php

class SpyRepository
{
    private $link;

    public function __construct(mysqli $link)
    {
        $this->link = $link;
    }

    /**
     * Fetches all data needed to render the spy page view.
     * @param int $user_id
     * @return array
     */
    public function getSpyPageData(int $user_id): array
    {
        // 1. Get current user state
        $me = ss_get_user_state(
            $this->link,
            $user_id,
            ['id', 'character_name', 'level', 'credits', 'attack_turns', 'spies', 'sentries', 'last_updated', 'experience', 'alliance_id']
        );
        $my_alliance_id = $me['alliance_id'] ?? null;

        // 2. Get targets (read-only helper adds army_size; excludes self)
        $targets = function_exists('ss_get_targets') ? ss_get_targets($this->link, $user_id, 100) : [];

        // 3. Hydrate targets with rivalry data (from original spy.php)
        foreach ($targets as &$row) {
            $row['is_rival'] = false;
            if ($my_alliance_id && !empty($row['alliance_id']) && $my_alliance_id != $row['alliance_id']) {
                $a1 = (int)$my_alliance_id; $a2 = (int)$row['alliance_id'];
                $sql_rival = "SELECT 1 FROM rivalries
                              WHERE (alliance1_id = ? AND alliance2_id = ?)
                                 OR (alliance1_id = ? AND alliance2_id = ?)
                              LIMIT 1";
                if ($stmt_rival = mysqli_prepare($this->link, $sql_rival)) {
                    mysqli_stmt_bind_param($stmt_rival, "iiii", $a1, $a2, $a2, $a1);
                    mysqli_stmt_execute($stmt_rival);
                    mysqli_stmt_store_result($stmt_rival);
                    if (mysqli_stmt_num_rows($stmt_rival) > 0) { $row['is_rival'] = true; }
                    mysqli_stmt_close($stmt_rival);
                }
            }
        }
        unset($row);
        
        // 4. Return all data as an array
        return [
            'me' => $me,
            'my_alliance_id' => $my_alliance_id,
            'targets' => $targets,
            // Removed TS cost variables
        ];
    }
}