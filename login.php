<?php
require_once __DIR__ . "/includes/header.php";
require_once __DIR__ . "/config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];

            switch ($user["role"]) {

                case "patient":
                    header("Location: patient/dashboard.php");
                    exit();

                case "family":
                    header("Location: family/dashboard.php");
                    exit();

                case "admin":
                    header("Location: admin/dashboard.php");
                    exit();
            }

        } else {
            $message = "Invalid Password!";
        }

    } else {
        $message = "User not found!";
    }

    $stmt->close();
}
?>

<div class="min-h-screen flex justify-center items-center">

<div class="bg-white shadow-lg rounded-xl p-8 w-full max-w-md">

<h2 class="text-3xl font-bold text-center text-green-600 mb-6">
Login
</h2>

<?php
if($message!=""){
    echo "<p class='text-center text-red-600 mb-4'>$message</p>";
}
?>

<form method="POST">

<input
name="email"
type="email"
placeholder="Email"
required
class="w-full border p-3 rounded mb-4">

<input
name="password"
type="password"
placeholder="Password"
required
class="w-full border p-3 rounded mb-6">

<button
class="bg-green-600 hover:bg-green-700 text-white w-full p-3 rounded">

Login

</button>

</form>

</div>

</div>

<?php include "includes/footer.php"; ?>