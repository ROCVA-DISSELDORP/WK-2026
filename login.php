<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Als de gebruiker al is ingelogd, stuur door naar dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$email  = '';
$notice = '';

// Succesbericht na registratie
if (isset($_GET['registered'])) {
    $notice = 'Je account is aangemaakt. Log nu in met je gegevens.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password']   ?? '';

    // Validatie
    if ($email === '') {
        $errors[] = 'E-mailadres is verplicht.';
    }
    if ($password === '') {
        $errors[] = 'Wachtwoord is verplicht.';
    }

    // Gebruiker ophalen & wachtwoord verifiëren
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $dbUser = $stmt->fetch();

        if ($dbUser && password_verify($password, $dbUser['password'])) {
            // Sessie starten
            $_SESSION['user_id']    = $dbUser['id'];
            $_SESSION['user_name']  = $dbUser['name'];
            $_SESSION['user_email'] = $dbUser['email'];

            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Ongeldige inloggegevens.';
        }
    }

}

$pageTitle = 'Inloggen';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="form-wrapper">
        <div class="form-card">
            <h1 class="form-title">Inloggen</h1>
            <p class="form-subtitle">Welkom terug in het stadion.</p>

            <?php if ($notice): ?>
                <div class="alert alert-success">✓ <?= htmlspecialchars($notice) ?></div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <form method="POST" action="login.php" novalidate>
                <div class="form-group">
                    <label class="form-label" for="email">E-mailadres</label>
                    <input type="email" id="email" name="email" class="form-input"
                           value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Wachtwoord</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">
                    Inloggen
                </button>
            </form>

            <div class="form-footer">
                Nog geen account? <a href="register.php">Registreer hier</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
