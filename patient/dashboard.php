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
/* =====================================
   MEDICATION INSIGHTS
===================================== */


// Completion Percentage

$completionPercentage = 0;

if($totalSchedules>0){

$completionPercentage=round(

(($takenToday+$skippedToday+$snoozedToday)

/$totalSchedules)*100

);

}


// Last Missed Medicine

$lastMissedMedicine="None";

$lastMissedDate="Excellent Progress!";


$sql="

SELECT

medicines.medicine_name,

dose_logs.log_date

FROM dose_logs

JOIN schedules
ON dose_logs.schedule_id=schedules.id

JOIN medicines
ON schedules.medicine_id=medicines.id

WHERE medicines.patient_id=?

AND dose_logs.status='Skipped'

ORDER BY dose_logs.log_date DESC

LIMIT 1

";


$stmt=$conn->prepare($sql);

$stmt->bind_param("i",$patient_id);

$stmt->execute();

$result=$stmt->get_result();


if($row=$result->fetch_assoc()){

$lastMissedMedicine=$row["medicine_name"];

$lastMissedDate=date(

"d M Y",

strtotime($row["log_date"])

);

}



// Progress Message (detailed message shown inside Medication Insights)


$insightMessage="Stay Consistent! Regular medication improves treatment effectiveness.";


if($adherence>=90){

$insightMessage="Excellent Progress! You've successfully taken all of today's medicines.";

}

elseif($adherence>=70){

$insightMessage="Great Work! You're maintaining good medication adherence.";

}

