<?php
include "includes/header.php";
include "config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $phone = trim($_POST["phone"]);
    $age = $_POST["age"];
    $role = $_POST["role"];

    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s",$email);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        $message = "Email already exists!";
    }else{

        $stmt = $conn->prepare("INSERT INTO users(full_name,email,password,phone,age,role)
        VALUES(?,?,?,?,?,?)");

        $stmt->bind_param(
            "ssssis",
            $name,
            $email,
            $password,
            $phone,
            $age,
            $role
        );

        if($stmt->execute()){
            $message = "Registration Successful!";
        }else{
            $message = "Something went wrong.";
        }

        $stmt->close();
    }

    $check->close();
}
?>

<div class="min-h-screen flex justify-center items-center">

<div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">

<h2 class="text-3xl font-bold mb-6 text-center text-blue-600">
Register
</h2>

<?php
if($message!=""){
    echo "<p class='mb-4 text-center text-red-600'>$message</p>";
}
?>

<form method="POST">

<input
name="name"
type="text"
placeholder="Full Name"
required
class="w-full border p-3 rounded mb-4">

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
class="w-full border p-3 rounded mb-4">

<input
name="phone"
type="text"
placeholder="Phone Number"
class="w-full border p-3 rounded mb-4">

<input
name="age"
type="number"
placeholder="Age"
class="w-full border p-3 rounded mb-4">

<select
name="role"
class="w-full border p-3 rounded mb-6">

<option value="patient">Patient</option>
<option value="family">Family Member</option>

</select>

<button
class="bg-blue-600 hover:bg-blue-700 text-white w-full p-3 rounded">

Register

</button>

</form>

<div class="text-center mt-5">

Already have an account?

<a href="login.php" class="text-blue-600">
Login
</a>

</div>

</div>

</div>

<?php include "includes/footer.php"; ?>