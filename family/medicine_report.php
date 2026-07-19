<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$family_id = (int) $_SESSION["user_id"];

/* =====================================
   GET LINKED PATIENT
   (Same pattern as family/dashboard.php)
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
   REPORT PERIOD (Week / Month toggle)

   BUGFIX: this previously used a rolling window
   (e.g. "last 30 days" / "last 7 days ending today"),
   which silently pulled in doses from the previous
   week/month whenever today wasn't the last day of
   the calendar period. It now uses the actual
   calendar week (Monday-Sunday) and calendar month
   (1st to last day) instead.

   Only affects the summary numbers and the
   per-medicine report table. The tick chart
   always shows the last 7 days for readability.
===================================== */

$range = (isset($_GET["range"]) && $_GET["range"] === "month") ? "month" : "week";

if ($range === "month") {

    // Current calendar month: 1st day -> last day of this month
    $periodStartDate = date("Y-m-01");
    $periodEndDate = date("Y-m-t");

} else {

    // Current calendar week: Monday -> Sunday of this week
    $periodStartDate = date("Y-m-d", strtotime("monday this week"));
    $periodEndDate = date("Y-m-d", strtotime("sunday this week"));

}

// Number of days actually inside the selected period
// (always 7 for a week; 28-31 for a month depending on
// the month) so "Total scheduled doses" reflects the
// real period length instead of a fixed guess.
$periodDays = (int) ((strtotime($periodEndDate) - strtotime($periodStartDate)) / 86400) + 1;

/* =====================================
   LAST 7 DAYS (for the tick/validation chart)
===================================== */

$chartDays = [];
for ($i = 6; $i >= 0; $i--) {
    $chartDays[] = date("Y-m-d", strtotime("-$i day"));
}
$chartStartDate = $chartDays[0];

/* =====================================
   ACTIVE MEDICINES FOR THIS PATIENT
===================================== */

