<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$message = "";
$messageType = "";
$redirect = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $patient_id = $_SESSION["user_id"];
    $medicine_name = trim($_POST["medicine_name"]);
    $dosage = trim($_POST["dosage"]);
    $take_time="";

if(isset($_POST["take_time"])){

$take_time=implode(",",$_POST["take_time"]);

}
    $meal_timing = $_POST["meal_timing"];
    $start_date = $_POST["start_date"];
    $end_date = $_POST["end_date"];

    if(empty($take_time)){

    $message = "Please select at least one time slot.";

    $messageType = "error";

}

else if($start_date > $end_date) {

    $message = "End date cannot be earlier than Start date.";

    $messageType = "error";

}

else {

        $stmt = $conn->prepare("
            INSERT INTO medicines
            (
                patient_id,
medicine_name,
dosage,
take_time,
meal_timing,
start_date,
end_date
            )
            VALUES
            (?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "issssss",
            $patient_id,
$medicine_name,
$dosage,
$take_time,
$meal_timing,
$start_date,
$end_date
        );
if ($stmt->execute()) {

    $medicine_id = $conn->insert_id;


    /*----------------------------------
        AUTO SCHEDULE SYSTEM
    ----------------------------------*/

    $schedule_times = [];

    if(isset($_POST["take_time"])){

        foreach($_POST["take_time"] as $time){

            if($time=="Morning"){

                $schedule_times[] = "08:00:00";

            }

            if($time=="Afternoon"){

                $schedule_times[] = "13:00:00";

            }

            if($time=="Night"){

                $schedule_times[] = "20:00:00";

            }

        }

    }


    foreach($schedule_times as $dose_time){

        $schedule_stmt = $conn->prepare("

        INSERT INTO schedules
        (medicine_id,dose_time)

        VALUES

        (?,?)

        ");

        $schedule_stmt->bind_param(
        "is",
        $medicine_id,
        $dose_time
        );

        $schedule_stmt->execute();

        $schedule_stmt->close();

    }


    $message = "Medicine added and automatically scheduled successfully!";

    $messageType = "success";

    $redirect = true;


} else {

    $message = "Something went wrong. Please try again.";

    $messageType = "error";

}
        $stmt->close();
    }
}

?>

<div class="max-w-4xl mx-auto mt-10 mb-10 bg-white rounded-xl shadow-lg p-8">

    <h1 class="text-3xl font-bold text-blue-700 mb-2">
        Add Medicine
    </h1>

    <p class="text-gray-500 mb-8">
        Add your prescribed medicine details below.
    </p>

    <?php if($message!=""): ?>

<div class="<?php echo $messageType=="success"
? "bg-green-100 border border-green-500 text-green-700"
: "bg-red-100 border border-red-500 text-red-700"; ?>

p-4 rounded mb-6">

<?php echo $message; ?>


<?php if($redirect){ ?>

<p class="text-gray-600 mt-3">

Redirecting to your dashboard...

</p>

<?php } ?>


</div>

<?php endif; ?>

    <form method="POST">

        <!-- Medicine Name -->

        <div class="mb-5">

            <label class="font-semibold block mb-2">
                Medicine Name
            </label>

            <input
                type="text"
                name="medicine_name"
                required
                class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-500">

        </div>

        <!-- Dosage -->

        <div class="mb-5">

            <label class="font-semibold block mb-2">
                Dosage
            </label>

            <input
                type="text"
                name="dosage"
                placeholder="Example: 500 mg"
                required
                class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-500">

        </div>

<!-- Take During -->

<div class="mb-5">

<label class="font-semibold block mb-3">

Take During

</label>

<div class="flex gap-8">

<label>

<input
type="checkbox"
name="take_time[]"
value="Morning">

Morning

</label>


<label>

<input
type="checkbox"
name="take_time[]"
value="Afternoon">

Afternoon

</label>


<label>

<input
type="checkbox"
name="take_time[]"
value="Night">

Night

</label>

</div>

</div>

        <!-- Meal Timing -->

        <div class="mb-5">

            <label class="font-semibold block mb-2">
               Take Relative To Meals
            </label>

            <select
                name="meal_timing"
                class="w-full border rounded-lg p-3">

                <option>Before Food</option>
                <option>After Food</option>
                <option>With Food</option>
                <option>Anytime</option>

            </select>

        </div>

        
        <!-- Dates -->

        <div class="grid md:grid-cols-2 gap-6">

            <div>

                <label class="font-semibold block mb-2">
                    Start Date
                </label>

                <input
                    type="date"
                    name="start_date"
                    required
                    class="w-full border rounded-lg p-3">

            </div>

            <div>

                <label class="font-semibold block mb-2">
                    End Date
                </label>

                <input
                    type="date"
                    name="end_date"
                    required
                    class="w-full border rounded-lg p-3">

            </div>

        </div>

        <button
            type="submit"
            class="mt-8 w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-semibold">

            Save Medicine

        </button>

    </form>

</div>
<?php

if($redirect){

?>

<script>

setTimeout(function(){

window.location.href="dashboard.php";

},2000);

</script>

<?php

}

?>

<?php
include "../includes/footer.php";
?>