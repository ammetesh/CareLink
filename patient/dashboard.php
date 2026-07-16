<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$patient_id = $_SESSION["user_id"];

/* =====================================
   TOTAL ACTIVE MEDICINES
===================================== */

$totalMedicines = 0;

$sql = "
SELECT COUNT(*) AS total
FROM medicines
WHERE patient_id = ?
AND is_active = 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();

$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    $totalMedicines = $row["total"];
}

/* =====================================
   TAKEN TODAY
===================================== */

$takenToday = 0;

$sql = "
SELECT COUNT(*) AS taken

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id = schedules.id

JOIN medicines
ON schedules.medicine_id = medicines.id

WHERE medicines.patient_id = ?

AND dose_logs.log_date = CURDATE()

AND dose_logs.status='Taken'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$patient_id);
$stmt->execute();

$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    $takenToday = $row["taken"];
}

/* =====================================
   SKIPPED TODAY
===================================== */

$skippedToday = 0;

$sql = "
SELECT COUNT(*) AS skipped

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id=schedules.id

JOIN medicines
ON schedules.medicine_id=medicines.id

WHERE medicines.patient_id=?

AND dose_logs.log_date=CURDATE()

AND dose_logs.status='Skipped'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$patient_id);
$stmt->execute();

$result=$stmt->get_result();

if($row=$result->fetch_assoc()){
    $skippedToday=$row["skipped"];
}

/* =====================================
   TOTAL SCHEDULED DOSES TODAY
===================================== */

$totalSchedules = 0;

$sql = "
SELECT COUNT(*) AS total

FROM schedules

JOIN medicines
ON schedules.medicine_id = medicines.id

WHERE medicines.patient_id = ?

AND medicines.is_active = 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$patient_id);
$stmt->execute();

$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    $totalSchedules = $row["total"];
}

/* =====================================
   SNOOZED TODAY
===================================== */

$snoozedToday = 0;

$sql = "

SELECT COUNT(*) AS total

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id = schedules.id

JOIN medicines
ON schedules.medicine_id = medicines.id

WHERE medicines.patient_id = ?

AND dose_logs.log_date = CURDATE()

AND dose_logs.status = 'Snoozed'

";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $patient_id);

$stmt->execute();

$result = $stmt->get_result();

if($row = $result->fetch_assoc()){

    $snoozedToday = $row["total"];

}

/* =====================================
   PENDING
===================================== */

$pendingToday = $totalSchedules - ($takenToday + $skippedToday + $snoozedToday);

if($pendingToday < 0){

    $pendingToday = 0;

}

/* =====================================
   ADHERENCE
===================================== */

$adherence = 0;

if($totalSchedules > 0){

    $adherence = round(
        ($takenToday/$totalSchedules)*100
    );

}


$progressMessage="Needs Attention";

if($adherence>=90){

    $progressMessage="Excellent Progress";

}

elseif($adherence>=70){

    $progressMessage="Good Progress";

}

elseif($adherence>=50){

    $progressMessage="Keep Going";

}

/* =====================================
   TODAY'S MEDICINES
===================================== */

$sql = "

SELECT

schedules.id AS schedule_id,

schedules.dose_time,

medicines.medicine_name,

medicines.dosage,

medicines.meal_timing,

dose_logs.status

FROM schedules

JOIN medicines

ON schedules.medicine_id = medicines.id

LEFT JOIN dose_logs

ON schedules.id = dose_logs.schedule_id

AND dose_logs.log_date = CURDATE()

WHERE medicines.patient_id = ?

AND medicines.is_active = 1

ORDER BY schedules.dose_time

";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i",$patient_id);

$stmt->execute();

$todayMedicines = $stmt->get_result();

?>
<?php

$hour=date("H");

if($hour<12){

    $greeting="Good Morning";

}

elseif($hour<17){

    $greeting="Good Afternoon";

}

else{

    $greeting="Good Evening";

}


$tips=array(

"Drink plenty of water every day.",

"Never skip prescribed medicines.",

"Maintain healthy sleeping habits.",

"Take medicines at the scheduled time.",

"Exercise regularly for better wellbeing.",

"Keep yourself hydrated throughout the day.",

"Always consult your doctor before stopping medications."

);


