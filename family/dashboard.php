<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$family_id = (int) $_SESSION["user_id"];

/* =====================================
   GET LINKED PATIENT
   (Prepared statement - no change to logic)
===================================== */

$stmt = $conn->prepare("
    SELECT patient_id
    FROM family_links
    WHERE family_id = ?
");
$stmt->bind_param("i", $family_id);
$stmt->execute();
$link = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$link) {
    header("Location: link_patient.php");
    exit();
}

$patient_id = (int) $link["patient_id"];

/* =====================================
   PATIENT NAME
===================================== */

$stmt = $conn->prepare("
    SELECT full_name
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

$patientName = $patient["full_name"] ?? "Patient";

/* =====================================
   TOTAL ACTIVE MEDICINES (unique medicines)
===================================== */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM medicines
    WHERE patient_id = ?
    AND is_active = 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalMedicines = (int) $total["total"];

/* =====================================
   TODAY'S TOTAL SCHEDULED DOSES
===================================== */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM schedules
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    AND medicines.is_active = 1
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$schedules = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalSchedules = (int) $schedules["total"];

/* =====================================
   TAKEN TODAY
===================================== */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS taken
    FROM dose_logs
    JOIN schedules ON dose_logs.schedule_id = schedules.id
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    AND dose_logs.status = 'Taken'
    AND dose_logs.log_date = CURDATE()
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$taken = $stmt->get_result()->fetch_assoc();
$stmt->close();

$takenToday = (int) $taken["taken"];

/* =====================================
   SKIPPED TODAY
===================================== */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS skipped
    FROM dose_logs
    JOIN schedules ON dose_logs.schedule_id = schedules.id
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    AND dose_logs.log_date = CURDATE()
    AND dose_logs.status = 'Skipped'
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$skipped = $stmt->get_result()->fetch_assoc();
$stmt->close();

$skippedToday = (int) $skipped["skipped"];

/* =====================================
   SNOOZED TODAY
===================================== */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS snoozed
    FROM dose_logs
    JOIN schedules ON dose_logs.schedule_id = schedules.id
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    AND dose_logs.log_date = CURDATE()
    AND dose_logs.status = 'Snoozed'
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$snoozed = $stmt->get_result()->fetch_assoc();
$stmt->close();

$snoozedToday = (int) $snoozed["snoozed"];

/* =====================================
   PENDING TODAY
===================================== */

$pendingToday = $totalSchedules - ($takenToday + $skippedToday + $snoozedToday);

if ($pendingToday < 0) {
    $pendingToday = 0;
}

/* =====================================
   ADHERENCE (Taken / Today's Scheduled Doses)
   NOTE: previously calculated against total medicines,
   corrected here to use total scheduled doses so the
   percentage matches the same doses being counted above.
===================================== */

$adherence = 0;

if ($totalSchedules > 0) {
    $adherence = round(($takenToday / $totalSchedules) * 100);
}

$adherenceMessage = "Needs Attention";

if ($adherence >= 90) {
    $adherenceMessage = "Excellent Progress";
} elseif ($adherence >= 70) {
    $adherenceMessage = "Good Progress";
} elseif ($adherence >= 50) {
    $adherenceMessage = "Keep Going";
}

/* =====================================
   SMART ALERTS
   (Medicines skipped 3+ times overall)
===================================== */

$stmt = $conn->prepare("
    SELECT
        medicines.medicine_name,
        COUNT(*) AS missed_count
    FROM dose_logs
    JOIN schedules ON dose_logs.schedule_id = schedules.id
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    AND dose_logs.status = 'Skipped'
    GROUP BY medicines.id
    HAVING missed_count >= 3
    ORDER BY missed_count DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$alerts = $stmt->get_result();

/* =====================================
   WEEKLY ADHERENCE SUMMARY (last 7 days)
===================================== */

$stmt = $conn->prepare("
    SELECT
        dose_logs.log_date,
        SUM(dose_logs.status = 'Taken') AS taken_count
    FROM dose_logs
    JOIN schedules ON dose_logs.schedule_id = schedules.id
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    AND dose_logs.log_date >= CURDATE() - INTERVAL 6 DAY
    GROUP BY dose_logs.log_date
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$weeklyResult = $stmt->get_result();

$weeklyTakenByDate = [];
while ($row = $weeklyResult->fetch_assoc()) {
    $weeklyTakenByDate[$row["log_date"]] = (int) $row["taken_count"];
}
$stmt->close();

// Build a fixed 7-day timeline (oldest to newest) so the chart
// always shows Mon-Sun style bars, even for days with no logs.
$weeklySummary = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date("Y-m-d", strtotime("-$i day"));
    $takenCount = $weeklyTakenByDate[$date] ?? 0;
    $percent = $totalSchedules > 0 ? round(($takenCount / $totalSchedules) * 100) : 0;
    $weeklySummary[] = [
        "label" => date("D", strtotime($date)),
        "date" => date("d M", strtotime($date)),
        "percent" => min($percent, 100),
    ];
}

/* =====================================
   MISSED MEDICINE INSIGHTS (last 7 days)
===================================== */

$stmt = $conn->prepare("
    SELECT
        medicines.medicine_name,
        COUNT(*) AS missed_count
    FROM dose_logs
    JOIN schedules ON dose_logs.schedule_id = schedules.id
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    AND dose_logs.status = 'Skipped'
    AND dose_logs.log_date >= CURDATE() - INTERVAL 6 DAY
    GROUP BY medicines.id
    ORDER BY missed_count DESC
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$weeklyMissed = $stmt->get_result();

/* =====================================
   TODAY'S MEDICINE SCHEDULE
===================================== */

$stmt = $conn->prepare("
    SELECT
        schedules.dose_time,
        medicines.medicine_name,
        medicines.dosage,
        medicines.meal_timing,
        dose_logs.status
    FROM schedules
    JOIN medicines ON schedules.medicine_id = medicines.id
    LEFT JOIN dose_logs
        ON schedules.id = dose_logs.schedule_id
        AND dose_logs.log_date = CURDATE()
    WHERE medicines.patient_id = ?
    AND medicines.is_active = 1
    ORDER BY schedules.dose_time
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$todaySchedule = $stmt->get_result();

/* =====================================
   UPCOMING DOSES (later today)
===================================== */

$stmt = $conn->prepare("
    SELECT
        medicines.medicine_name,
        medicines.dosage,
        schedules.dose_time
    FROM schedules
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    AND medicines.is_active = 1
    AND TIME(schedules.dose_time) > CURTIME()
    ORDER BY schedules.dose_time
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$upcoming = $stmt->get_result();

/* =====================================
   RECENT ACTIVITY
===================================== */

$stmt = $conn->prepare("
    SELECT
        medicines.medicine_name,
        schedules.dose_time,
        dose_logs.status
    FROM dose_logs
    JOIN schedules ON dose_logs.schedule_id = schedules.id
    JOIN medicines ON schedules.medicine_id = medicines.id
    WHERE medicines.patient_id = ?
    ORDER BY dose_logs.taken_time DESC
    LIMIT 10
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$activity = $stmt->get_result();

/* =====================================
   UI HELPERS (presentation only)
   - $alertCount: reused so the hero banner and the
     Smart Alerts section both read from the same count
     without moving the result-set cursor.
   - status_icon(): centralises the Taken/Skipped/Snoozed/
     Pending icon markup so it isn't duplicated across
     the schedule table and the activity feed.
===================================== */

$alertCount = $alerts->num_rows;

function status_icon($status) {

    $icons = [
        "Taken" => [
            "bg" => "bg-emerald-100",
            "text" => "text-emerald-600",
            "path" => "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z",
        ],
        "Skipped" => [
            "bg" => "bg-rose-100",
            "text" => "text-rose-600",
            "path" => "M15 9l-6 6M9 9l6 6m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
        ],
        "Snoozed" => [
            "bg" => "bg-amber-100",
            "text" => "text-amber-600",
            "path" => "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
        ],
        "Pending" => [
            "bg" => "bg-slate-200",
            "text" => "text-slate-500",
            "path" => "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
        ],
    ];

    $icon = $icons[$status] ?? $icons["Pending"];

    return '<span class="inline-flex items-center justify-center w-9 h-9 rounded-full ' . $icon["bg"] . ' ' . $icon["text"] . ' flex-shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="' . $icon["path"] . '" />
        </svg>
    </span>';
}

?>
<div class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto p-4 md:p-8">

    <!-- =====================================
         HEADER
    ====================================== -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
        <div>
            <h1 class="text-2xl md:text-4xl font-bold text-slate-800 tracking-tight">
                Family Dashboard
            </h1>
            <p class="text-slate-500 mt-1 text-sm md:text-base">
                Monitoring medication for a loved one, at a glance.
            </p>
        </div>
        <p class="text-xs md:text-sm text-slate-400">
            <?php echo date("l, d M Y"); ?>
        </p>
    </div>

    <?php if ($totalMedicines > 0) { ?>

    <!-- =====================================
         PATIENT HEALTH OVERVIEW
         (Only shown once the patient has at least
         one active medicine - otherwise adherence/
         streak numbers would be meaningless zeros.)
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-6 md:p-8 mt-6">
        <div class="flex flex-col md:flex-row md:items-center gap-6">

            <div class="flex items-center gap-4 flex-1">
                <div class="w-14 h-14 md:w-16 md:h-16 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white text-xl md:text-2xl font-bold flex items-center justify-center shadow-md flex-shrink-0">
                    <?php echo strtoupper(substr($patientName, 0, 1)); ?>
                </div>
                <div>
                    <h2 class="text-xl md:text-2xl font-bold text-slate-800">
                        <?php echo htmlspecialchars($patientName); ?>
                    </h2>
                    <p class="text-slate-500 text-sm mt-0.5">
                        <?php echo $totalMedicines; ?> active medicine<?php echo $totalMedicines == 1 ? "" : "s"; ?> · <?php echo $totalSchedules; ?> doses/day
                    </p>
                    <a href="medicine_report.php"
                       class="inline-flex items-center gap-1.5 mt-3 text-sm font-semibold text-blue-600 hover:text-blue-700">
                        📄 View Medicine Report
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Adherence Ring (hero metric) -->
            <?php
                $ringRadius = 42;
                $ringCircumference = 2 * M_PI * $ringRadius;
                $ringOffset = $ringCircumference * (1 - min($adherence, 100) / 100);
                $ringColor = $adherence >= 90 ? "#059669" : ($adherence >= 70 ? "#2563eb" : ($adherence >= 50 ? "#d97706" : "#dc2626"));
            ?>
            <div class="flex items-center gap-4 bg-slate-50 rounded-xl p-4 md:p-5">
                <div class="relative w-20 h-20 flex-shrink-0">
                    <svg class="w-20 h-20 -rotate-90" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="<?php echo $ringRadius; ?>" fill="none" stroke="#e2e8f0" stroke-width="9"/>
                        <circle cx="50" cy="50" r="<?php echo $ringRadius; ?>" fill="none" stroke="<?php echo $ringColor; ?>" stroke-width="9"
                            stroke-linecap="round"
                            stroke-dasharray="<?php echo $ringCircumference; ?>"
                            stroke-dashoffset="<?php echo $ringOffset; ?>"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-lg font-bold text-slate-800"><?php echo $adherence; ?>%</span>
                    </div>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-semibold">Today's Adherence</p>
                    <p class="text-sm font-bold mt-1" style="color: <?php echo $ringColor; ?>;"><?php echo $adherenceMessage; ?></p>
                    <p class="text-xs text-slate-500 mt-0.5"><?php echo $takenToday; ?> of <?php echo $totalSchedules; ?> doses taken</p>
                </div>
            </div>

        </div>
    </div>

    <!-- =====================================
         SMART ALERT HERO BANNER
         (Only the count is checked here; the full
         list still renders in the Smart Alerts section)
    ====================================== -->
    <?php if ($alertCount > 0) { ?>
        <div class="mt-6 bg-rose-50 border border-rose-200 rounded-2xl p-5 flex items-center gap-4">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-rose-100 text-rose-600 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 4.874c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </span>
            <div>
                <p class="font-bold text-rose-700">Attention Needed</p>
                <p class="text-sm text-rose-600 mt-0.5">
                    <?php echo $alertCount; ?> medicine<?php echo $alertCount == 1 ? " has" : "s have"; ?> been skipped 3 or more times. See Smart Alerts below.
                </p>
            </div>
        </div>
    <?php } ?>

    <!-- =====================================
         KEY STATS (Pending highlighted as it needs action)
    ====================================== -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mt-6">

        <div class="bg-white border border-slate-100 shadow-sm rounded-xl p-4">
            <div class="flex items-center justify-between">
                <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Medicines</p>
                <span class="text-lg">💊</span>
            </div>
            <p class="text-3xl font-bold text-slate-800 mt-2"><?php echo $totalMedicines; ?></p>
        </div>

        <div class="bg-white border border-slate-100 shadow-sm rounded-xl p-4">
            <div class="flex items-center justify-between">
                <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Today's Doses</p>
                <span class="text-lg">📋</span>
            </div>
            <p class="text-3xl font-bold text-slate-800 mt-2"><?php echo $totalSchedules; ?></p>
        </div>

        <div class="bg-white border border-slate-100 shadow-sm rounded-xl p-4">
            <div class="flex items-center justify-between">
                <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Taken</p>
                <?php echo status_icon("Taken"); ?>
            </div>
            <p class="text-3xl font-bold text-emerald-600 mt-2"><?php echo $takenToday; ?></p>
        </div>

        <div class="bg-white border border-slate-100 shadow-sm rounded-xl p-4">
            <div class="flex items-center justify-between">
                <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Skipped</p>
                <?php echo status_icon("Skipped"); ?>
            </div>
            <p class="text-3xl font-bold text-rose-600 mt-2"><?php echo $skippedToday; ?></p>
        </div>

        <div class="bg-white border border-slate-100 shadow-sm rounded-xl p-4">
            <div class="flex items-center justify-between">
                <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Snoozed</p>
                <?php echo status_icon("Snoozed"); ?>
            </div>
            <p class="text-3xl font-bold text-amber-600 mt-2"><?php echo $snoozedToday; ?></p>
        </div>

        <!-- Pending: intentionally styled as an accent card so it stands out -->
        <div class="bg-orange-50 border-2 border-orange-200 shadow-sm rounded-xl p-4 col-span-2 md:col-span-1">
            <div class="flex items-center justify-between">
                <p class="text-orange-500 text-xs font-bold uppercase tracking-wide">Pending</p>
                <?php echo status_icon("Pending"); ?>
            </div>
            <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo $pendingToday; ?></p>
        </div>

    </div>

    <!-- =====================================
         TODAY'S MEDICINE SCHEDULE
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-5 md:p-6 mt-6">

        <h2 class="text-lg md:text-xl font-bold text-slate-800 mb-1">
            Today's Medicine Schedule
        </h2>
        <p class="text-sm text-slate-400 mb-4">Every dose scheduled for today and its current status.</p>

        <?php if ($todaySchedule->num_rows == 0) { ?>

            <p class="text-slate-500 text-center py-8">
                No medicines are scheduled for today.
            </p>

        <?php } else { ?>

            <div class="divide-y divide-slate-100">
                <?php while ($dose = $todaySchedule->fetch_assoc()) {

                    $status = $dose["status"] ?? "Pending";
                    if (empty($status)) {
                        $status = "Pending";
                    }

                    $badgeColor = "bg-slate-100 text-slate-600";
                    if ($status == "Taken") {
                        $badgeColor = "bg-emerald-50 text-emerald-700";
                    }
                    if ($status == "Skipped") {
                        $badgeColor = "bg-rose-50 text-rose-700";
                    }
                    if ($status == "Snoozed") {
                        $badgeColor = "bg-amber-50 text-amber-700";
                    }
                ?>
                    <div class="flex items-center gap-4 py-4">
                        <?php echo status_icon($status); ?>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-800 truncate">
                                <?php echo htmlspecialchars($dose["medicine_name"]); ?>
                            </p>
                            <p class="text-sm text-slate-500 truncate">
                                <?php echo htmlspecialchars($dose["dosage"]); ?> · <?php echo htmlspecialchars($dose["meal_timing"]); ?> · <?php echo date("h:i A", strtotime($dose["dose_time"])); ?>
                            </p>
                        </div>
                        <span class="<?php echo $badgeColor; ?> px-3 py-1 rounded-full text-xs font-semibold flex-shrink-0">
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </div>
                <?php } ?>
            </div>

        <?php } ?>

    </div>

    <!-- =====================================
         UPCOMING DOSES
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-5 md:p-6 mt-6">

        <h2 class="text-lg md:text-xl font-bold text-slate-800 mb-4">
            Upcoming Doses ⏰
        </h2>

        <?php if ($upcoming->num_rows == 0) { ?>

            <p class="text-slate-500 text-center py-8">
                No upcoming doses are scheduled for the rest of the day.
            </p>

        <?php } else { ?>

            <div class="divide-y divide-slate-100">
                <?php while ($next = $upcoming->fetch_assoc()) { ?>
                    <div class="flex justify-between items-center py-4">
                        <div>
                            <p class="font-semibold text-slate-800">
                                💊 <?php echo htmlspecialchars($next["medicine_name"]); ?>
                            </p>
                            <p class="text-sm text-slate-500">
                                <?php echo htmlspecialchars($next["dosage"]); ?>
                            </p>
                        </div>
                        <div class="text-blue-600 font-bold bg-blue-50 px-3 py-1.5 rounded-lg text-sm">
                            <?php echo date("h:i A", strtotime($next["dose_time"])); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

        <?php } ?>

    </div>

    <!-- =====================================
         WEEKLY ADHERENCE SUMMARY
         Bars are color-coded by the same thresholds
         used for the adherence ring above.
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-5 md:p-6 mt-6">

        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg md:text-xl font-bold text-slate-800">
                Weekly Adherence Summary
            </h2>
            <?php
                $weeklyAverage = round(array_sum(array_column($weeklySummary, "percent")) / count($weeklySummary));
            ?>
            <span class="text-sm font-semibold text-slate-500">Avg: <?php echo $weeklyAverage; ?>%</span>
        </div>

        <div class="grid grid-cols-7 gap-2 md:gap-3 items-end" style="height: 150px;">
            <?php foreach ($weeklySummary as $day) {
                $barColor = $day["percent"] >= 90 ? "bg-emerald-500" : ($day["percent"] >= 70 ? "bg-blue-500" : ($day["percent"] >= 50 ? "bg-amber-500" : "bg-rose-400"));
            ?>
                <div class="flex flex-col items-center justify-end h-full group">
                    <span class="text-[10px] md:text-xs font-semibold text-slate-600 mb-1"><?php echo $day["percent"]; ?>%</span>
                    <div class="w-full bg-slate-100 rounded-t-md flex items-end overflow-hidden" style="height: 90px;">
                        <div class="w-full <?php echo $barColor; ?> rounded-t-md transition-all" style="height: <?php echo max($day["percent"], 3); ?>%;"></div>
                    </div>
                    <span class="text-[10px] md:text-xs text-slate-500 mt-2 font-medium"><?php echo $day["label"]; ?></span>
                    <span class="text-[9px] text-slate-400 hidden md:block"><?php echo $day["date"]; ?></span>
                </div>
            <?php } ?>
        </div>

    </div>

    <!-- =====================================
         MISSED MEDICINE INSIGHTS (last 7 days)
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-5 md:p-6 mt-6">

        <h2 class="text-lg md:text-xl font-bold text-slate-800 mb-4">
            Missed Medicine Insights <span class="text-slate-400 font-normal text-sm">(Last 7 Days)</span>
        </h2>

        <?php if ($weeklyMissed->num_rows == 0) { ?>

            <div class="flex items-center gap-3 text-emerald-700 bg-emerald-50 rounded-xl px-4 py-4">
                <span class="text-xl">✅</span>
                <p class="font-medium">No missed doses in the last 7 days. Great consistency!</p>
            </div>

        <?php } else { ?>

            <div class="space-y-3">
                <?php while ($missed = $weeklyMissed->fetch_assoc()) { ?>
                    <div class="flex justify-between items-center bg-rose-50 rounded-xl px-4 py-3">
                        <p class="font-semibold text-slate-700">
                            <?php echo htmlspecialchars($missed["medicine_name"]); ?>
                        </p>
                        <p class="text-rose-600 font-bold text-sm bg-white px-2.5 py-1 rounded-lg">
                            <?php echo (int) $missed["missed_count"]; ?> missed
                        </p>
                    </div>
                <?php } ?>
            </div>

        <?php } ?>

    </div>

    <!-- =====================================
         RECENT ACTIVITY
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-5 md:p-6 mt-6 mb-8">

        <h2 class="text-lg md:text-xl font-bold text-slate-800 mb-4">
            Recent Activity
        </h2>

        <?php if ($activity->num_rows == 0) { ?>

            <p class="text-slate-500 text-center py-8">No activity available.</p>

        <?php } else { ?>

            <div class="divide-y divide-slate-100">
                <?php while ($row = $activity->fetch_assoc()) { ?>
                    <div class="flex items-center gap-4 py-4">
                        <?php echo status_icon($row["status"]); ?>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-800 truncate">
                                <?php echo htmlspecialchars($row["medicine_name"]); ?>
                            </p>
                            <p class="text-sm text-slate-500">
                                <?php echo date("h:i A", strtotime($row["dose_time"])); ?>
                            </p>
                        </div>
                        <span class="text-sm font-bold text-slate-600 flex-shrink-0">
                            <?php echo htmlspecialchars($row["status"]); ?>
                        </span>
                    </div>
                <?php } ?>
            </div>

        <?php } ?>

    </div>

    <?php } else { ?>

    <!-- =====================================
         EMPTY STATE (no active medicines)
         No adherence %, consistency score, streaks,
         or medicine performance data are calculated
         or shown here - there's nothing to calculate.
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-6 md:p-8 mt-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white text-xl font-bold flex items-center justify-center shadow-md flex-shrink-0">
                <?php echo strtoupper(substr($patientName, 0, 1)); ?>
            </div>
            <h2 class="text-xl md:text-2xl font-bold text-slate-800">
                <?php echo htmlspecialchars($patientName); ?>
            </h2>
        </div>
    </div>

    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-10 md:p-14 mt-6 mb-8 text-center">
        <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 text-3xl flex items-center justify-center mx-auto">
            💊
        </div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-800 mt-5">
            No medicines added yet
        </h2>
        <p class="text-slate-500 mt-2 max-w-md mx-auto">
            Start adding medicines to begin tracking medication adherence, schedules, and reports.
        </p>
        <p class="text-sm text-slate-400 mt-4">
            Ask <?php echo htmlspecialchars($patientName); ?> (or their care team) to add a medicine from the patient dashboard to unlock adherence tracking, dose statistics, and detailed reports here.
        </p>
    </div>

    <?php } ?>

</div>
</div>

<?php include "../includes/footer.php"; ?>