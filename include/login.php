<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();


?>

<div class="mk-login-shell">
  <section class="mk-login-hero">
    <div class="mk-login-brand"><img src="img/favicon.png" alt="Mikhmon"><span>MIKHMON Admin</span></div>
    <div class="mk-login-copy">
      <span class="eyebrow"><i class="fa fa-shield"></i> Administrator hotspot</span>
      <h1>Kontrol jaringan dalam satu panel.</h1>
      <p>Kelola router, hotspot, voucher, laporan, dan reseller melalui antarmuka yang cepat dan responsif.</p>
    </div>
  </section>
  <section class="mk-login-form-side">
    <div class="mk-login-form">
      <h2>Masuk ke admin</h2>
      <p>Gunakan akun administrator Mikhmon.</p>
      <?php if (!empty($error)): ?><div class="mk-login-error"><i class="fa fa-exclamation-circle"></i> Username atau password tidak benar.</div><?php endif; ?>
      <form autocomplete="on" action="" method="post">
        <div class="mk-login-field">
          <label for="_username">Username</label>
          <div class="mk-login-input"><i class="fa fa-user"></i><input class="form-control" type="text" name="user" id="_username" placeholder="Masukkan username" autocomplete="username" required autofocus></div>
        </div>
        <div class="mk-login-field">
          <label for="_password">Password</label>
          <div class="mk-login-input"><i class="fa fa-lock"></i><input class="form-control" type="password" name="pass" id="_password" placeholder="Masukkan password" autocomplete="current-password" required></div>
        </div>
        <button class="btn-login" type="submit" name="login"><i class="fa fa-sign-in"></i> Masuk ke Panel</button>
      </form>
      <div class="mk-login-note"><i class="fa fa-lock"></i> Akses khusus administrator</div>
    </div>
  </section>
</div>

</body>
</html>