$dailyTip=$tips[array_rand($tips)];

?>
<div class="max-w-7xl mx-auto p-8">

<h1 class="text-4xl font-bold">

<?php echo $greeting; ?>,

<?php echo htmlspecialchars($_SESSION["user_name"]); ?> 👋
</h1>

<p class="text-gray-500 mt-2">

Stay consistent with your medications and take care of your health today.

</p>

<!-- SUMMARY CARDS -->

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mt-10">


    <!-- Total Medicines -->

    <div class="bg-white shadow-lg rounded-xl p-6">

        <p class="text-gray-500">
            Total Medicines
        </p>

        <h2 class="text-5xl font-bold text-blue-600 mt-4">

            <?php echo $totalMedicines; ?>

        </h2>

    </div>

    <!-- Taken Today -->

    <div class="bg-white shadow-lg rounded-xl p-6">

        <p class="text-gray-500">
            Taken Today
        </p>

        <h2 class="text-5xl font-bold text-green-600 mt-4">

            <?php echo $takenToday; ?>

        </h2>

    </div>

    <!-- Skipped -->

    <div class="bg-white shadow-lg rounded-xl p-6">

        <p class="text-gray-500">
            Skipped
        </p>

        <h2 class="text-5xl font-bold text-red-600 mt-4">

            <?php echo $skippedToday; ?>

        </h2>

    </div>

    <!-- Pending -->

    <div class="bg-white shadow-lg rounded-xl p-6">

        <p class="text-gray-500">
            Pending
        </p>

        <h2 class="text-5xl font-bold text-yellow-500 mt-4">

            <?php echo $pendingToday; ?>

        </h2>

    </div>

    <!-- Adherence -->

    <div class="bg-white shadow-lg rounded-xl p-6">

        <p class="text-gray-500">
            Adherence
        </p>

        <h2 class="text-5xl font-bold text-purple-600 mt-4">

            <?php echo $adherence;?>%

<div class="w-full bg-gray-200 rounded-full h-3 mt-4">

<div

class="bg-purple-600 h-3 rounded-full"

style="width:<?php echo $adherence;?>%;">

</div>

</div>

<p class="text-sm text-gray-600 mt-3 font-semibold">

<?php echo $progressMessage; ?>

</p>

        </h2>

    </div>

</div>

<!-- TODAY'S MEDICINES -->

<div class="bg-white rounded-xl shadow-lg mt-10 p-6">

    <h2 class="text-3xl font-bold mb-6">

        Today's Medicines

    </h2>

    <?php

    if($todayMedicines->num_rows==0){
?>
        <div class="text-center py-10">

<h3 class="text-2xl font-bold">

No Medicines Scheduled Today

</h3>

<p class="text-gray-500 mt-3">

You're all caught up for now.

</p>

</div>
<?php
    }else{

    ?>

    <table class="w-full">

        <thead>

            <tr class="border-b">

                <th class="text-left p-4">

                    Medicine

                </th>

                <th class="text-left p-4">

                    Time

                </th>

                <th class="text-left p-4">

                    Dosage

                </th>

                <th class="text-left p-4">

                    Status

                </th>

                <th class="text-center p-4">

                    Action

                </th>

            </tr>

        </thead>

        <tbody>

<?php

while($medicine = $todayMedicines->fetch_assoc()){

$status = $medicine["status"] ?? "Pending";

if(empty($status)){
    $status = "Pending";
}

$statusColor="bg-gray-200 text-gray-700";

if($status=="Taken"){

    $statusColor="bg-green-100 text-green-700";

}

if($status=="Skipped"){

    $statusColor="bg-red-100 text-red-700";

}

if($status=="Snoozed"){

    $statusColor="bg-yellow-100 text-yellow-700";

}

?>

<tr class="border-b hover:bg-gray-50">

<td class="p-4">

💊

<strong>

<?php echo htmlspecialchars($medicine["medicine_name"]); ?>

</strong>

<br>

<span class="text-sm text-gray-500">

<?php echo htmlspecialchars($medicine["meal_timing"]); ?>

</span>

</td>

<td class="p-4">

<?php

echo date(
"h:i A",
strtotime($medicine["dose_time"])
);

?>

</td>

<td class="p-4">

<?php echo htmlspecialchars($medicine["dosage"]); ?>

</td>

<td class="p-4">

<span class="<?php echo $statusColor; ?> px-3 py-1 rounded-full text-sm font-semibold">

<?php echo $status; ?>

</span>

</td>

<td class="text-center p-4">

<?php

if($status=="Pending"){

?>

<a
href="dashboard.php?id=<?php echo $medicine["schedule_id"]; ?>&status=Taken"
class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm">

Taken

</a>

<a
href="dashboard.php?id=<?php echo $medicine["schedule_id"]; ?>&status=Skipped"
class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm ml-2">

Skip

</a>

<a
href="dashboard.php?id=<?php echo $medicine["schedule_id"]; ?>&status=Snoozed"
class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded text-sm ml-2">

Snooze

</a>

<?php

}else{

?>

<span class="text-gray-500 italic">

Already Marked

</span>

<?php

}

?>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

<?php

}

?>

</div>

<?php

/* =====================================
   UPCOMING MEDICINES
===================================== */

//$currentTime = date("H:i:s");

$sql = "

SELECT

medicine_name,

dosage,

dose_time

FROM schedules

JOIN medicines

ON schedules.medicine_id = medicines.id

WHERE medicines.patient_id = ?

AND medicines.is_active = 1

AND TIME(dose_time) > CURTIME()

ORDER BY dose_time

LIMIT 5

";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
"i",
$patient_id
);

