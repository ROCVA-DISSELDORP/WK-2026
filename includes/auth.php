<?php
// ============================================
// Authenticatie helper functies
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Controleer of de gebruiker is ingelogd.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Stuur de gebruiker door naar de login pagina als deze niet is ingelogd.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Geeft een deterministische achtergrondkleur voor een avatar op basis van de naam.
 */
function avatarColor(string $name): string {
    $colors = ['#e74c3c','#3498db','#2ecc71',
               '#9b59b6','#e67e22','#1abc9c',
               '#e91e63','#ff5722'];
    $index = ord($name[0]) % count($colors);
    return $colors[$index];
}

/**
 * Haal de huidige ingelogde gebruiker op (id, name, email).
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
    ];
}
