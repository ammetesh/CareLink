<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$message = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $family_id = $_SESSION["user_id"];
    $email = trim($_POST["email"]);

    /* Check if patient exists */

    $stmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        AND role = 'patient'
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

   if($result->num_rows == 0){

        $message = "Patient not found.";

    }else{

        $patient = $result->fetch_assoc();
        $patient_id = $patient["id"];

        ///* Check if already linked 

        $check = $conn->prepare("
            SELECT id
            FROM family_links
            WHERE family_id = ?
        ");

        $check->bind_param("i", $family_id);
        $check->execute();

        $exists = $check->get_result();

        if($exists->num_rows > 0){

            $message = "You are already linked to a patient.";

        }else{

            $insert = $conn->prepare("
                INSERT INTO family_links(patient_id, family_id)
                VALUES(?, ?)
            ");

            $insert->bind_param("ii", $patient_id, $family_id);

           if($insert->execute()){

    header("Location: dashboard.php");
    exit();

}else{

    $message = "Something went wrong.";

}

        }

    }

}

?>

<div class="max-w-xl mx-auto mt-10 bg-white shadow-lg rounded-xl p-8">

<h1 class="text-3xl font-bold mb-6">

Link Patient

</h1>

<?php

if($message != ""){

echo "<p class='mb-5 text-blue-600 font-semibold'>$message</p>";

}

?>

<form method="POST">

<label class="block mb-2 font-semibold">

Patient Email

</label>

<input
type="email"
name="email"
required
placeholder="Enter Patient Email"
class="w-full border rounded-lg p-3 mb-6">

<button
class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">

Link Patient

</button>

</form>

</div>

<?php include "../includes/footer.php"; ?>