<?php

require_once "../includes/auth.php";
require_once "../config/db.php";

$patient_id = $_SESSION["user_id"];

if (!isset($_GET["id"])) {
    header("Location: medicines.php");
    exit();
}

$id = $_GET["id"];

$stmt = $conn->prepare("
DELETE FROM medicines
WHERE id = ?
AND patient_id = ?
");

$stmt->bind_param("ii", $id, $patient_id);

$stmt->execute();

header("Location: medicines.php");
exit();

?>