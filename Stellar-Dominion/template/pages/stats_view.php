<?php
/**
 * template/pages/stats_view.php
 *
 * This is the "View" FRAGMENT for LOGGED-IN users.
 * This file is included *inside* the 4-column grid opened by header.php.
 * It provides the <aside> (col 1) and the <div> (cols 2-4).
 *
 * @var array|null $user_stats (Provided by Controller)
 * @var array $lb_left (Provided by Controller)
 * @var array $lb_right (Provided by Controller)
 */
?>

<aside class="lg:col-span-1 space-y-6">
    <?php
        // $user_stats is provided by the controller
        include_once __DIR__ . '/../includes/advisor.php';
    ?>
</aside>

<div class="lg:col-span-3">
    <div class="main-bg border border-gray-700 rounded-lg shadow-2xl p-4">
        <h2 class="font-title text-2xl text-cyan-400 text-center mb-6">Leaderboards</h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="space-y-6">
                <?php foreach ($lb_left as $title => $details): ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">
                            <?php echo htmlspecialchars($title); ?>
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="p-2">Rank</th>
                                        <th class="p-2">Commander</th>
                                        <th class="p-2 text-right"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $details['field']))); ?></th>
                                        <th class="p-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rank = 1;
                                    foreach ($details['data'] as $row):
                                        $avatar = !empty($row['avatar_path']) ? $row['avatar_path'] : '/assets/img/default_avatar.webp';
                                    ?>
                                    <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                        <td class="p-2 font-bold text-cyan-400"><?php echo $rank++; ?></td>
                                        <td class="p-2">
                                            <div class="flex items-center">
                                                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md mr-3 object-cover">
                                                <div>
                                                    <p class="font-bold text-white"><?php echo htmlspecialchars($row['character_name']); ?></p>
                                                    <?php if (isset($row['race'], $row['class'])): ?>
                                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($row['race'] . ' ' . $row['class'])); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-2 text-right font-semibold text-white">
                                            <?php
                                                $val = isset($row[$details['field']]) ? $row[$details['field']] : 0;
                                                echo ($details['format'] === 'number') ? number_format((int)$val) : htmlspecialchars((string)$val);
                                            ?>
                                        </td>
                                        <td class="p-2 text-right">
                                            <a href="/view_profile.php?id=<?php echo (int)$row['id']; ?>" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="space-y-6">
                <?php foreach ($lb_right as $title => $details): ?>
                    <div class="content-box rounded-lg p-4">
                        <h3 class="font-title text-cyan-400 border-b border-gray-600 pb-2 mb-3">
                            <?php echo htmlspecialchars($title); ?>
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="p-2">Rank</th>
                                        <th class="p-2">Commander</th>
                                        <th class="p-2 text-right"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $details['field']))); ?></th>
                                        <th class="p-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rank = 1;
                                    foreach ($details['data'] as $row):
                                        $avatar = !empty($row['avatar_path']) ? $row['avatar_path'] : '/assets/img/default_avatar.webp';
                                    ?>
                                    <tr class="border-t border-gray-700 hover:bg-gray-700/50">
                                        <td class="p-2 font-bold text-cyan-400"><?php echo $rank++; ?></td>
                                        <td class="p-2">
                                            <div class="flex items-center">
                                                <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="w-10 h-10 rounded-md mr-3 object-cover">
                                                <div>
                                                    <p class="font-bold text-white"><?php echo htmlspecialchars($row['character_name']); ?></p>
                                                    <?php if (isset($row['race'], $row['class'])): ?>
                                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars(strtoupper($row['race'] . ' ' . $row['class'])); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-2 text-right font-semibold text-white">
                                            <?php
                                                $val = isset($row[$details['field']]) ? $row[$details['field']] : 0;
                                                echo ($details['format'] === 'number') ? number_format((int)$val) : htmlspecialchars((string)$val);
                                            ?>
                                        </td>
                                        <td class="p-2 text-right">
                                            <a href="/view_profile.php?id=<?php echo (int)$row['id']; ?>" class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-1 px-3 rounded-md text-xs">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div> </div>