elseif($adherence>=50){

$insightMessage="Keep Going! You're making progress. Complete the remaining medicines on time.";

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
/*==================================
TODAY'S GOAL
==================================*/

$dailyGoal = "Take your medicines on time and stay healthy.";

if($adherence == 100){

    $dailyGoal = "Maintain your perfect medication adherence today.";

}

elseif($pendingToday > 0){

    $dailyGoal = "Complete the remaining medicines on time.";

}

elseif($skippedToday > 0){

    $dailyGoal = "Try not to skip medicines and stay consistent with your schedule.";

}

elseif($takenToday == 0){

    $dailyGoal = "Start your day by taking your scheduled medicines on time.";

}
/*==================================
DAILY STATUS MESSAGE
==================================*/

$statusTitle="Stay Consistent 💪";

$statusDescription="You still have medicines scheduled for today. Stay consistent and complete them on time.";


if($pendingToday==0 && $skippedToday==0 && $totalSchedules>0){

$statusTitle="Congratulations 🎉";

$statusDescription="You have completed all of today's scheduled medicines. Keep maintaining your healthy routine.";

}

elseif($pendingToday>0){

$statusTitle="Stay Consistent 💪";

$statusDescription="You still have medicines scheduled for today. Stay consistent and complete them on time.";

}

elseif($skippedToday>0){

$statusTitle="Attention Needed ⚠";

$statusDescription="Some medicines were skipped today. Please consult your healthcare provider if you frequently miss scheduled doses.";

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

/* =====================================
   UPCOMING MEDICINES
===================================== */

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

/* =====================================
   GREETING MESSAGE
===================================== */

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

/* =====================================
   DAILY HEALTH TIP
===================================== */

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

/* =====================================
   GENERAL HEALTH FACTS
   (Shown only to first-time users under "Did You Know?")
===================================== */

$healthFacts=array(

"Taking medicines at the same time daily helps your body maintain steady drug levels.",

"Missing doses often is one of the biggest reasons treatments take longer to work.",

"A consistent medication routine can significantly improve long-term health outcomes.",

"Setting reminders can reduce missed doses by a large margin.",

"Some medicines work best when taken with food, while others require an empty stomach.",

"Keeping a simple medication log helps doctors adjust your treatment more accurately.",

"Staying hydrated can help your body absorb certain medications more effectively."

);


$healthFact=$healthFacts[array_rand($healthFacts)];

/* =====================================
   FIRST TIME USER FLAG
   (Single source of truth - used everywhere below)
===================================== */

$isFirstTimeUser = ($totalMedicines == 0);

?>
<div class="max-w-7xl mx-auto p-8">

    <!-- =====================================
         GREETING (shown once, for every user)
    ====================================== -->
    <h1 class="text-4xl font-bold">
        <?php echo $greeting; ?>,
        <?php echo htmlspecialchars($_SESSION["user_name"]); ?> 👋
    </h1>

    <p class="text-gray-500 mt-2">
        Stay consistent with your medications and take care of your health today.
    </p>

    <?php if($isFirstTimeUser){ ?>

        <!-- =====================================
             CASE 1: FIRST TIME USER (ONBOARDING VIEW)
             Only the welcome card + health tip are shown.
             No stats, no medicine lists, no quick actions.
        ====================================== -->

        <?php
            // Feature list is data-driven so new items can be added
            // in one place without touching the markup below.
            $onboardingFeatures = [
                "Add & Manage Medicines",
                "Automatic Medicine Scheduling",
                "Track Daily Medication Progress",
                "Monitor Medication Adherence",
            ];
        ?>

        <div class="bg-blue-50 border border-blue-200 rounded-3xl shadow-xl mt-10 px-6 py-12 md:p-14 text-center">

            <!-- Icon -->
            <div class="w-16 h-16 rounded-full bg-blue-600 text-white text-3xl flex items-center justify-center mx-auto shadow-md">
                💊
            </div>

            <!-- Heading -->
            <h2 class="text-3xl md:text-4xl font-bold text-blue-700 mt-6">
                Welcome to CareLink 👋
            </h2>

            <!-- Subtext -->
            <p class="text-gray-600 mt-4 text-base md:text-lg max-w-2xl mx-auto leading-relaxed">
                We're here to help you manage your medicines effortlessly. Add your first medicine to begin tracking your medication schedule and stay consistent every day.
            </p>

            <!-- Feature List -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-10 max-w-2xl mx-auto">
                <?php foreach($onboardingFeatures as $feature){ ?>
                    <div class="flex items-center gap-3 bg-white border border-blue-100 rounded-xl px-4 py-3 text-left shadow-sm">
                        <span class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-sm font-bold">
                            ✓
                        </span>
                        <span class="text-gray-700 font-medium">
                            <?php echo htmlspecialchars($feature); ?>
                        </span>
                    </div>
                <?php } ?>
            </div>

            <!-- Call To Action -->
            <a href="add_medicine.php"
               class="inline-flex items-center gap-2 mt-10 bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-xl font-bold shadow-md transition">
                <span>➕</span>
                Add Your First Medicine
            </a>

        </div>

    <?php } else { ?>

        <!-- =====================================
             CASE 2: RETURNING USER (FULL DASHBOARD)
             Shown only after at least one medicine exists.
        ====================================== -->

        <!-- SUMMARY CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mt-10">

            <!-- Total Medicines (unique medicines added by the patient) -->
            <div class="bg-white shadow-lg rounded-xl p-6">
                <p class="text-gray-500">Total Medicines</p>
                <h2 class="text-5xl font-bold text-blue-600 mt-4">
                    <?php echo $totalMedicines; ?>
                </h2>
            </div>

            <!-- Today's Doses (total scheduled doses across all medicines today) -->
            <div class="bg-white shadow-lg rounded-xl p-6">
                <p class="text-gray-500">Today's Doses</p>
                <h2 class="text-5xl font-bold text-indigo-600 mt-4">
                    <?php echo $totalSchedules; ?>
                </h2>
            </div>

            <!-- Doses Taken Today -->
            <div class="bg-white shadow-lg rounded-xl p-6">
                <p class="text-gray-500">Doses Taken Today</p>
                <h2 class="text-5xl font-bold text-green-600 mt-4">
                    <?php echo $takenToday; ?>
                </h2>
            </div>

            <!-- Pending Doses -->
            <div class="bg-white shadow-lg rounded-xl p-6">
                <p class="text-gray-500">Pending Doses</p>
                <h2 class="text-5xl font-bold text-yellow-500 mt-4">
                    <?php echo $pendingToday; ?>
                </h2>
            </div>

            <!-- Adherence: (Doses Taken / Today's Doses) x 100, unchanged calculation -->
            <div class="bg-white shadow-lg rounded-xl p-6">
                <p class="text-gray-500">Adherence</p>
                <h2 class="text-5xl font-bold text-purple-600 mt-4">
                    <?php echo $adherence; ?>%
                </h2>
                <div class="w-full bg-gray-200 rounded-full h-3 mt-4">
                    <div class="bg-purple-600 h-3 rounded-full" style="width:<?php echo $adherence; ?>%;"></div>
                </div>
                <p class="text-sm text-gray-600 mt-3 font-semibold">
                    <?php echo $progressMessage; ?>
                </p>
            </div>

        </div>

        <!-- =====================================
             MEDICATION INSIGHTS
             Shown only when totalMedicines > 0
        ====================================== -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-10">

            <!-- PROGRESS STATUS -->
            <div class="bg-green-50 rounded-xl p-6">
                <h3 class="text-xl font-bold">Progress Status</h3>
                <p class="text-gray-600 mt-3">
                    <?php echo $insightMessage; ?>
                </p>
            </div>

            <!-- COMPLETION RATE -->
            <div class="bg-blue-50 rounded-xl p-6">
                <h3 class="text-xl font-bold">Completion Rate</h3>
                <p class="text-3xl font-bold text-blue-600 mt-3">
                    <?php echo ($takenToday + $skippedToday + $snoozedToday); ?> / <?php echo $totalSchedules; ?>
                </p>
                <p class="text-gray-500 mt-2">
                    <?php echo $completionPercentage; ?>% Completed
                </p>
            </div>

            <!-- LAST MISSED DOSE -->
            <div class="bg-red-50 rounded-xl p-6">
                <h3 class="text-xl font-bold">Last Missed Dose</h3>
                <p class="font-semibold mt-3">
                    <?php echo htmlspecialchars($lastMissedMedicine); ?>
                </p>
                <p class="text-gray-500">
                    <?php echo $lastMissedDate; ?>
                </p>
            </div>

            <!-- TODAY'S GOAL -->
            <div class="bg-purple-50 rounded-xl p-6">
                <h3 class="text-xl font-bold">Today's Goal</h3>
                <p class="text-gray-600 mt-3">
                    <?php echo $dailyGoal; ?>
                </p>
            </div>

        </div>

        <!-- =====================================
             TODAY'S MEDICINES
        ====================================== -->
        <div class="bg-white rounded-xl shadow-lg mt-10 p-6">

            <h2 class="text-3xl font-bold mb-6">Today's Medicines</h2>

            <?php if($todayMedicines->num_rows == 0){ ?>

                <div class="text-center py-10">
                    <h3 class="text-2xl font-bold">No Medicines Scheduled Today</h3>
                    <p class="text-gray-500 mt-3">You're all caught up for now.</p>
                </div>

            <?php } else { ?>

                <table class="w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left p-4">Medicine</th>
                            <th class="text-left p-4">Time</th>
                            <th class="text-left p-4">Dosage</th>
                            <th class="text-left p-4">Status</th>
                            <th class="text-center p-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($medicine = $todayMedicines->fetch_assoc()){

                            $status = $medicine["status"] ?? "Pending";

                            if(empty($status)){
                                $status = "Pending";
                            }

                            $statusColor = "bg-gray-200 text-gray-700";

                            if($status == "Taken"){
                                $statusColor = "bg-green-100 text-green-700";
                            }

                            if($status == "Skipped"){
                                $statusColor = "bg-red-100 text-red-700";
                            }

                            if($status == "Snoozed"){
                                $statusColor = "bg-yellow-100 text-yellow-700";
                            }
                        ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4">
                                    💊
                                    <strong><?php echo htmlspecialchars($medicine["medicine_name"]); ?></strong>
                                    <br>
                                    <span class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($medicine["meal_timing"]); ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <?php echo date("h:i A", strtotime($medicine["dose_time"])); ?>
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
                                    <?php if($status == "Pending"){ ?>
                                        <a href="mark.php?id=<?php echo $medicine["schedule_id"]; ?>&status=Taken"
                                           class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm">
                                            Taken
                                        </a>
                                        <a href="mark.php?id=<?php echo $medicine["schedule_id"]; ?>&status=Skipped"
                                           class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded text-sm ml-2">
                                            Skip
                                        </a>
                                        <a href="mark.php?id=<?php echo $medicine["schedule_id"]; ?>&status=Snoozed"
                                           class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded text-sm ml-2">
                                            Snooze
                                        </a>
                                    <?php } else { ?>
                                        <span class="text-gray-500 italic">Already Marked</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

            <?php } ?>

        </div>

        <!-- =====================================
             UPCOMING MEDICINES
        ====================================== -->
        <div class="bg-white rounded-xl shadow-lg mt-10 p-6">

            <h2 class="text-3xl font-bold mb-6">Upcoming Medicines ⏰</h2>

            <?php if($upcoming->num_rows == 0){ ?>

                <div class="text-center text-gray-500 py-10">
                    <?php if($pendingToday == 0 && $totalSchedules > 0){ ?>
                        🎉 You've successfully completed today's medication schedule.
                    <?php } else { ?>
                        No upcoming medicines are scheduled for the rest of the day.
                    <?php } ?>
                </div>

            <?php } else {
                while($next = $upcoming->fetch_assoc()){
            ?>
                <div class="flex justify-between items-center border-b py-4">
                    <div>
                        <p class="font-semibold">
                            💊 <?php echo htmlspecialchars($next["medicine_name"]); ?>
                        </p>
                        <p class="text-gray-500">
                            <?php echo htmlspecialchars($next["dosage"]); ?>
                        </p>
                    </div>
                    <div class="text-blue-600 font-bold text-lg">
                        <?php echo date("h:i A", strtotime($next["dose_time"])); ?>
                    </div>
                </div>
            <?php
                }
            } ?>

        </div>

        <!-- =====================================
             DAILY STATUS
             (Congratulations / Great Going / Needs Attention)
        ====================================== -->
        <div class="bg-green-50 rounded-xl shadow-lg mt-10 p-6">
            <h2 class="text-3xl font-bold">
                <?php echo $statusTitle; ?>
            </h2>
            <p class="text-gray-700 mt-4">
                <?php echo $statusDescription; ?>
            </p>
        </div>

        <!-- =====================================
             QUICK ACTIONS
        ====================================== -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">

            <a href="add_medicine.php"
               class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-lg p-6 text-center transition">
                <div class="text-5xl mb-3">💊</div>
                <h3 class="text-xl font-bold">Add Medicine</h3>
                <p class="mt-2 text-blue-100">Add a new prescription.</p>
            </a>

            <a href="medicines.php"
               class="bg-green-600 hover:bg-green-700 text-white rounded-xl shadow-lg p-6 text-center transition">
                <div class="text-5xl mb-3">📋</div>
                <h3 class="text-xl font-bold">Manage Medicines</h3>
                <p class="mt-2 text-green-100">Edit or remove medicines.</p>
            </a>

            <a href="today_medicines.php"
               class="bg-purple-600 hover:bg-purple-700 text-white rounded-xl shadow-lg p-6 text-center transition">
                <div class="text-5xl mb-3">⏰</div>
                <h3 class="text-xl font-bold">Today's Medicines</h3>
                <p class="mt-2 text-purple-100">View today's schedule.</p>
            </a>

        </div>

    <?php } ?>

    <!-- =====================================
         HEALTH SECTION (shown once, for every user)
         First-time users see a general "Did You Know?" fact.
         Returning users see the regular Daily Health Tip.
    ====================================== -->
    <div class="mt-12 bg-blue-50 border border-blue-200 rounded-xl p-6">
        <?php if($isFirstTimeUser){ ?>
            <h3 class="text-xl font-bold text-blue-700">Did You Know? 💡</h3>
            <p class="text-gray-700 mt-3">
                <?php echo $healthFact; ?>
            </p>
        <?php } else { ?>
            <h3 class="text-xl font-bold text-blue-700">Today's Health Tip 💙</h3>
            <p class="text-gray-700 mt-3">
                <?php echo $dailyTip; ?>
            </p>
        <?php } ?>
    </div>

</div>

<?php

include "../includes/footer.php";

?>