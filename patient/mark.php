<?php

require_once "../includes/auth.php";
require_once "../config/db.php";

$schedule_id = $_GET["id"];
$status = $_GET["status"];

$today = date("Y-m-d");
$time = date("Y-m-d H:i:s");

/* Check whether today's record already exists */

$check = $conn->prepare("
SELECT id
FROM dose_logs
WHERE schedule_id = ?
AND log_date = ?
");

$check->bind_param("is", $schedule_id, $today);
$check->execute();

$result = $check->get_result();

if($result->num_rows > 0){

    /* Update existing record */

    $row = $result->fetch_assoc();

    $update = $conn->prepare("
    UPDATE dose_logs
    SET status = ?, taken_time = ?
    WHERE id = ?
    ");

    $update->bind_param(
        "ssi",
        $status,
        $time,
        $row["id"]
    );

    $update->execute();

}else{

    /* Insert new record */

    $insert = $conn->prepare("
    INSERT INTO dose_logs
    (schedule_id, log_date, status, taken_time)
    VALUES
    (?, ?, ?, ?)
    ");

    $insert->bind_param(
        "isss",
        $schedule_id,
        $today,
        $status,
        $time
    );

    $insert->execute();

}

header("Location: dashboard.php");
exit();

?>