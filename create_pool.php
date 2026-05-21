<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireLogin();
$user = currentUser();

// =================================================================
// WAT MOET DIT BESTAND DOEN?
// =================================================================
// Een ingelogde gebruiker vult het formulier in met een poule-naam
// en (optioneel) een beschrijving. Als het formulier wordt verzonden:
//   1. Controleer of de ingevulde gegevens kloppen (validatie).
//   2. Maak een unieke 8-tekens toegangscode aan.
//   3. Sla de poule op in de database.
//   4. Voeg de aanmaker meteen toe als eerste lid van de poule.
//   5. Stuur de gebruiker door naar de detailpagina van de poule.
//
// WAAROM EEN TOEGANGSCODE?
// Andere gebruikers kunnen alleen meedoen als ze de code kennen.
// Zo blijven poules privé en kun je de code delen met bv. je klas.
//
// WAAROM MOET DE CODE UNIEK ZIJN?
// Op de kolom `access_code` staat een UNIQUE constraint in MySQL.
// Als je per ongeluk een bestaande code invoert, geeft MySQL een
// foutmelding en mislukt de INSERT. Met een random code van 8 tekens
// is de kans op botsing heel klein, maar bij duizenden poules niet nul.
// =================================================================


$errors      = [];
$name        = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validatie
    if ($name === '') {
        $errors[] = 'Naam is verplicht.';
    } elseif (mb_strlen($name) < 3) {
        $errors[] = 'Naam moet minimaal 3 tekens lang zijn.';
    } elseif (mb_strlen($name) > 100) {
        $errors[] = 'Naam mag maximaal 100 tekens lang zijn.';
    }
    if (mb_strlen($description) > 500) {
        $errors[] = 'Beschrijving mag maximaal 500 tekens lang zijn.';
    }

    // Unieke toegangscode genereren + poule opslaan
    if (empty($errors)) {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt = $pdo->prepare("SELECT id FROM pools WHERE access_code = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch());

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO pools (name, description, access_code, created_by) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$name, $description, $code, $user['id']]);

            $pool_id = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO pool_members (pool_id, user_id) VALUES (?, ?)"
            );
            $stmt->execute([$pool_id, $user['id']]);

            $pdo->commit();

            header('Location: pool_detail.php?id=' . $pool_id);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Er ging iets mis bij het aanmaken van de poule.';
        }
    }

}

$pageTitle = 'Nieuwe poule';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-wrapper">
        <div class="form-card">
            <h1 class="form-title">Nieuwe poule</h1>
            <p class="form-subtitle">Start je eigen competitie en nodig vrienden uit met een toegangscode.</p>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <form method="POST" action="create_pool.php" novalidate>
                <div class="form-group">
                    <label class="form-label" for="name">Naam van de poule</label>
                    <input type="text" id="name" name="name" class="form-input"
                           value="<?= htmlspecialchars($name) ?>"
                           placeholder="Bijv. Klas 4A - WK 2026" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Beschrijving (optioneel)</label>
                    <textarea id="description" name="description" class="form-textarea"
                              placeholder="Waar gaat deze poule over?"><?= htmlspecialchars($description) ?></textarea>
                </div>

                <div class="flex gap-2">
                    <a href="pools.php" class="btn btn-ghost">Annuleren</a>
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Poule aanmaken</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