$stmt->execute();

$upcoming = $stmt->get_result();

?>

<div class="bg-white rounded-xl shadow-lg mt-10 p-6">

<h2 class="text-3xl font-bold mb-6">

Upcoming Medicines ⏰

</h2>

<?php

if($upcoming->num_rows==0){

?>

<div class="text-center text-gray-500 py-10">

🎉

You're all caught up for today!

Take some rest and stay healthy.

</div>

<?php

}else{

while($next=$upcoming->fetch_assoc()){

?>

<div class="flex justify-between items-center border-b py-4">

<div>

<p class="font-semibold">

💊

<?php

echo htmlspecialchars(

$next["medicine_name"]

);

?>

</p>

<p class="text-gray-500">

<?php

echo htmlspecialchars(

$next["dosage"]

);

?>

</p>

</div>

<div class="text-blue-600 font-bold text-lg">

<?php

echo date(

"h:i A",

strtotime(

$next["dose_time"]

)

);

?>

</div>

</div>

<?php

}

}

?>

</div>

<!-- QUICK ACTIONS -->

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">

    <a
    href="add_medicine.php"
    class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-lg p-6 text-center transition">

        <div class="text-5xl mb-3">
            💊
        </div>

        <h3 class="text-xl font-bold">

            Add Medicine

        </h3>

        <p class="mt-2 text-blue-100">

            Add a new prescription.

        </p>

    </a>

    <a
    href="medicines.php"
    class="bg-green-600 hover:bg-green-700 text-white rounded-xl shadow-lg p-6 text-center transition">

        <div class="text-5xl mb-3">

            📋

        </div>

        <h3 class="text-xl font-bold">

            Manage Medicines

        </h3>

        <p class="mt-2 text-green-100">

            Edit or remove medicines.

        </p>

    </a>

    <a
    href="today_medicines.php"
    class="bg-purple-600 hover:bg-purple-700 text-white rounded-xl shadow-lg p-6 text-center transition">

        <div class="text-5xl mb-3">

            ⏰

        </div>

        <h3 class="text-xl font-bold">

            Today's Medicines

        </h3>

        <p class="mt-2 text-purple-100">

            View today's schedule.

        </p>

    </a>

</div>

<!-- DASHBOARD FOOTER -->

<div class="mt-12 bg-blue-50 border border-blue-200 rounded-xl p-6">

    <h3 class="text-xl font-bold text-blue-700">

        Today's Health Tip 💙

    </h3>

    <p class="text-gray-700 mt-3">

        <?php echo $dailyTip; ?>
    </p>

</div>

</div>

<?php

include "../includes/footer.php";

?>