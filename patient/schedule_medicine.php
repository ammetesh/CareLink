<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$medicine_id = $_GET["id"];

$medicine = $conn->query("
SELECT
medicine_name,
take_time,
meal_timing
FROM medicines
WHERE id=$medicine_id
")->fetch_assoc();


$schedules = $conn->query("
SELECT *
FROM schedules
WHERE medicine_id=$medicine_id
ORDER BY dose_time
");

?>

<div class="max-w-4xl mx-auto mt-10 bg-white rounded-xl shadow-lg p-8">


<h1 class="text-3xl font-bold">

Auto Scheduled Medicine

</h1>


<p class="text-gray-500 mt-2">

Schedules are automatically generated based on your selected timings.

</p>



<div class="bg-blue-50 rounded-xl p-6 mt-8">

<h2 class="text-2xl font-bold text-blue-700">

<?php echo htmlspecialchars($medicine["medicine_name"]); ?>

</h2>


<p class="mt-4">

<strong>Take During :</strong>

<?php

echo htmlspecialchars($medicine["take_time"]);

?>

</p>


<p class="mt-2">

<strong>Meal Timing :</strong>

<?php

echo htmlspecialchars($medicine["meal_timing"]);

?>

</p>


</div>



<div class="mt-10">

<h2 class="text-2xl font-bold mb-5">

Medicine Schedule

</h2>


<?php

if($schedules->num_rows==0){

?>

<div class="bg-yellow-50 border border-yellow-300 rounded-xl p-5">

No schedules have been created yet.

</div>

<?php

}else{


while($row=$schedules->fetch_assoc()){

?>

<div class="flex justify-between items-center border-b py-4">

<div>

<p class="font-semibold">

Medicine Reminder

</p>

<p class="text-gray-500">

<?php

echo date(

"h:i A",

strtotime($row["dose_time"])

);

?>

</p>

</div>


<div class="text-green-600 font-bold">

Auto Scheduled ✓

</div>


</div>


<?php

}

}

?>


</div>



<div class="bg-green-50 border border-green-300 rounded-xl p-6 mt-10">


<h3 class="text-xl font-bold text-green-700">

Smart Reminder System (Coming Soon)

</h3>


<p class="mt-3 text-gray-700">

CareLink will automatically remind patients to take their medicines
during the scheduled time period. Family members will also be alerted
if medicines are repeatedly missed.

</p>


</div>



</div>


<?php include "../includes/footer.php"; ?>