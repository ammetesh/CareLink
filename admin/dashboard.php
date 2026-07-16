<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";

?>

<div class="p-10">

<h1 class="text-4xl font-bold">
Welcome, <?php echo htmlspecialchars($_SESSION["user_name"]); ?> 👋
</h1>

<p class="text-gray-600 mt-2">
Admin Dashboard
</p>

</div>

<?php
include "../includes/footer.php";
?>