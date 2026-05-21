<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();
$user = currentUser();

$errors  = [];
$success = '';
$predictions = [];
$view_user = $user;
$view_user_id = (int)($_GET['user_id'] ?? $user['id']);
$pool_id = (int)($_GET['pool_id'] ?? 0);
$is_own_predictions = $view_user_id === (int)$user['id'];

if ($view_user_id <= 0) {
    $view_user_id = (int)$user['id'];
    $is_own_predictions = true;
}

if (!$is_own_predictions) {
    if ($pool_id <= 0) {
        header('Location: pools.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            INNER JOIN pool_members pm_target ON pm_target.user_id = u.id AND pm_target.pool_id = ?
            INNER JOIN pool_members pm_me ON pm_me.pool_id = pm_target.pool_id AND pm_me.user_id = ?
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$pool_id, $user['id'], $view_user_id]);
        $view_user = $stmt->fetch();
    } catch (PDOException $e) {
        $view_user = false;
    }

    if (!$view_user) {
        header('Location: pools.php');
        exit;
    }
}

// Alle wedstrijden ophalen
try {
    $stmt = $pdo->query("SELECT * FROM matches ORDER BY match_date ASC");
    $matches = $stmt->fetchAll();
} catch (PDOException $e) {
    $matches = [];
}

// Bestaande voorspellingen van de gebruiker ophalen
try {
    $stmt = $pdo->prepare("SELECT * FROM predictions WHERE user_id = ?");
    $stmt->execute([$view_user_id]);
    foreach ($stmt->fetchAll() as $row) {
        $predictions[$row['match_id']] = $row;
    }
} catch (PDOException $e) {
    $predictions = [];
}

// Voorspellingen opslaan / updaten bij POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_own_predictions) {
    $sql = "INSERT INTO predictions (user_id, match_id, predicted_home, predicted_away)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              predicted_home = VALUES(predicted_home),
              predicted_away = VALUES(predicted_away)";
    $stmt = $pdo->prepare($sql);

    foreach ($_POST['predictions'] ?? [] as $match_id => $scores) {
        $home = $scores['home'] ?? '';
        $away = $scores['away'] ?? '';

        if ($home === '' || $away === '') {
            continue;
        }
        if (!ctype_digit((string)$home) || !ctype_digit((string)$away)) {
            continue;
        }

        $stmt->execute([
            $view_user_id, (int)$match_id, (int)$home, (int)$away
        ]);
    }

    $success = 'Je voorspellingen zijn opgeslagen!';

    // Predictions array vernieuwen met nieuwe data
    $stmt2 = $pdo->prepare("SELECT * FROM predictions WHERE user_id = ?");
    $stmt2->execute([$view_user_id]);
    $predictions = [];
    foreach ($stmt2->fetchAll() as $row) {
        $predictions[$row['match_id']] = $row;
    }
}


$pageTitle = 'Voorspellingen';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <?php if ($pool_id > 0): ?>
        <div style="margin-bottom: 24px;">
            <a href="pool_detail.php?id=<?= $pool_id ?>" class="nav-link" style="padding-left: 0;">← Terug naar poule</a>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <div class="page-eyebrow">Speelronde</div>
            <h1 class="page-title">
                <?= $is_own_predictions
                    ? 'Voorspel de uitslagen'
                    : 'Voorspellingen van ' . htmlspecialchars($view_user['name']) ?>
            </h1>
            <p class="page-desc">
                <?= $is_own_predictions
                    ? 'Vul per wedstrijd je voorspelde eindstand in. Lege velden worden genegeerd. Je kunt je voorspellingen later nog aanpassen.'
                    : 'Je bekijkt hier de voorspellingen van een medespeler uit jouw poule.' ?>
            </p>
        </div>
    </div>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (empty($matches)): ?>
        <div class="empty">
            <div class="empty-icon">📅</div>
            <h2 class="empty-title">Nog geen wedstrijden</h2>
            <p class="empty-text">Zodra TODO 1 is afgemaakt zie je hier alle wedstrijden verschijnen.</p>
        </div>
    <?php else: ?>
        <?php if ($is_own_predictions): ?>
        <form method="POST" action="predictions.php<?= $pool_id > 0 ? '?pool_id=' . $pool_id : '' ?>">
            <div class="match-list">
                <?php foreach ($matches as $match):
                    $mid = (int)$match['id'];
                    $existing = $predictions[$mid] ?? null;
                    $home_val = $existing['predicted_home'] ?? '';
                    $away_val = $existing['predicted_away'] ?? '';
                    $date = new DateTime($match['match_date']);
                ?>
                    <div class="match">
                        <div class="match-meta">
                            <span class="match-stage"><?= htmlspecialchars($match['stage']) ?></span>
                            <span><?= $date->format('d M Y · H:i') ?></span>
                        </div>

                        <div class="match-row">
                            <div class="team team-home">
                                <span class="team-name"><?= htmlspecialchars($match['home_team']) ?></span>
                                <span class="team-flag"><?= strtoupper(substr($match['home_team'], 0, 2)) ?></span>
                            </div>

                            <div class="score-input-group">
                                <input type="number"
                                       name="predictions[<?= $mid ?>][home]"
                                       class="score-input"
                                       min="0" max="99"
                                       value="<?= htmlspecialchars((string)$home_val) ?>"
                                       placeholder="-">
                                <span class="score-sep">:</span>
                                <input type="number"
                                       name="predictions[<?= $mid ?>][away]"
                                       class="score-input"
                                       min="0" max="99"
                                       value="<?= htmlspecialchars((string)$away_val) ?>"
                                       placeholder="-">
                            </div>

                            <div class="team">
                                <span class="team-flag"><?= strtoupper(substr($match['away_team'], 0, 2)) ?></span>
                                <span class="team-name"><?= htmlspecialchars($match['away_team']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="predictions-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    💾 Voorspellingen opslaan
                </button>
            </div>
        </form>
        <?php else: ?>
        <div class="match-list">
            <?php foreach ($matches as $match):
                $mid = (int)$match['id'];
                $existing = $predictions[$mid] ?? null;
                $home_val = $existing['predicted_home'] ?? '-';
                $away_val = $existing['predicted_away'] ?? '-';
                $date = new DateTime($match['match_date']);
            ?>
                <div class="match">
                    <div class="match-meta">
                        <span class="match-stage"><?= htmlspecialchars($match['stage']) ?></span>
                        <span><?= $date->format('d M Y · H:i') ?></span>
                    </div>

                    <div class="match-row">
                        <div class="team team-home">
                            <span class="team-name"><?= htmlspecialchars($match['home_team']) ?></span>
                            <span class="team-flag"><?= strtoupper(substr($match['home_team'], 0, 2)) ?></span>
                        </div>

                        <div class="score-input-group">
                            <input type="number"
                                   class="score-input"
                                   value="<?= htmlspecialchars((string)$home_val) ?>"
                                   disabled>
                            <span class="score-sep">:</span>
                            <input type="number"
                                   class="score-input"
                                   value="<?= htmlspecialchars((string)$away_val) ?>"
                                   disabled>
                        </div>

                        <div class="team">
                            <span class="team-flag"><?= strtoupper(substr($match['away_team'], 0, 2)) ?></span>
                            <span class="team-name"><?= htmlspecialchars($match['away_team']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
