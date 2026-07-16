<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$patient_id = $_SESSION["user_id"];

$stmt = $conn->prepare("
SELECT *
FROM medicines
WHERE patient_id=?
ORDER BY id DESC
");

$stmt->bind_param("i",$patient_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<div class="max-w-7xl mx-auto p-8">

<div class="flex justify-between items-center mb-8">

<div>

<h1 class="text-3xl font-bold">
My Medicines
</h1>

<p class="text-gray-500">
Manage your prescribed medicines.
</p>

</div>

<a
href="add_medicine.php"
class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg">

+ Add Medicine

</a>

</div>

<?php

if($result->num_rows==0){

?>

<div class="bg-white rounded-xl shadow p-8 text-center">

<p class="text-gray-500">

No medicines added yet.

</p>

</div>

<?php

}else{

?>

<div class="bg-white rounded-xl shadow overflow-hidden">

<table class="w-full">

<thead class="bg-gray-100">

<tr>

<th class="p-4 text-left">Medicine</th>

<th class="p-4 text-left">Dosage</th>

<th class="p-4 text-left">Frequency</th>

<th class="p-4 text-left">Meal Timing</th>

<th class="p-4 text-left">Status</th>

<th class="p-4 text-center">Actions</th>

</tr>

</thead>

<tbody>

<?php

while($row=$result->fetch_assoc()){

?>

<tr class="border-b">

<td class="p-4">

<?php echo htmlspecialchars($row["medicine_name"]); ?>

</td>

<td class="p-4">

<?php echo htmlspecialchars($row["dosage"]); ?>

</td>

<td class="p-4">

<?php echo htmlspecialchars($row["frequency"]); ?>

</td>

<td class="p-4">

<?php echo htmlspecialchars($row["meal_timing"]); ?>

</td>

<td class="p-4">

<?php

if($row["is_active"]){

echo "<span class='text-green-600 font-semibold'>Active</span>";

}else{

echo "<span class='text-red-600 font-semibold'>Inactive</span>";

}

?>

</td>

<td class="p-4 text-center">

<div class="flex justify-center gap-3">

<a
href="schedule_medicine.php?id=<?php echo $row['id']; ?>"
class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
Schedule
</a>

<a
href="edit_medicine.php?id=<?php echo $row['id']; ?>"
class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
Edit
</a>

<a
href="delete_medicine.php?id=<?php echo $row['id']; ?>"
onclick="return confirm('Delete this medicine?')"
class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
Delete
</a>

</div>
</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

<?php

}

?>

</div>

<?php

include "../includes/footer.php";

?>