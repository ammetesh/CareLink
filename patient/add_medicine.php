<?php

require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $patient_id = $_SESSION["user_id"];
    $medicine_name = trim($_POST["medicine_name"]);
    $dosage = trim($_POST["dosage"]);
    $frequency = $_POST["frequency"];
    $meal_timing = $_POST["meal_timing"];
    $instructions = trim($_POST["instructions"]);
    $notes = trim($_POST["notes"]);
    $start_date = $_POST["start_date"];
    $end_date = $_POST["end_date"];

    if ($start_date > $end_date) {

        $message = "End date cannot be earlier than Start date.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO medicines
            (
                patient_id,
                medicine_name,
                dosage,
                frequency,
                meal_timing,
                instructions,
                notes,
                start_date,
                end_date
            )
            VALUES
            (?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "issssssss",
            $patient_id,
            $medicine_name,
            $dosage,
            $frequency,
            $meal_timing,
            $instructions,
            $notes,
            $start_date,
            $end_date
        );

        if ($stmt->execute()) {

            $message = "Medicine added successfully!";
            $messageType = "success";

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
        Fill in the medicine details below.
    </p>

    <?php if($message!=""): ?>

        <div class="<?php echo $messageType=="success"
            ? "bg-green-100 border border-green-500 text-green-700"
            : "bg-red-100 border border-red-500 text-red-700"; ?>

            p-4 rounded mb-6">

            <?php echo $message; ?>

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

        <!-- Frequency -->

        <div class="mb-5">

            <label class="font-semibold block mb-2">
                Frequency
            </label>

            <select
                name="frequency"
                class="w-full border rounded-lg p-3">

                <option>Once Daily</option>
                <option>Twice Daily</option>
                <option>Three Times Daily</option>
                <option>Weekly</option>
                <option>As Needed</option>

            </select>

        </div>

        <!-- Meal Timing -->

        <div class="mb-5">

            <label class="font-semibold block mb-2">
                Meal Timing
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

        <!-- Instructions -->

        <div class="mb-5">

            <label class="font-semibold block mb-2">
                Instructions
            </label>

            <textarea
                name="instructions"
                rows="3"
                class="w-full border rounded-lg p-3"></textarea>

        </div>

        <!-- Notes -->

        <div class="mb-5">

            <label class="font-semibold block mb-2">
                Additional Notes
            </label>

            <textarea
                name="notes"
                rows="3"
                class="w-full border rounded-lg p-3"></textarea>

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
include "../includes/footer.php";
?>