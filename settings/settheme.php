<?php
/*
 * Theme switching is stored in the current PHP session.
 * Application source files are never rewritten at runtime.
 */

$gettheme = isset($_GET['set-theme']) ? (string) $_GET['set-theme'] : '';
$mtheme = array('dark', 'light', 'blue', 'green', 'pink');
$themeColorMap = array(
    'dark' => '#0d1511',
    'light' => '#f4f7f2',
    'blue' => '#126e9d',
    'green' => '#27784c',
    'pink' => '#b52d69',
);

if ($gettheme !== '' && in_array($gettheme, $mtheme, true)) {
    $_SESSION['theme'] = $gettheme;
    $_SESSION['themecolor'] = $themeColorMap[$gettheme];

    $redirectUrl = preg_replace('/([?&])set-theme=[^&]*(&|$)/', '$1', $url);
    $redirectUrl = rtrim((string) $redirectUrl, '?&');
    header('Location: ' . ($redirectUrl !== '' ? $redirectUrl : './admin.php?id=sessions'));
    exit;
}
?>