$stmt = $conn->prepare("
    SELECT id, medicine_name, dosage, meal_timing, start_date, end_date
    FROM medicines
    WHERE patient_id = ?
    AND is_active = 1
    ORDER BY medicine_name
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$medicinesResult = $stmt->get_result();
$stmt->close();

$medicines = [];
while ($row = $medicinesResult->fetch_assoc()) {
    $medicines[] = $row;
}

// Used to gate every adherence/report calculation below -
// with zero medicines there is nothing to calculate.
$hasMedicines = count($medicines) > 0;

/* =====================================
   OVERALL PERFORMANCE SUMMARY
   (All medicines combined, for the selected period)
   Skipped entirely when there are no active medicines -
   there is nothing to schedule, log, or divide by.
===================================== */

$overallTotalDoses = 0;
$overallTaken = 0;
$overallSkipped = 0;
$overallSnoozed = 0;
$overallMissed = 0;
$overallAdherence = 0;

if ($hasMedicines) {

    // Doses scheduled per day, across all active medicines
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM schedules
        JOIN medicines ON schedules.medicine_id = medicines.id
        WHERE medicines.patient_id = ?
        AND medicines.is_active = 1
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $dosesPerDayAll = (int) $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();

    $overallTotalDoses = $dosesPerDayAll * $periodDays;

    // Taken / Skipped / Snoozed across all medicines, within the period
    $stmt = $conn->prepare("
        SELECT
            SUM(dose_logs.status = 'Taken')   AS taken,
            SUM(dose_logs.status = 'Skipped') AS skipped,
            SUM(dose_logs.status = 'Snoozed') AS snoozed
        FROM dose_logs
        JOIN schedules ON dose_logs.schedule_id = schedules.id
        JOIN medicines ON schedules.medicine_id = medicines.id
        WHERE medicines.patient_id = ?
        AND medicines.is_active = 1
        AND dose_logs.log_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $patient_id, $periodStartDate, $periodEndDate);
    $stmt->execute();
    $overallLogs = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $overallTaken = (int) ($overallLogs["taken"] ?? 0);
    $overallSkipped = (int) ($overallLogs["skipped"] ?? 0);
    $overallSnoozed = (int) ($overallLogs["snoozed"] ?? 0);

    $overallMissed = $overallTotalDoses - $overallTaken;
    if ($overallMissed < 0) {
        $overallMissed = 0;
    }

    $overallAdherence = $overallTotalDoses > 0
        ? round(($overallTaken / $overallTotalDoses) * 100)
        : 0;

}

/* =====================================
   PER-MEDICINE REPORT DATA
   Built once per medicine: schedule times,
   period totals, and the 7-day tick chart.
   (The foreach simply does nothing when
   $medicines is empty - no extra guard needed.)
===================================== */

$medicineReports = [];

foreach ($medicines as $medicine) {

    $medicine_id = (int) $medicine["id"];

    // Schedule (dose) times for this medicine
    $stmt = $conn->prepare("
        SELECT dose_time
        FROM schedules
        WHERE medicine_id = ?
        ORDER BY dose_time
    ");
    $stmt->bind_param("i", $medicine_id);
    $stmt->execute();
    $scheduleResult = $stmt->get_result();
    $stmt->close();

    $doseTimes = [];
    while ($s = $scheduleResult->fetch_assoc()) {
        $doseTimes[] = $s["dose_time"];
    }

    $dosesPerDay = count($doseTimes);

    $timingLabel = $dosesPerDay > 0
        ? implode(", ", array_map(function ($t) {
            return date("h:i A", strtotime($t));
        }, $doseTimes))
        : "No schedule set";

    // Totals for the selected period (week/month)
    $totalInPeriod = $dosesPerDay * $periodDays;

    $stmt = $conn->prepare("
        SELECT
            SUM(dose_logs.status = 'Taken')   AS taken,
            SUM(dose_logs.status = 'Skipped') AS skipped,
            SUM(dose_logs.status = 'Snoozed') AS snoozed
        FROM dose_logs
        JOIN schedules ON dose_logs.schedule_id = schedules.id
        WHERE schedules.medicine_id = ?
        AND dose_logs.log_date BETWEEN ? AND ?
    ");
    $stmt->bind_param("iss", $medicine_id, $periodStartDate, $periodEndDate);
    $stmt->execute();
    $periodLogs = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $takenCount = (int) ($periodLogs["taken"] ?? 0);
    $skippedCount = (int) ($periodLogs["skipped"] ?? 0);
    $snoozedCount = (int) ($periodLogs["snoozed"] ?? 0);

    $adherencePercent = $totalInPeriod > 0
        ? round(($takenCount / $totalInPeriod) * 100)
        : 0;

    // Daily breakdown for the last 7 days (used by the tick chart)
    $stmt = $conn->prepare("
        SELECT
            dose_logs.log_date,
            SUM(dose_logs.status = 'Taken')   AS taken,
            SUM(dose_logs.status = 'Skipped') AS skipped,
            SUM(dose_logs.status = 'Snoozed') AS snoozed
        FROM dose_logs
        JOIN schedules ON dose_logs.schedule_id = schedules.id
        WHERE schedules.medicine_id = ?
        AND dose_logs.log_date >= ?
        GROUP BY dose_logs.log_date
    ");
    $stmt->bind_param("is", $medicine_id, $chartStartDate);
    $stmt->execute();
    $dailyResult = $stmt->get_result();
    $stmt->close();

    $dailyByDate = [];
    while ($d = $dailyResult->fetch_assoc()) {
        $dailyByDate[$d["log_date"]] = [
            "taken" => (int) $d["taken"],
            "skipped" => (int) $d["skipped"],
            "snoozed" => (int) $d["snoozed"],
        ];
    }

    // Build the 7-day tick chart for this medicine
    $tickChart = [];

    foreach ($chartDays as $day) {

        $isBeforeStart = !empty($medicine["start_date"]) && strtotime($day) < strtotime($medicine["start_date"]);
        $isAfterEnd = !empty($medicine["end_date"]) && strtotime($day) > strtotime($medicine["end_date"]);

        if ($dosesPerDay === 0 || $isBeforeStart || $isAfterEnd) {
            $state = "none";
        } else {
            $counts = $dailyByDate[$day] ?? ["taken" => 0, "skipped" => 0, "snoozed" => 0];

            if ($counts["skipped"] > 0) {
                $state = "skipped";
            } elseif ($counts["snoozed"] > 0) {
                $state = "snoozed";
            } elseif ($counts["taken"] >= $dosesPerDay) {
                $state = "taken";
            } else {
                // Within the medicine's active window but not
                // (fully) logged yet - e.g. today or a future day.
                $state = "pending";
            }
        }

        $tickChart[] = [
            "label" => date("D", strtotime($day)),
            "state" => $state,
        ];
    }

    $medicineReports[] = [
        "name" => $medicine["medicine_name"],
        "dosage" => $medicine["dosage"],
        "timing" => $timingLabel,
        "meal_timing" => $medicine["meal_timing"],
        "dosesPerDay" => $dosesPerDay,
        "totalInPeriod" => $totalInPeriod,
        "taken" => $takenCount,
        "skipped" => $skippedCount,
        "snoozed" => $snoozedCount,
        "adherence" => $adherencePercent,
        "tickChart" => $tickChart,
    ];
}

/* =====================================
   UI HELPER: tick chart icon per state
===================================== */

function tick_icon($state) {

    $map = [
        "taken" => ["icon" => "✔", "bg" => "bg-emerald-100", "text" => "text-emerald-600"],
        "skipped" => ["icon" => "✖", "bg" => "bg-rose-100", "text" => "text-rose-600"],
        "snoozed" => ["icon" => "⏰", "bg" => "bg-amber-100", "text" => "text-amber-600"],
        "pending" => ["icon" => "•", "bg" => "bg-slate-100", "text" => "text-slate-400"],
        "none" => ["icon" => "–", "bg" => "bg-slate-50", "text" => "text-slate-300"],
    ];

    $s = $map[$state] ?? $map["none"];

    return '<span class="w-8 h-8 md:w-9 md:h-9 rounded-lg ' . $s["bg"] . ' ' . $s["text"] . ' flex items-center justify-center text-sm md:text-base font-bold">' . $s["icon"] . '</span>';
}

?>
<div class="bg-slate-50 min-h-screen">
<div class="max-w-6xl mx-auto p-4 md:p-8">

    <!-- =====================================
         HEADER
    ====================================== -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <a href="dashboard.php" class="text-sm text-blue-600 font-semibold hover:underline">&larr; Back to Dashboard</a>
            <h1 class="text-2xl md:text-4xl font-bold text-slate-800 tracking-tight mt-2">
                Medicine Report
            </h1>
            <p class="text-slate-500 mt-1 text-sm md:text-base">
                Detailed adherence report for <?php echo htmlspecialchars($patientName); ?>
            </p>
        </div>

        <?php if ($hasMedicines) { ?>
        <!-- Week / Month toggle -->
        <div class="inline-flex bg-white border border-slate-200 rounded-xl p-1 self-start">
            <a href="?range=week"
               class="px-4 py-2 rounded-lg text-sm font-semibold <?php echo $range === "week" ? "bg-blue-600 text-white" : "text-slate-500"; ?>">
                This Week
            </a>
            <a href="?range=month"
               class="px-4 py-2 rounded-lg text-sm font-semibold <?php echo $range === "month" ? "bg-blue-600 text-white" : "text-slate-500"; ?>">
                This Month
            </a>
        </div>
        <?php } ?>
    </div>

    <?php if (!$hasMedicines) { ?>

    <!-- =====================================
         EMPTY STATE (no medicines)
         Validation chart, weekly/monthly reports,
         and adherence calculations are all hidden -
         there is no medicine data to show.
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-10 md:p-14 mt-6 mb-8 text-center">
        <div class="w-16 h-16 rounded-full bg-blue-50 text-blue-600 text-3xl flex items-center justify-center mx-auto">
            📋
        </div>
        <h2 class="text-xl md:text-2xl font-bold text-slate-800 mt-5">
            No medicine records available
        </h2>
        <p class="text-slate-500 mt-2 max-w-md mx-auto">
            Once medicines are scheduled, detailed reports and adherence tracking will appear here.
        </p>
    </div>

    <?php } else { ?>

    <!-- =====================================
         OVERALL PERFORMANCE SUMMARY
    ====================================== -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">

        <div class="bg-white border border-slate-100 shadow-sm rounded-xl p-4">
            <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Total Doses</p>
            <p class="text-3xl font-bold text-slate-800 mt-2"><?php echo $overallTotalDoses; ?></p>
        </div>

        <div class="bg-white border border-slate-100 shadow-sm rounded-xl p-4">
            <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Taken</p>
            <p class="text-3xl font-bold text-emerald-600 mt-2"><?php echo $overallTaken; ?></p>
        </div>

        <div class="bg-white border border-slate-100 shadow-sm rounded-xl p-4">
            <p class="text-slate-400 text-xs font-semibold uppercase tracking-wide">Missed</p>
            <p class="text-3xl font-bold text-rose-600 mt-2"><?php echo $overallMissed; ?></p>
        </div>

        <div class="bg-white border-2 border-purple-100 shadow-sm rounded-xl p-4">
            <p class="text-purple-500 text-xs font-bold uppercase tracking-wide">Adherence</p>
            <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $overallAdherence; ?>%</p>
            <div class="w-full bg-slate-200 rounded-full h-2 mt-2">
                <div class="bg-purple-600 h-2 rounded-full" style="width:<?php echo $overallAdherence; ?>%;"></div>
            </div>
        </div>

    </div>

    <!-- =====================================
         MEDICINE REPORT TABLE (desktop)
         / CARD LIST (mobile)
    ====================================== -->
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-5 md:p-6 mt-6">

        <h2 class="text-lg md:text-xl font-bold text-slate-800 mb-1">
            Medicine Report
        </h2>
        <p class="text-sm text-slate-400 mb-5">
            <?php echo $range === "month" ? "Last 30 days" : "Last 7 days"; ?> per medicine breakdown.
        </p>

        <?php if (count($medicineReports) === 0) { ?>

            <p class="text-slate-500 text-center py-8">No active medicines to report on.</p>

        <?php } else { ?>

            <!-- Desktop table -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 uppercase text-xs border-b border-slate-100">
                            <th class="py-3 pr-3">Medicine</th>
                            <th class="py-3 pr-3">Dosage</th>
                            <th class="py-3 pr-3">Timing</th>
                            <th class="py-3 pr-3 text-center">Total</th>
                            <th class="py-3 pr-3 text-center">Taken</th>
                            <th class="py-3 pr-3 text-center">Skipped</th>
                            <th class="py-3 pr-3 text-center">Snoozed</th>
                            <th class="py-3 pr-3 text-right">Adherence</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicineReports as $report) { ?>
                            <tr class="border-b border-slate-50">
                                <td class="py-4 pr-3 font-semibold text-slate-800">
                                    💊 <?php echo htmlspecialchars($report["name"]); ?>
                                </td>
                                <td class="py-4 pr-3 text-slate-600"><?php echo htmlspecialchars($report["dosage"]); ?></td>
                                <td class="py-4 pr-3 text-slate-600"><?php echo htmlspecialchars($report["timing"]); ?></td>
                                <td class="py-4 pr-3 text-center text-slate-700 font-semibold"><?php echo $report["totalInPeriod"]; ?></td>
                                <td class="py-4 pr-3 text-center text-emerald-600 font-semibold"><?php echo $report["taken"]; ?></td>
                                <td class="py-4 pr-3 text-center text-rose-600 font-semibold"><?php echo $report["skipped"]; ?></td>
                                <td class="py-4 pr-3 text-center text-amber-600 font-semibold"><?php echo $report["snoozed"]; ?></td>
                                <td class="py-4 pr-3 text-right">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                        <?php echo $report["adherence"] >= 90 ? "bg-emerald-50 text-emerald-700"
                                            : ($report["adherence"] >= 70 ? "bg-blue-50 text-blue-700"
                                            : ($report["adherence"] >= 50 ? "bg-amber-50 text-amber-700"
                                            : "bg-rose-50 text-rose-700")); ?>">
                                        <?php echo $report["adherence"]; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="md:hidden space-y-4">
                <?php foreach ($medicineReports as $report) { ?>
                    <div class="border border-slate-100 rounded-xl p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-slate-800">💊 <?php echo htmlspecialchars($report["name"]); ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($report["dosage"]); ?> · <?php echo htmlspecialchars($report["timing"]); ?></p>
                            </div>
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                <?php echo $report["adherence"] >= 90 ? "bg-emerald-50 text-emerald-700"
                                    : ($report["adherence"] >= 70 ? "bg-blue-50 text-blue-700"
                                    : ($report["adherence"] >= 50 ? "bg-amber-50 text-amber-700"
                                    : "bg-rose-50 text-rose-700")); ?>">
                                <?php echo $report["adherence"]; ?>%
                            </span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 mt-3 text-center">
                            <div>
                                <p class="text-slate-800 font-bold"><?php echo $report["totalInPeriod"]; ?></p>
                                <p class="text-[10px] text-slate-400 uppercase">Total</p>
                            </div>
                            <div>
                                <p class="text-emerald-600 font-bold"><?php echo $report["taken"]; ?></p>
                                <p class="text-[10px] text-slate-400 uppercase">Taken</p>
                            </div>
                            <div>
                                <p class="text-rose-600 font-bold"><?php echo $report["skipped"]; ?></p>
                                <p class="text-[10px] text-slate-400 uppercase">Skipped</p>
                            </div>
                            <div>
                                <p class="text-amber-600 font-bold"><?php echo $report["snoozed"]; ?></p>
                                <p class="text-[10px] text-slate-400 uppercase">Snoozed</p>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>

        <?php } ?>

    </div>

    <!-- =====================================
         VALIDATION TRACKING CHART (last 7 days)
    ====================================== -->
    <?php
        // Presentation-only aggregation for the "This Week" summary strip.
        // Reuses the already-computed $medicineReports / $chartDays data —
        // no new queries, no change to the underlying PHP/SQL logic.
        $weekCompleted = 0;
        $weekMissed = 0;

        foreach ($medicineReports as $r) {
            foreach ($r["tickChart"] as $day) {
                if ($day["state"] === "taken") {
                    $weekCompleted++;
                } elseif ($day["state"] === "skipped" || $day["state"] === "snoozed") {
                    $weekMissed++;
                }
                // "pending" (today/future, not yet due) and "none"
                // (outside the medicine's active window) are excluded
                // from both counts since they aren't a completed or
                // missed dose.
            }
        }

        $weekApplicable = $weekCompleted + $weekMissed;
        $weekAdherence = $weekApplicable > 0 ? round(($weekCompleted / $weekApplicable) * 100) : 0;

        $stateLabels = [
            "taken" => "Taken",
            "skipped" => "Skipped",
            "snoozed" => "Snoozed",
            "pending" => "Pending",
            "none" => "No schedule",
        ];
    ?>
    <div class="bg-white shadow-sm border border-slate-100 rounded-2xl p-5 md:p-6 mt-6 mb-8">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg md:text-xl font-bold text-slate-800">
                    Validation Tracking Chart
                </h2>
                <p class="text-sm text-slate-400">Daily status per medicine for the last 7 days.</p>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap gap-3 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><?php echo tick_icon("taken"); ?> Taken</span>
                <span class="flex items-center gap-1.5"><?php echo tick_icon("skipped"); ?> Skipped</span>
                <span class="flex items-center gap-1.5"><?php echo tick_icon("snoozed"); ?> Snoozed</span>
                <span class="flex items-center gap-1.5"><?php echo tick_icon("none"); ?> No schedule</span>
            </div>
        </div>

        <?php if (count($medicineReports) === 0) { ?>

            <p class="text-slate-500 text-center py-8">No active medicines to display.</p>

        <?php } else { ?>

            <!-- This Week summary strip -->
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 bg-slate-50 border border-slate-100 rounded-xl px-5 py-4 mb-6 text-sm">
                <span class="font-bold text-slate-700">This week:</span>
                <span class="flex items-center gap-1.5 text-emerald-700 font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <?php echo $weekCompleted; ?> dose<?php echo $weekCompleted == 1 ? "" : "s"; ?> completed
                </span>
                <span class="flex items-center gap-1.5 text-rose-700 font-semibold">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <?php echo $weekMissed; ?> dose<?php echo $weekMissed == 1 ? "" : "s"; ?> missed
                </span>
                <span class="flex items-center gap-1.5 text-purple-700 font-semibold">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                    <?php echo $weekAdherence; ?>% adherence
                </span>
            </div>

            <!-- Scrollable grid (horizontal scroll on mobile instead of wrapping) -->
            <div class="overflow-x-auto -mx-1 px-1">
                <table class="w-full border-separate" style="border-spacing: 0 10px; min-width: 640px;">
                    <thead>
                        <tr>
                            <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2 pr-4 sticky left-0 bg-white">
                                Medicine
                            </th>
                            <?php foreach ($chartDays as $headerDate) { ?>
                                <th class="text-center text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2 px-1 w-16">
                                    <?php echo date("D", strtotime($headerDate)); ?>
                                    <div class="text-[10px] font-normal normal-case text-slate-300">
                                        <?php echo date("d M", strtotime($headerDate)); ?>
                                    </div>
                                </th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicineReports as $report) { ?>
                            <tr class="bg-slate-50/60 align-middle">
                                <td class="pr-4 pl-3 py-3 rounded-l-xl sticky left-0 bg-slate-50/60">
                                    <p class="font-semibold text-slate-800 whitespace-nowrap">
                                        💊 <?php echo htmlspecialchars($report["name"]); ?>
                                    </p>
                                    <p class="text-[11px] text-slate-400 whitespace-nowrap">
                                        <?php echo htmlspecialchars($report["dosage"]); ?>
                                    </p>
                                </td>
                                <?php foreach ($report["tickChart"] as $index => $day) {
                                    $fullDate = $chartDays[$index];
                                    $tooltipDate = date("D, d M Y", strtotime($fullDate));
                                    $tooltipStatus = $stateLabels[$day["state"]] ?? "Unknown";
                                ?>
                                    <td class="text-center py-3 px-1 last:rounded-r-xl">
                                        <div class="relative flex justify-center group">

                                            <?php echo tick_icon($day["state"]); ?>

                                            <!-- Hover tooltip -->
                                            <div class="pointer-events-none absolute bottom-full mb-2 left-1/2 -translate-x-1/2 z-20
                                                        opacity-0 group-hover:opacity-100 transition-opacity duration-150
                                                        bg-slate-800 text-white text-xs rounded-lg px-3 py-2 shadow-lg whitespace-nowrap">
                                                <p class="font-semibold"><?php echo htmlspecialchars($report["name"]); ?></p>
                                                <p class="text-slate-300"><?php echo $tooltipDate; ?></p>
                                                <p class="text-slate-300">Scheduled: <?php echo htmlspecialchars($report["timing"]); ?></p>
                                                <p class="font-semibold mt-0.5"><?php echo $tooltipStatus; ?></p>
                                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                                            </div>

                                        </div>
                                    </td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        <?php } ?>

    </div>

    <?php } ?>

</div>
</div>

<?php include "../includes/footer.php"; ?>