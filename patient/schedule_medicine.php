<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$medicine_id = $_GET["id"];

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $dose_time = $_POST["dose_time"];

    $stmt = $conn->prepare("
    INSERT INTO schedules(medicine_id,dose_time)
    VALUES(?,?)
    ");

    $stmt->bind_param("is",$medicine_id,$dose_time);

    if($stmt->execute()){

        $message="Schedule Added!";

    }

}

$medicine = $conn->query("SELECT medicine_name FROM medicines WHERE id=$medicine_id")->fetch_assoc();

$schedules = $conn->query("SELECT * FROM schedules WHERE medicine_id=$medicine_id ORDER BY dose_time");

?>

<div class="max-w-3xl mx-auto mt-10 bg-white rounded-xl shadow p-8">

<h1 class="text-3xl font-bold">

Schedule Medicine

</h1>

<h2 class="text-blue-600 mt-2 mb-8">

<?php echo $medicine["medicine_name"]; ?>

</h2>

<?php

if($message!=""){

echo "<div class='bg-green-100 p-3 rounded mb-5'>$message</div>";

}

?>

<form method="POST">

<input

type="time"

name="dose_time"

required

class="border p-3 rounded">

<button

class="bg-blue-600 text-white px-5 py-3 rounded ml-3">

Add Time

</button>

</form>

<hr class="my-8">

<h2 class="text-2xl font-bold mb-4">

Current Schedule

</h2>

<?php

while($row=$schedules->fetch_assoc()){

echo "<div class='flex justify-between border-b py-3'>";

echo "<span>".$row["dose_time"]."</span>";

echo "</div>";

}

?>

</div>

<?php include "../includes/footer.php"; ?>