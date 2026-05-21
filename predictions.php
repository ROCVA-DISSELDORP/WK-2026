<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();
$user = currentUser();

$errors  = [];
$success = '';

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
    $stmt->execute([$user['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $predictions[$row['match_id']] = $row;
    }
} catch (PDOException $e) {
    $predictions = [];
}

// Voorspellingen opslaan / updaten bij POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $user['id'], (int)$match_id, (int)$home, (int)$away
        ]);
    }

    $success = 'Je voorspellingen zijn opgeslagen!';

    // Predictions array vernieuwen met nieuwe data
    $stmt2 = $pdo->prepare("SELECT * FROM predictions WHERE user_id = ?");
    $stmt2->execute([$user['id']]);
    $predictions = [];
    foreach ($stmt2->fetchAll() as $row) {
        $predictions[$row['match_id']] = $row;
    }
}


$pageTitle = 'Voorspellingen';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <div class="page-eyebrow">Speelronde</div>
            <h1 class="page-title">Voorspel de uitslagen</h1>
            <p class="page-desc">Vul per wedstrijd je voorspelde eindstand in. Lege velden worden genegeerd. Je kunt je voorspellingen later nog aanpassen.</p>
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
        <form method="POST" action="predictions.php">
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
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
