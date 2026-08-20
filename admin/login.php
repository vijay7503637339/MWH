<?php

declare(strict_types=1);

session_start();

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MWH Admin Login</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-page">
    <main class="login-card">
        <div class="brand-mark">MWH</div>
        <h1>Admin Panel</h1>
        <p class="muted">Maan World Hospitality</p>

        <form id="loginForm">
            <label>Email</label>
            <input type="email" id="email" name="email" autocomplete="username" required>

            <label>Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit" id="loginButton">Sign in</button>
            <div id="loginMessage" class="message" hidden></div>
        </form>
    </main>

<script>
const form = document.getElementById('loginForm');
const button = document.getElementById('loginButton');
const message = document.getElementById('loginMessage');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    button.disabled = true;
    button.textContent = 'Signing in...';
    message.hidden = true;

    try {
        const response = await fetch('../api/auth/login.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value
            })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Login failed');
        }

        window.location.href = 'dashboard.php';
    } catch (error) {
        message.textContent = error.message;
        message.hidden = false;
    } finally {
        button.disabled = false;
        button.textContent = 'Sign in';
    }
});
</script>
</body>
</html>
