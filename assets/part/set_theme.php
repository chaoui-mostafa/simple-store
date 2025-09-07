<?php
// set_theme.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
    $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
    
    // Set cookie for 1 year
    setcookie('theme', $theme, time() + (365 * 24 * 60 * 60), '/');
    
    echo 'Theme set to ' . $theme;
    exit();
}
?>