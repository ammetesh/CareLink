<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$patient_id = $_SESSION["user_id"];

$sql = "
SELECT

medicines.medicine_name,
medicines.dosage,
medicines.meal_timing,
schedules.id AS schedule_id,
schedules.dose_time

FROM medicines

JOIN schedules

ON medicines.id=schedules.medicine_id

WHERE medicines.patient_id=?

AND medicines.is_active=1

ORDER BY schedules.dose_time
";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i",$patient_id);

$stmt->execute();

$result=$stmt->get_result();

?>

<div class="max-w-5xl mx-auto p-8">

<h1 class="text-3xl font-bold mb-8">

Today's Medicines

</h1>

<?php

if($result->num_rows==0){

?>

<div class="bg-white rounded-xl shadow p-8 text-center">

No medicines scheduled.

</div>

<?php

}

while($row=$result->fetch_assoc()){

?>

<div class="bg-white rounded-xl shadow mb-6 p-6">

<div class="flex justify-between items-center">

<div>

<h2 class="text-2xl font-bold">

💊 <?php echo $row["medicine_name"]; ?>

</h2>

<p class="text-gray-600 mt-2">

<?php echo $row["dosage"]; ?>

<br>

<?php echo $row["meal_timing"]; ?>

</p>

</div>

<div class="text-right">

<p class="text-xl font-bold text-blue-600">

<?php echo date("h:i A",strtotime($row["dose_time"])); ?>

</p>

</div>

</div>

<div class="flex gap-4 mt-6">

<a
href="mark.php?id=<?php echo $row["schedule_id"]; ?>&status=Taken"
class="bg-green-600 text-white px-4 py-2 rounded">

Taken

</a>

<a
href="mark.php?id=<?php echo $row["schedule_id"]; ?>&status=Snoozed"
class="bg-yellow-500 text-white px-4 py-2 rounded">

Snooze

</a>

<a
href="mark.php?id=<?php echo $row["schedule_id"]; ?>&status=Skipped"
class="bg-red-600 text-white px-4 py-2 rounded">

Skip

</a>

</div>

</div>

<?php

}

?>

</div>

<?php include "../includes/footer.php"; ?>