<?php
/*
 *  Mikhmon V3 — Reseller Panel · Login View
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>MIKHMON Reseller — Login</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= $themecolor ?>" />
    <link rel="stylesheet" href="css/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="css/mikhmon-ui.<?= $theme; ?>.min.css">
    <link rel="icon" href="./img/favicon.png" />
    <script src="js/jquery.min.js"></script>
    <style>
    .reseller-login-box{max-width:380px;margin:0 auto;padding-top:8%}
    .reseller-login-box .card{border-radius:8px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.18)}
    .reseller-login-box .card-header{text-align:center;padding:18px 12px 10px}
    .reseller-login-box .card-body{padding:12px 24px 24px}
    .reseller-login-box input[type=text],
    .reseller-login-box input[type=password]{width:100%;height:40px;font-size:15px;padding:6px 12px;margin-bottom:10px;box-sizing:border-box;border:1px solid rgba(128,128,128,.3);border-radius:5px}
    .reseller-login-box .btn-login{width:100%;height:42px;font-weight:700;font-size:16px;border:0;border-radius:5px;cursor:pointer;margin-top:8px}
    .r-badge-reseller{display:inline-block;padding:3px 12px;border-radius:12px;font-size:11px;font-weight:600;background:#e67e22;color:#fff;margin-bottom:8px}
    </style>
</head>
<body>
<div class="wrapper">

<div class="reseller-login-box">
  <div class="card">
    <div class="card-header">
      <h3>Reseller Login</h3>
    </div>
    <div class="card-body">
      <div class="text-center" style="padding:10px 0">
        <img src="img/favicon.png" alt="MIKHMON" style="max-height:60px">
      </div>
      <div class="text-center">
        <span style="font-size:22px;font-weight:700;">MIKHMON</span><br>
        <span class="r-badge-reseller"><i class="fa fa-user-circle"></i> Reseller Panel</span>
      </div>
      <form autocomplete="off" method="post" action="./reseller.php?page=login">
        <input class="form-control" type="text" name="user" placeholder="Username" required autofocus>
        <input class="form-control" type="password" name="pass" placeholder="Password" required>
        <input class="btn-login bg-primary pointer" type="submit" name="reseller_login" value="Login">
        <?= $error; ?>
      </form>
    </div>
  </div>
</div>

</div>
</body>
</html>
