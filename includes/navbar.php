<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<nav class="bg-blue-700 text-white shadow-md">

    <div class="max-w-7xl mx-auto flex justify-between items-center p-4">

        <!-- Logo -->

        <a href="/" class="text-3xl font-bold">
            CareLink
        </a>

        <!-- Navigation -->

        <div class="flex items-center gap-6">

            <?php if(isset($_SESSION["role"])): ?>

                <!-- ==========================
                     PATIENT NAVBAR
                =========================== -->

                <?php if($_SESSION["role"] == "patient"): ?>

                    <a href="/CareLink/patient/dashboard.php" class="hover:text-gray-300">
                        Dashboard
                    </a>

                    <a href="/CareLink/patient/medicines.php" class="hover:text-gray-300">
                        Medicines
                    </a>

                    <a href="/CareLink/patient/today_medicines.php" class="hover:text-gray-300">
                        Today's Medicines
                    </a>

                <?php endif; ?>


                <!-- ==========================
                     FAMILY NAVBAR
                =========================== -->

                <?php if($_SESSION["role"] == "family"): ?>

                    <a href="/CareLink/family/dashboard.php" class="hover:text-gray-300">
                        Dashboard
                    </a>

                    <a href="/CareLink/family/report.php" class="hover:text-gray-300">
                        Reports
                    </a>

                <?php endif; ?>


                <!-- ==========================
                     COMMON LINKS
                =========================== -->

                <a href="#" class="hover:text-gray-300">
                    Settings
                </a>

                <a
                    href="/CareLink/logout.php"
                    class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg">

                    Logout

                </a>

            <?php endif; ?>

        </div>

    </div>

</nav>