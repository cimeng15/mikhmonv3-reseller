<?php
/* Prevent direct access */
if (substr($_SERVER["REQUEST_URI"], -9) == "index.php") {
    header("Location:../admin.php?id=login");
}
