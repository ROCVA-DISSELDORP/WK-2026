<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();
$user = currentUser();

// =================================================================
// WAT MOET DIT BESTAND DOEN?
// =================================================================
// Een ingelogde gebruiker heeft een toegangscode gekregen van iemand
// anders en wil meedoen aan die poule. Het proces:
//   1. Controleer of de code niet leeg is (validatie).
//   2. Zoek de bijbehorende poule in de database.
//   3. Check of de gebruiker al lid is (zo ja: niet dubbel toevoegen).
//   4. Voeg de gebruiker toe aan de poule.
//   5. Stuur door naar de detailpagina van de poule.
//
// WAAROM EERST CHECKEN OF IEMAND AL LID IS?
// Op de `pool_members` tabel staat een UNIQUE KEY op (pool_id, user_id).
// Als je iemand probeert toe te voegen die al lid is, crasht de INSERT.
// We willen netjes afhandelen door simpelweg door te sturen naar de
// poule-pagina — de gebruiker ziet gewoon de poule die hij wilde
// bekijken, zonder foutmelding.
// =================================================================


$errors  = [];
$code    = '';
$pool    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // De code uit het formulier: trim (spaties weg) + uppercase voor
    // hoofdletter-ongevoelige matching. Dus "abc12345" wordt "ABC12345".
    $code = strtoupper(trim($_POST['access_code'] ?? ''));

    // Validatie
    if ($code === '') {
        $errors[] = 'Vul een toegangscode in.';
    }

    // Poule zoeken op basis van code
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM pools WHERE access_code = ?");
        $stmt->execute([$code]);
        $pool = $stmt->fetch();

        if (!$pool) {
            $errors[] = 'Deze toegangscode is onbekend.';
        }
    }

    // Controleer of gebruiker al lid is
    if (empty($errors) && $pool) {
        $stmt = $pdo->prepare(
            "SELECT id FROM pool_members WHERE pool_id = ? AND user_id = ?"
        );
        $stmt->execute([$pool['id'], $user['id']]);

        if ($stmt->fetch()) {
            header('Location: pool_detail.php?id=' . $pool['id']);
            exit;
        }
    }

    // Gebruiker toevoegen aan poule
    if (empty($errors) && $pool) {
        $stmt = $pdo->prepare(
            "INSERT INTO pool_members (pool_id, user_id) VALUES (?, ?)"
        );
        $stmt->execute([$pool['id'], $user['id']]);

        header('Location: pool_detail.php?id=' . $pool['id']);
        exit;
    }
}

$pageTitle = 'Poule joinen';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-wrapper">
        <div class="form-card">
            <h1 class="form-title">Join een poule</h1>
            <p class="form-subtitle">Heb je een toegangscode gekregen? Vul hem hier in om deel te nemen.</p>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <form method="POST" action="join_pool.php" novalidate>
                <div class="form-group">
                    <label class="form-label" for="access_code">Toegangscode</label>
                    <input type="text" id="access_code" name="access_code" class="form-input"
                           value="<?= htmlspecialchars($code) ?>"
                           placeholder="Bijv. A1B2C3D4"
                           style="font-family: var(--font-mono); letter-spacing: 0.15em; text-transform: uppercase;"
                           maxlength="20" required>
                    <p class="form-help">De 8-cijferige code die je van de poule-beheerder hebt gekregen.</p>
                </div>

                <div class="flex gap-2">
                    <a href="pools.php" class="btn btn-ghost">Annuleren</a>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Join poule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
