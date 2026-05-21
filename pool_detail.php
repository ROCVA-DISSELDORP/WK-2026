<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();
$user = currentUser();

$pool_id = (int)($_GET['id'] ?? 0);
if ($pool_id <= 0) {
    header('Location: pools.php');
    exit;
}

// Haal de poule op + check of gebruiker lid is
$pool = null;
$members = [];

try {
    // Poule + check of user lid is
    $stmt = $pdo->prepare("
        SELECT p.*, u.name AS creator_name
        FROM pools p
        INNER JOIN users u ON u.id = p.created_by
        INNER JOIN pool_members pm ON pm.pool_id = p.id AND pm.user_id = ?
        WHERE p.id = ?
    ");
    $stmt->execute([$user['id'], $pool_id]);
    $pool = $stmt->fetch();

    if (!$pool) {
        header('Location: pools.php');
        exit;
    }

    // Leden ophalen
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, pm.joined_at
        FROM pool_members pm
        INNER JOIN users u ON u.id = pm.user_id
        WHERE pm.pool_id = ?
        ORDER BY pm.joined_at ASC
    ");
    $stmt->execute([$pool_id]);
    $members = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Fout bij ophalen van poule: ' . htmlspecialchars($e->getMessage()));
}

$pageTitle = $pool['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="margin-bottom: 24px;">
        <a href="pools.php" class="nav-link" style="padding-left: 0;">← Terug naar poules</a>
    </div>

    <div class="pool-hero">
        <div class="feature-number">POULE</div>
        <h1 class="pool-hero-title"><?= htmlspecialchars($pool['name']) ?></h1>
        <?php if ($pool['description']): ?>
            <p style="color: var(--text-dim); font-size: 16px; max-width: 640px;">
                <?= nl2br(htmlspecialchars($pool['description'])) ?>
            </p>
        <?php endif; ?>
        <div class="pool-hero-code">
            <span class="pool-hero-code-label">Toegangscode:</span>
            <strong><?= htmlspecialchars($pool['access_code']) ?></strong>
        </div>
    </div>

    <div class="dash-grid">
        <!-- Deelnemers -->
        <section class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Deelnemers</h2>
                    <p class="card-subtitle"><?= count($members) ?> LEDEN</p>
                </div>
            </div>

            <div class="member-list">
                <?php foreach ($members as $member): ?>
                    <a class="member member-link" href="predictions.php?pool_id=<?= (int)$pool['id'] ?>&user_id=<?= (int)$member['id'] ?>">
                        <div class="member-avatar">
                            <?= strtoupper(substr(htmlspecialchars($member['name']), 0, 1)) ?>
                        </div>
                        <div class="member-info">
                            <div class="member-name"><?= htmlspecialchars($member['name']) ?></div>
                            <div class="member-email"><?= htmlspecialchars($member['email']) ?></div>
                        </div>
                        <?php if ((int)$member['id'] === (int)$pool['created_by']): ?>
                            <span class="member-badge">Beheerder</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Sidebar -->
        <aside class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Acties</h2>
                    <p class="card-subtitle">BEHEER</p>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="predictions.php" class="btn btn-primary btn-block">⚽ Voorspellen</a>
                <div style="padding: 16px; background: var(--bg-deep); border: 1px dashed var(--border-hi); border-radius: var(--radius-sm);">
                    <div style="font-family: var(--font-mono); font-size: 11px; color: var(--text-mute); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 6px;">
                        Deel deze code met vrienden
                    </div>
                    <div style="font-family: var(--font-display); font-size: 24px; color: var(--field); letter-spacing: 0.1em;">
                        <?= htmlspecialchars($pool['access_code']) ?>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
