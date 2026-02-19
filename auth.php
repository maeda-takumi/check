<?php

function requireLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['is_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

function currentUserId(): int
{
    return (int)($_SESSION['auth_user_id'] ?? 0);
}
