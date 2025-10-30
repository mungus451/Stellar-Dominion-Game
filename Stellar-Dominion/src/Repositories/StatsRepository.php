<?php

namespace StellarDominion\Repositories;

/**
 * Class StatsRepository
 *
 * Handles fetching data for leaderboards and player statistics using mysqli.
 */
class StatsRepository
{
    /**
     * @var \mysqli The database connection object.
     */
    protected $mysqli;

    /**
     * StatsRepository constructor.
     *
     * @param \mysqli $link A mysqli database connection instance (the global $link).
     */
    public function __construct(\mysqli $link)
    {
        $this->mysqli = $link;
    }

    /**
     * Fetches all leaderboard data.
     *
     * @return array An associative array where keys are leaderboard titles
     * and values are arrays containing data and metadata.
     */
    public function getAllLeaderboards(): array
    {
        $leaderboards = [];

        // Top 10 by Level
        $sql_level = "SELECT id, character_name, level, experience, race, class, avatar_path 
                      FROM users 
                      ORDER BY level DESC, experience DESC 
                      LIMIT 10";
        $result_level = $this->mysqli->query($sql_level);
        $data_level = [];
        if ($result_level) {
            $data_level = $result_level->fetch_all(MYSQLI_ASSOC);
            $result_level->free();
        }
        $leaderboards['Top 10 by Level'] = [
            'data' => $data_level,
            'field' => 'level',
            'format' => 'default'
        ];

        // Top 10 by Net Worth
        $sql_wealth = "SELECT id, character_name, net_worth, race, class, avatar_path 
                       FROM users 
                       ORDER BY net_worth DESC 
                       LIMIT 10";
        $result_wealth = $this->mysqli->query($sql_wealth);
        $data_wealth = [];
        if ($result_wealth) {
            $data_wealth = $result_wealth->fetch_all(MYSQLI_ASSOC);
            $result_wealth->free();
        }
        $leaderboards['Top 10 Richest Commanders'] = [
            'data' => $data_wealth,
            'field' => 'net_worth',
            'format' => 'number'
        ];

        // Top 10 by Population
        $sql_pop = "SELECT id, character_name, (workers + soldiers + guards + sentries + spies + untrained_citizens) AS population, race, class, avatar_path 
                    FROM users 
                    ORDER BY population DESC 
                    LIMIT 10";
        $result_pop = $this->mysqli->query($sql_pop);
        $data_pop = [];
        if ($result_pop) {
            $data_pop = $result_pop->fetch_all(MYSQLI_ASSOC);
            $result_pop->free();
        }
        $leaderboards['Top 10 by Population'] = [
            'data' => $data_pop,
            'field' => 'population',
            'format' => 'number'
        ];

        // Top 10 by Army Size
        $sql_army = "SELECT id, character_name, (soldiers + guards + sentries + spies) AS army_size, race, class, avatar_path 
                     FROM users 
                     ORDER BY army_size DESC 
                     LIMIT 10";
        $result_army = $this->mysqli->query($sql_army);
        $data_army = [];
        if ($result_army) {
            $data_army = $result_army->fetch_all(MYSQLI_ASSOC);
            $result_army->free();
        }
        $leaderboards['Top 10 by Army Size'] = [
            'data' => $data_army,
            'field' => 'army_size',
            'format' => 'number'
        ];

        // Top Plunderers (All-Time)
        $sql_plunder = "
            SELECT u.id, u.character_name, u.race, u.class, u.avatar_path,
                   COALESCE(SUM(b.credits_stolen), 0) AS total_plundered
            FROM battle_logs b
            JOIN users u ON u.id = b.attacker_id
            GROUP BY u.id
            HAVING total_plundered > 0
            ORDER BY total_plundered DESC
            LIMIT 10
        ";
        $result_plunder = $this->mysqli->query($sql_plunder);
        $data_plunder = [];
        if ($result_plunder) {
            $data_plunder = $result_plunder->fetch_all(MYSQLI_ASSOC);
            $result_plunder->free();
        }
        $leaderboards['Top Plunderers (All-Time)'] = [
            'data' => $data_plunder,
            'field' => 'total_plundered',
            'format' => 'number'
        ];

        // Highest Fatigue Casualties (All-Time)
        $sql_fatigue = "
            SELECT u.id, u.character_name, u.race, u.class, u.avatar_path,
                   COALESCE(SUM(b.attacker_soldiers_lost), 0) AS total_fatigue_lost
            FROM battle_logs b
            JOIN users u ON u.id = b.attacker_id
            GROUP BY u.id
            HAVING total_fatigue_lost > 0
            ORDER BY total_fatigue_lost DESC
            LIMIT 10
        ";
        $result_fatigue = $this->mysqli->query($sql_fatigue);
        $data_fatigue = [];
        if ($result_fatigue) {
            $data_fatigue = $result_fatigue->fetch_all(MYSQLI_ASSOC);
            $result_fatigue->free();
        }
        $leaderboards['Highest Casualties (All-Time)'] = [
            'data' => $data_fatigue,
            'field' => 'total_fatigue_lost',
            'format' => 'number'
        ];

        return $leaderboards;
    }
}