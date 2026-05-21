<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Als de gebruiker al is ingelogd, stuur door naar dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$name   = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Validatie
    if ($name === '') {
        $errors[] = 'Naam is verplicht.';
    } elseif (mb_strlen($name) < 2) {
        $errors[] = 'Naam moet minimaal 2 tekens lang zijn.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Vul een geldig e-mailadres in.';
    }
    if (mb_strlen($password) < 6) {
        $errors[] = 'Wachtwoord moet minimaal 6 tekens lang zijn.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Wachtwoorden komen niet overeen.';
    }

    // Controleer of e-mail al bestaat
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Dit e-mailadres is al in gebruik.';
        }
    }

    // Gebruiker opslaan
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $hashed]);

        header('Location: login.php?registered=1');
        exit;
    }

}

$pageTitle = 'Registreren';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-wrapper">
        <div class="form-card">
            <h1 class="form-title">Word lid</h1>
            <p class="form-subtitle">Maak een account en start direct met voorspellen.</p>

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" action="register.php" novalidate>
                <div class="form-group">
                    <label class="form-label" for="name">Volledige naam</label>
                    <input type="text" id="name" name="name" class="form-input"
                           value="<?= htmlspecialchars($name) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">E-mailadres</label>
                    <input type="email" id="email" name="email" class="form-input"
                           value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Wachtwoord</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                    <p class="form-help">Minimaal 6 tekens</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirm">Bevestig wachtwoord</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-input" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">
                    Account aanmaken
                </button>
            </form>

            <div class="form-footer">
                Al een account? <a href="login.php">Log hier in</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
