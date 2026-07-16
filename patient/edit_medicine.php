<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$patient_id = $_SESSION["user_id"];

$id = $_GET["id"];

$stmt = $conn->prepare("
SELECT *
FROM medicines
WHERE id=? AND patient_id=?
");

$stmt->bind_param("ii",$id,$patient_id);
$stmt->execute();

$result=$stmt->get_result();

if($result->num_rows==0){

die("Medicine not found.");

}

$medicine=$result->fetch_assoc();

if($_SERVER["REQUEST_METHOD"]=="POST"){

$medicine_name=$_POST["medicine_name"];
$dosage=$_POST["dosage"];
$frequency=$_POST["frequency"];
$meal_timing=$_POST["meal_timing"];
$instructions=$_POST["instructions"];
$notes=$_POST["notes"];
$start_date=$_POST["start_date"];
$end_date=$_POST["end_date"];

$update=$conn->prepare("

UPDATE medicines

SET

medicine_name=?,
dosage=?,
frequency=?,
meal_timing=?,
instructions=?,
notes=?,
start_date=?,
end_date=?

WHERE id=?

");

$update->bind_param(

"ssssssssi",

$medicine_name,
$dosage,
$frequency,
$meal_timing,
$instructions,
$notes,
$start_date,
$end_date,
$id

);

$update->execute();

header("Location: medicines.php");

exit();

}

?>

<div class="max-w-4xl mx-auto mt-10 bg-white shadow rounded-xl p-8">

<h1 class="text-3xl font-bold mb-8">

Edit Medicine

</h1>

<form method="POST">

<input
name="medicine_name"
value="<?php echo $medicine["medicine_name"]; ?>"
class="w-full border p-3 rounded mb-4">

<input
name="dosage"
value="<?php echo $medicine["dosage"]; ?>"
class="w-full border p-3 rounded mb-4">

<select
name="frequency"
class="w-full border p-3 rounded mb-4">

<?php

$options=[
"Once Daily",
"Twice Daily",
"Three Times Daily",
"Weekly",
"As Needed"
];

foreach($options as $option){

$selected=($medicine["frequency"]==$option)?"selected":"";

echo "<option $selected>$option</option>";

}

?>

</select>

<select
name="meal_timing"
class="w-full border p-3 rounded mb-4">

<?php

$options=[
"Before Food",
"After Food",
"With Food",
"Anytime"
];

foreach($options as $option){

$selected=($medicine["meal_timing"]==$option)?"selected":"";

echo "<option $selected>$option</option>";

}

?>

</select>

<textarea
name="instructions"
class="w-full border p-3 rounded mb-4"><?php echo $medicine["instructions"]; ?></textarea>

<textarea
name="notes"
class="w-full border p-3 rounded mb-4"><?php echo $medicine["notes"]; ?></textarea>

<input
type="date"
name="start_date"
value="<?php echo $medicine["start_date"]; ?>"
class="w-full border p-3 rounded mb-4">

<input
type="date"
name="end_date"
value="<?php echo $medicine["end_date"]; ?>"
class="w-full border p-3 rounded mb-6">

<button
class="bg-green-600 text-white px-8 py-3 rounded hover:bg-green-700">

Update Medicine

</button>

</form>

</div>

<?php

include "../includes/footer.php";

?>