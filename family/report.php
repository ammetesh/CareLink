<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$family_id = $_SESSION["user_id"];

$link = $conn->query("
SELECT patient_id
FROM family_links
WHERE family_id=$family_id
")->fetch_assoc();

$patient_id = $link["patient_id"];

$report = $conn->query("
SELECT
status,
COUNT(*) total

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id=schedules.id

JOIN medicines
ON schedules.medicine_id=medicines.id

WHERE medicines.patient_id=$patient_id

AND log_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)

GROUP BY status
");

?>

<div class="max-w-5xl mx-auto p-8">

<h1 class="text-4xl font-bold">

Weekly Report

</h1>

<div class="bg-white rounded-xl shadow mt-8">

<table class="w-full">

<tr>

<th class="p-4 text-left">

Status

</th>

<th>

Count

</th>

</tr>

<?php

while($row=$report->fetch_assoc()){

?>

<tr>

<td class="p-4">

<?php echo $row["status"]; ?>

</td>

<td>

<?php echo $row["total"]; ?>

</td>

</tr>

<?php

}

?>

</table>

</div>

</div>

<?php include "../includes/footer.php"; ?>