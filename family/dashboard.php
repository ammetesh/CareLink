<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$family_id = $_SESSION["user_id"];

/* ===============================
   GET LINKED PATIENT
================================ */

$stmt = $conn->prepare("
SELECT patient_id
FROM family_links
WHERE family_id = ?
");

$stmt->bind_param("i", $family_id);
$stmt->execute();

$link = $stmt->get_result()->fetch_assoc();

if(!$link){

    header("Location: link_patient.php");
    exit();

}

$patient_id = $link["patient_id"];
/* ===============================
   PATIENT NAME
================================ */

$patient = $conn->query("
SELECT full_name
FROM users
WHERE id = $patient_id
")->fetch_assoc();

/* ===============================
   TOTAL ACTIVE MEDICINES
================================ */

$total = $conn->query("
SELECT COUNT(*) AS total
FROM medicines
WHERE patient_id = $patient_id
AND is_active = 1
")->fetch_assoc();

/* ===============================
   TAKEN TODAY
================================ */

$taken = $conn->query("
SELECT COUNT(*) AS taken

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id = schedules.id

JOIN medicines
ON schedules.medicine_id = medicines.id

WHERE medicines.patient_id = $patient_id
AND dose_logs.status='Taken'
AND dose_logs.log_date = CURDATE()
")->fetch_assoc();

/* ===============================
   SKIPPED TODAY
================================ */

$skipped = $conn->query("

SELECT COUNT(*) AS skipped

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id=schedules.id

JOIN medicines
ON schedules.medicine_id=medicines.id

WHERE medicines.patient_id=$patient_id

AND dose_logs.log_date=CURDATE()

AND dose_logs.status='Skipped'

")->fetch_assoc();

/* ===============================
   SNOOZED TODAY
================================ */

$snoozed = $conn->query("

SELECT COUNT(*) AS snoozed

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id=schedules.id

JOIN medicines
ON schedules.medicine_id=medicines.id

WHERE medicines.patient_id=$patient_id

AND dose_logs.log_date=CURDATE()

AND dose_logs.status='Snoozed'

")->fetch_assoc();

/* ===============================
   TOTAL SCHEDULES
================================ */

$schedules = $conn->query("

SELECT COUNT(*) AS total

FROM schedules

JOIN medicines

ON schedules.medicine_id=medicines.id

WHERE medicines.patient_id=$patient_id

AND medicines.is_active=1

")->fetch_assoc();

$pending =

$schedules["total"]

-

(

$taken["taken"]

+

$skipped["skipped"]

+

$snoozed["snoozed"]

);

if($pending<0){

$pending=0;

}

/* ===============================
   SMART ALERTS
================================ */

$alerts = $conn->query("
SELECT
    medicines.medicine_name,
    COUNT(*) AS missed_count

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id = schedules.id

JOIN medicines
ON schedules.medicine_id = medicines.id

WHERE medicines.patient_id = $patient_id
AND dose_logs.status='Skipped'

GROUP BY medicines.id

HAVING missed_count >= 3
");

/* ===============================
   RECENT ACTIVITY
================================ */

$activity = $conn->query("
SELECT
    medicines.medicine_name,
    schedules.dose_time,
    dose_logs.status

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id = schedules.id

JOIN medicines
ON schedules.medicine_id = medicines.id

WHERE medicines.patient_id = $patient_id

ORDER BY dose_logs.taken_time DESC

LIMIT 10
");

?>

<div class="max-w-6xl mx-auto p-8">

    <h1 class="text-4xl font-bold">
        Family Dashboard
    </h1>

    <p class="text-gray-500 mt-2">
        Monitoring Patient Medication
    </p>

    <!-- PATIENT CARD -->

    <div class="bg-white shadow rounded-xl p-6 mt-8">

        <h2 class="text-2xl font-bold">
            👤 <?php echo htmlspecialchars($patient["full_name"]); ?>
        </h2>

    </div>

    <!-- SMART ALERT -->

    <?php if($alerts->num_rows > 0){ ?>

        <div class="bg-red-100 border-l-8 border-red-600 p-6 rounded-xl mt-8">

            <h2 class="text-2xl font-bold text-red-700 mb-4">

                🚨 Smart Alerts

            </h2>

            <?php while($alert = $alerts->fetch_assoc()){ ?>

                <p class="mb-2">

                    <strong>

                        <?php echo htmlspecialchars($alert["medicine_name"]); ?>

                    </strong>

                    has been skipped

                    <strong>

                        <?php echo $alert["missed_count"]; ?>

                    </strong>

                    times.

                </p>

            <?php } ?>

        </div>

    <?php } ?>

    <!-- STATISTICS -->

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 mt-8">

        <div class="bg-white shadow rounded-xl p-6">

            <h3 class="text-gray-500">

                Total Medicines

            </h3>

            <p class="text-5xl font-bold mt-3">

                <?php echo $total["total"]; ?>

            </p>

        </div>

        <div class="bg-white shadow rounded-xl p-6">

            <h3 class="text-gray-500">

                Taken Today

            </h3>

            <p class="text-5xl font-bold text-green-600 mt-3">

                <?php echo $taken["taken"]; ?>

            </p>

        </div>

        <div class="bg-white shadow rounded-xl p-6">

<h3 class="text-gray-500">

Skipped

</h3>

<p class="text-5xl text-red-600 font-bold mt-3">

<?php echo $skipped["skipped"]; ?>

</p>

</div>

<div class="bg-white shadow rounded-xl p-6">

<h3 class="text-gray-500">

Snoozed

</h3>

<p class="text-5xl text-yellow-500 font-bold mt-3">

<?php echo $snoozed["snoozed"]; ?>

</p>

</div>

<div class="bg-white shadow rounded-xl p-6">

<h3 class="text-gray-500">

Pending

</h3>

<p class="text-5xl text-blue-600 font-bold mt-3">

<?php echo $pending; ?>

</p>

</div>

        <div class="bg-white shadow rounded-xl p-6">

            <h3 class="text-gray-500">

                Adherence

            </h3>

            <p class="text-5xl font-bold text-blue-600 mt-3">

                <?php

                if($total["total"] == 0){

                    echo "0%";

                }else{

                    echo round(($taken["taken"]/$total["total"])*100)."%";

                }

                ?>

            </p>

        </div>

    </div>

    <!-- RECENT ACTIVITY -->

    <div class="bg-white shadow rounded-xl p-6 mt-8">

        <h2 class="text-2xl font-bold mb-6">

            Recent Activity

        </h2>

        <?php

        if($activity->num_rows == 0){

            echo "<p class='text-gray-500'>No activity available.</p>";

        }

        while($row = $activity->fetch_assoc()){

            $icon = "⏳";
            $color = "text-gray-600";

            if($row["status"]=="Taken"){

                $icon="✔";
                $color="text-green-600";

            }

            if($row["status"]=="Skipped"){

                $icon="✖";
                $color="text-red-600";

            }

            if($row["status"]=="Snoozed"){

                $icon="⏰";
                $color="text-yellow-600";

            }

        ?>

        <div class="flex justify-between items-center border-b py-4">

            <div>

                <p class="font-semibold">

                    <?php echo $icon; ?>

                    <?php echo htmlspecialchars($row["medicine_name"]); ?>

                </p>

                <p class="text-gray-500">

                    <?php echo date("h:i A",strtotime($row["dose_time"])); ?>

                </p>

            </div>

            <div class="<?php echo $color; ?> font-bold">

                <?php echo htmlspecialchars($row["status"]); ?>

            </div>

        </div>

        <?php } ?>

    </div>

</div>

<?php include "../includes/footer.php"; ?>