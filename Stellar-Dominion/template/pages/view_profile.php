<?php

/**
 * View Profile Page (Template)
 *
 * This file is a template and is meant to be included by a controller.
 * It expects all necessary variables to be prepared by the controller.
 *
 * @var array $user The user being viewed.
 * @var array $stats The stats of the user being viewed.
 * @var array $race The race of the user being viewed.
 * @var array|null $alliance The alliance of the user being viewed.
 * @var array $armory The armory of the user being viewed.
 * @var array $structures The structures of the user being viewed.
 * @var bool $is_self Whether the user is viewing their own profile.
 * @var bool|null $scouted Whether the profile is being viewed as a result of scouting.
 * @var string $active_page The currently active page for navigation.
 */

// To prevent errors if this template is loaded directly, we ensure
// that essential variables are at least defined as empty arrays or null.
// The controller is responsible for populating them with real data.
$user = $user ?? ['username' => 'Unknown', 'id' => 0];
$stats = $stats ?? ['level' => 0, 'networth' => 0, 'offense' => 0, 'defense' => 0];
$race = $race ?? ['name' => 'Unknown'];
$alliance = $alliance ?? null;
$armory = $armory ?? [];
$structures = $structures ?? [];
$is_self = $is_self ?? false;
$scouted = $scouted ?? false;
$active_page = $active_page ?? 'community';

// Include the header and navigation.
include __DIR__ . '/../includes/public_header.php';
include __DIR__ . '/../includes/navigation.php';

?>
<main class="container">
    <div class="row">
        <div class="col-md-4">
            <!-- Profile Sidebar -->
            <div class="card">
                <div class="card-body text-center">
                    <img src="/assets/img/<?php echo htmlspecialchars(strtolower($race['name'])); ?>.png" alt="<?php echo htmlspecialchars($race['name']); ?>" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px;">
                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                    <p class="text-muted"><?php echo htmlspecialchars($race['name']); ?> | Level: <?php echo htmlspecialchars($stats['level']); ?></p>
                    
                    <?php if ($alliance) : ?>
                        <p><strong>Alliance:</strong> <a href="/alliance/view/<?php echo $alliance['id']; ?>"><?php echo htmlspecialchars($alliance['name']); ?></a></p>
                    <?php else : ?>
                        <p><strong>Alliance:</strong> None</p>
                    <?php endif; ?>

                    <?php if ($scouted) : ?>
                        <div class="alert alert-info mt-3">This is a scout report.</div>
                    <?php endif; ?>

                    <?php
                    // This ensures the attack button appears on any profile
                    // that is not your own, including scouted reports.
                    ?>
                    <?php if (!$is_self && $user['id'] !== 0) : ?>
                        <div class="mt-4">
                            <a href="/attack/battle/<?php echo $user['id']; ?>" class="btn btn-danger btn-lg">Attack <?php echo htmlspecialchars($user['username']); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <!-- Main Profile Content -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link active" id="stats-tab" data-toggle="tab" href="#stats" role="tab" aria-controls="stats" aria-selected="true">Stats</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="armory-tab" data-toggle="tab" href="#armory" role="tab" aria-controls="armory" aria-selected="false">Armory</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="structures-tab" data-toggle="tab" href="#structures" role="tab" aria-controls="structures" aria-selected="false">Structures</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                            <h4>Base Stats</h4>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Networth
                                    <span class="badge badge-primary badge-pill"><?php echo number_format($stats['networth']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Offense
                                    <span class="badge badge-primary badge-pill"><?php echo number_format($stats['offense']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Defense
                                    <span class="badge badge-primary badge-pill"><?php echo number_format($stats['defense']); ?></span>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="armory" role="tabpanel" aria-labelledby="armory-tab">
                            <h4>Military Units</h4>
                            <ul class="list-group">
                                <?php foreach ($armory as $unit) : ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($unit['name']); ?>
                                        <span class="badge badge-info badge-pill"><?php echo number_format($unit['quantity']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="structures" role="tabpanel" aria-labelledby="structures-tab">
                            <h4>Player Structures</h4>
                            <ul class="list-group">
                                <?php foreach ($structures as $structure) : ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($structure['name']); ?>
                                        <span class="badge badge-secondary badge-pill">Level <?php echo htmlspecialchars($structure['level']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// Include the footer.
include __DIR__ . '/../includes/public_footer.php';
?>
