<?php
require_once "../includes/auth.php";
require_once "../includes/header.php";
require_once "../config/db.php";

$patient_id = $_SESSION["user_id"];

$success_message = "";
$error_message = "";

// ---------------------------------------------------------
// Handle form submission
// ---------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $gender                  = trim($_POST["gender"] ?? "");
    $date_of_birth           = trim($_POST["date_of_birth"] ?? "");
    $blood_group             = trim($_POST["blood_group"] ?? "");
    $height                  = trim($_POST["height"] ?? "");
    $weight                  = trim($_POST["weight"] ?? "");
    $current_condition       = trim($_POST["current_condition"] ?? "");
    $treatment_for           = trim($_POST["treatment_for"] ?? "");
    $current_medications     = trim($_POST["current_medications"] ?? "");
    $allergies               = trim($_POST["allergies"] ?? "");
    $previous_complications  = trim($_POST["previous_complications"] ?? "");
    $insurance_company       = trim($_POST["insurance_company"] ?? "");
    $policy_number           = trim($_POST["policy_number"] ?? "");
    $coverage_amount         = trim($_POST["coverage_amount"] ?? "");
    $policy_valid_until      = trim($_POST["policy_valid_until"] ?? "");
    $doctor_name             = trim($_POST["doctor_name"] ?? "");
    $doctor_specialization   = trim($_POST["doctor_specialization"] ?? "");
    $hospital_name           = trim($_POST["hospital_name"] ?? "");
    $doctor_phone            = trim($_POST["doctor_phone"] ?? "");

    // Normalize empty values to NULL for every nullable column so a
    // partially filled form saves cleanly and can be completed later
    // without leaving stray empty strings in the database.
    $gender                  = ($gender === "") ? null : $gender;
    $date_of_birth           = ($date_of_birth === "") ? null : $date_of_birth;
    $blood_group             = ($blood_group === "") ? null : $blood_group;
    $height                  = ($height === "") ? null : $height;
    $weight                  = ($weight === "") ? null : $weight;
    $current_condition       = ($current_condition === "") ? null : $current_condition;
    $treatment_for           = ($treatment_for === "") ? null : $treatment_for;
    $current_medications     = ($current_medications === "") ? null : $current_medications;
    $allergies               = ($allergies === "") ? null : $allergies;
    $previous_complications  = ($previous_complications === "") ? null : $previous_complications;
    $insurance_company       = ($insurance_company === "") ? null : $insurance_company;
    $policy_number           = ($policy_number === "") ? null : $policy_number;
    $coverage_amount         = ($coverage_amount === "") ? null : $coverage_amount;
    $policy_valid_until      = ($policy_valid_until === "") ? null : $policy_valid_until;
    $doctor_name             = ($doctor_name === "") ? null : $doctor_name;
    $doctor_specialization   = ($doctor_specialization === "") ? null : $doctor_specialization;
    $hospital_name           = ($hospital_name === "") ? null : $hospital_name;
    $doctor_phone            = ($doctor_phone === "") ? null : $doctor_phone;

    // -------------------------------------------------------
    // Check whether a profile already exists for this patient
    // -------------------------------------------------------
    $check_stmt = $conn->prepare("SELECT id FROM patient_profiles WHERE patient_id = ?");
    $check_stmt->bind_param("i", $patient_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // ---------------- UPDATE existing profile ----------------
        $update_stmt = $conn->prepare("UPDATE patient_profiles SET
                gender = ?,
                date_of_birth = ?,
                blood_group = ?,
                height = ?,
                weight = ?,
                current_condition = ?,
                treatment_for = ?,
                current_medications = ?,
                allergies = ?,
                previous_complications = ?,
                insurance_company = ?,
                policy_number = ?,
                coverage_amount = ?,
                policy_valid_until = ?,
                doctor_name = ?,
                doctor_specialization = ?,
                hospital_name = ?,
                doctor_phone = ?,
                updated_at = NOW()
            WHERE patient_id = ?");

        $update_stmt->bind_param(
            "sssddsssssssdsssssi",
            $gender,
            $date_of_birth,
            $blood_group,
            $height,
            $weight,
            $current_condition,
            $treatment_for,
            $current_medications,
            $allergies,
            $previous_complications,
            $insurance_company,
            $policy_number,
            $coverage_amount,
            $policy_valid_until,
            $doctor_name,
            $doctor_specialization,
            $hospital_name,
            $doctor_phone,
            $patient_id
        );

        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully.";
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
        $update_stmt->close();
    } else {
        // ---------------- INSERT new profile ----------------
        $insert_stmt = $conn->prepare("INSERT INTO patient_profiles (
                patient_id,
                gender,
                date_of_birth,
                blood_group,
                height,
                weight,
                current_condition,
                treatment_for,
                current_medications,
                allergies,
                previous_complications,
                insurance_company,
                policy_number,
                coverage_amount,
                policy_valid_until,
                doctor_name,
                doctor_specialization,
                hospital_name,
                doctor_phone,
                created_at,
                updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");

        $insert_stmt->bind_param(
            "isssddsssssssdsssss",
            $patient_id,
            $gender,
            $date_of_birth,
            $blood_group,
            $height,
            $weight,
            $current_condition,
            $treatment_for,
            $current_medications,
            $allergies,
            $previous_complications,
            $insurance_company,
            $policy_number,
            $coverage_amount,
            $policy_valid_until,
            $doctor_name,
            $doctor_specialization,
            $hospital_name,
            $doctor_phone
        );

        if ($insert_stmt->execute()) {
            $success_message = "Profile created successfully.";
        } else {
            $error_message = "Failed to create profile. Please try again.";
        }
        $insert_stmt->close();
    }
    $check_stmt->close();

    // -------------------------------------------------------
    // Emergency contacts: delete existing, insert new ones
    // (max 5, priority = 1..5 based on submitted order)
    // -------------------------------------------------------
    $contact_names      = $_POST["contact_name"] ?? [];
    $relationships      = $_POST["relationship"] ?? [];
    $phones             = $_POST["phone"] ?? [];
    $alternate_phones   = $_POST["alternate_phone"] ?? [];
    $emails             = $_POST["email"] ?? [];
    $addresses          = $_POST["address"] ?? [];

    $delete_contacts_stmt = $conn->prepare("DELETE FROM emergency_contacts WHERE patient_id = ?");
    $delete_contacts_stmt->bind_param("i", $patient_id);
    $delete_contacts_stmt->execute();
    $delete_contacts_stmt->close();

    $insert_contact_stmt = $conn->prepare("INSERT INTO emergency_contacts (
            patient_id,
            contact_name,
            relationship,
            phone,
            alternate_phone,
            email,
            address,
            priority,
            created_at
        ) VALUES (?,?,?,?,?,?,?,?,NOW())");

    $max_contacts = 5;
    $priority = 1;
    $total_contacts = count($contact_names);

    for ($i = 0; $i < $total_contacts && $priority <= $max_contacts; $i++) {
        $c_name  = trim($contact_names[$i] ?? "");
        $c_rel   = trim($relationships[$i] ?? "");
        $c_phone = trim($phones[$i] ?? "");
        $c_alt   = trim($alternate_phones[$i] ?? "");
        $c_email = trim($emails[$i] ?? "");
        $c_addr  = trim($addresses[$i] ?? "");

        // Skip completely empty contact rows
        if ($c_name === "" && $c_rel === "" && $c_phone === "" && $c_alt === "" && $c_email === "" && $c_addr === "") {
            continue;
        }

        // contact_name, relationship, and phone are NOT NULL columns in the table
        if ($c_name === "" || $c_rel === "" || $c_phone === "") {
            continue;
        }

        // Nullable columns
        $c_alt_bind   = ($c_alt === "") ? null : $c_alt;
        $c_email_bind = ($c_email === "") ? null : $c_email;
        $c_addr_bind  = ($c_addr === "") ? null : $c_addr;

        $insert_contact_stmt->bind_param(
            "issssssi",
            $patient_id,
            $c_name,
            $c_rel,
            $c_phone,
            $c_alt_bind,
            $c_email_bind,
            $c_addr_bind,
            $priority
        );
        $insert_contact_stmt->execute();
        $priority++;
    }
    $insert_contact_stmt->close();
}

// ---------------------------------------------------------
// Load logged in patient's basic user data
// (users table has no "age" column, so age is derived from
// patient_profiles.date_of_birth below instead)
// ---------------------------------------------------------
$user_stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
$user_stmt->bind_param("i", $patient_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// ---------------------------------------------------------
// Load patient profile (if it exists)
// ---------------------------------------------------------
$profile_stmt = $conn->prepare("SELECT * FROM patient_profiles WHERE patient_id = ?");
$profile_stmt->bind_param("i", $patient_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile_data = $profile_result->fetch_assoc();
$profile_stmt->close();

if (!$profile_data) {
    $profile_data = [
        "gender" => "",
        "date_of_birth" => "",
        "blood_group" => "",
        "height" => "",
        "weight" => "",
        "current_condition" => "",
        "treatment_for" => "",
        "current_medications" => "",
        "allergies" => "",
        "previous_complications" => "",
        "insurance_company" => "",
        "policy_number" => "",
        "coverage_amount" => "",
        "policy_valid_until" => "",
        "doctor_name" => "",
        "doctor_specialization" => "",
        "hospital_name" => "",
        "doctor_phone" => "",
    ];
}

// ---------------------------------------------------------
// Derive age from date_of_birth (read-only, not stored directly)
// ---------------------------------------------------------
$computed_age = "";
if (!empty($profile_data["date_of_birth"])) {
    try {
        $dob = new DateTime($profile_data["date_of_birth"]);
        $today = new DateTime("today");
        $computed_age = $dob->diff($today)->y;
    } catch (Exception $e) {
        $computed_age = "";
    }
}

// ---------------------------------------------------------
// Load emergency contacts ordered by priority
// ---------------------------------------------------------
$contacts_stmt = $conn->prepare("SELECT * FROM emergency_contacts WHERE patient_id = ? ORDER BY priority ASC");
$contacts_stmt->bind_param("i", $patient_id);
$contacts_stmt->execute();
$contacts_result = $contacts_stmt->get_result();
$emergency_contacts = [];
while ($row = $contacts_result->fetch_assoc()) {
    $emergency_contacts[] = $row;
}
$contacts_stmt->close();

if (empty($emergency_contacts)) {
    $emergency_contacts[] = [
        "contact_name" => "",
        "relationship" => "",
        "phone" => "",
        "alternate_phone" => "",
        "email" => "",
        "address" => "",
    ];
}
?>

<style>
    :root {
        --clx-primary: #2952e3;
        --clx-primary-dark: #1d3bb0;
        --clx-personal: #3b82f6;
        --clx-medical: #f43f5e;
        --clx-insurance: #f59e0b;
        --clx-doctor: #8b5cf6;
        --clx-emergency: #ef4444;
    }

    /* ---------- Base interactive elements ---------- */
    .clx-input {
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .clx-input:hover {
        border-color: #9ca3af;
    }
    .clx-input:focus {
        outline: none;
        border-color: var(--clx-primary);
        box-shadow: 0 0 0 3px rgba(41, 82, 227, 0.15);
    }
    label.clx-label {
        display: block;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: .35rem;
    }
    .clx-hint {
        font-size: .72rem;
        color: #9ca3af;
        margin-top: .3rem;
    }

    /* ---------- Hero / header ---------- */
    .clx-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #f7f9ff 0%, #ffffff 65%);
    }
    .clx-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--clx-primary);
        margin-bottom: .4rem;
    }
    .clx-eyebrow::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--clx-primary);
        display: inline-block;
    }
    .clx-heartbeat {
        position: absolute;
        top: 6px;
        left: 0;
        width: 55%;
        max-width: 440px;
        height: 44px;
        opacity: .28;
        pointer-events: none;
    }
    .clx-heartbeat path {
        fill: none;
        stroke: var(--clx-primary);
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-dasharray: 520;
        stroke-dashoffset: 520;
        animation: clxDrawPulse 1.6s ease forwards .2s;
    }
    @keyframes clxDrawPulse { to { stroke-dashoffset: 0; } }

    /* ---------- Section cards ---------- */
    .clx-card {
        transition: box-shadow .25s ease, transform .25s ease, opacity .5s ease;
        scroll-margin-top: 96px;
        border-top: 3px solid transparent;
        opacity: 0;
        transform: translateY(16px);
    }
    .clx-card.clx-revealed {
        opacity: 1;
        transform: translateY(0);
    }
    .clx-card:hover {
        box-shadow: 0 12px 28px -8px rgba(0,0,0,0.12), 0 8px 10px -6px rgba(0,0,0,0.06);
        transform: translateY(-3px);
    }
    .clx-card.clx-revealed:hover {
        transform: translateY(-3px);
    }
    #section-personal { border-top-color: var(--clx-personal); }
    #section-medical { border-top-color: var(--clx-medical); }
    #section-insurance { border-top-color: var(--clx-insurance); }
    #section-doctor { border-top-color: var(--clx-doctor); }
    #section-emergency { border-top-color: var(--clx-emergency); }

    .clx-icon-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: .85rem;
        flex-shrink: 0;
    }

    /* ---------- Section quick-nav ---------- */
    .clx-navlink {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        transition: color .15s ease, background-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }
    .clx-navlink.active {
        background-color: var(--nav-color, var(--clx-primary));
        color: #ffffff;
        transform: scale(1.04);
        box-shadow: 0 4px 10px -3px rgba(0,0,0,.25);
    }

    /* ---------- Contact cards ---------- */
    .contact-card {
        border-left: 4px solid var(--clx-emergency);
        transition: opacity .25s ease, transform .25s ease, box-shadow .2s ease;
    }
    .contact-card:hover {
        box-shadow: 0 6px 16px -4px rgba(0,0,0,0.1);
    }
    .contact-card.clx-removing {
        opacity: 0;
        transform: scale(0.97);
    }
    .contact-card.clx-entering {
        animation: clxFadeIn .35s ease;
    }
    @keyframes clxFadeIn {
        from { opacity: 0; transform: translateY(-8px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .clx-priority-badge {
        background: linear-gradient(135deg, var(--clx-emergency), #f97316);
    }

    /* ---------- Toast ---------- */
    #clxToast {
        transition: opacity .3s ease, transform .3s ease;
    }
    #clxToast.clx-shake {
        animation: clxShake .4s ease;
    }
    @keyframes clxShake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-6px); }
        75% { transform: translateX(6px); }
    }

    /* ---------- Progress bar ---------- */
    .clx-badge {
        min-width: 1.75rem;
    }
    .clx-progress-fill {
        transition: width .5s ease, background .5s ease;
        background: linear-gradient(90deg, #f87171, #ef4444);
    }
    #addContactBtn {
        transition: transform .12s ease, background-color .15s ease;
    }
    #addContactBtn:not(:disabled):active {
        transform: scale(0.95);
    }

    /* ---------- Sticky save bar ---------- */
    .clx-savebar {
        box-shadow: 0 -8px 24px -12px rgba(15, 23, 42, .18);
    }
    #clxSaveBtn {
        transition: transform .12s ease, background-color .15s ease, box-shadow .15s ease;
    }
    #clxSaveBtn:active {
        transform: scale(0.97);
    }

    /* ---------- Success modal ---------- */
    #clxSuccessOverlay {
        transition: opacity .25s ease;
    }
    #clxSuccessModal {
        transition: opacity .25s ease, transform .25s ease;
    }
    .clx-check-circle {
        stroke-dasharray: 166;
        stroke-dashoffset: 166;
        animation: clxDrawCircle .5s ease forwards;
    }
    .clx-check-mark {
        stroke-dasharray: 48;
        stroke-dashoffset: 48;
        animation: clxDrawCheck .35s .4s ease forwards;
    }
    @keyframes clxDrawCircle {
        to { stroke-dashoffset: 0; }
    }
    @keyframes clxDrawCheck {
        to { stroke-dashoffset: 0; }
    }
    .clx-confetti {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 8px;
        height: 8px;
        border-radius: 2px;
        opacity: 0;
        animation: clxConfettiBurst .9s ease-out forwards;
    }
    @keyframes clxConfettiBurst {
        0% { opacity: 1; transform: translate(-50%, -50%) rotate(0deg) translateY(0); }
        100% { opacity: 0; transform: translate(-50%, -50%) rotate(var(--clx-rot, 180deg)) translateY(var(--clx-dist, -90px)); }
    }

    /* ---------- Accessibility ---------- */
    .clx-navlink:focus-visible,
    #addContactBtn:focus-visible,
    #clxSaveBtn:focus-visible,
    .removeContactBtn:focus-visible,
    #clxSuccessCloseBtn:focus-visible {
        outline: 2px solid var(--clx-primary);
        outline-offset: 2px;
    }

    @media (prefers-reduced-motion: reduce) {
        .clx-card, .clx-input, .clx-navlink, .contact-card, #clxToast,
        #clxSuccessOverlay, #clxSuccessModal, .clx-progress-fill, #addContactBtn, #clxSaveBtn {
            transition: none !important;
        }
        .clx-card { opacity: 1 !important; transform: none !important; }
        .clx-heartbeat path { animation: none !important; stroke-dashoffset: 0 !important; }
        .clx-check-circle, .clx-check-mark, .clx-confetti, .contact-card.clx-entering, #clxToast.clx-shake {
            animation: none !important;
        }
    }
</style>

<div class="max-w-6xl mx-auto p-8 pb-28">

    <!-- Hero header + completion progress -->
    <div class="clx-hero px-6 py-6 md:px-8 md:py-7 mb-6">
        <svg class="clx-heartbeat hidden md:block" viewBox="0 0 400 60" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 30 H120 L135 10 L150 50 L165 22 L180 30 H400"></path>
        </svg>
        <div class="relative flex flex-col md:flex-row md:items-end md:justify-between gap-5">
            <div>
                <div class="clx-eyebrow">Your health record</div>
                <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-gray-900">Patient Profile</h1>
                <p class="text-gray-500 mt-1.5 max-w-xl">Fill in as much as you know now — every field is optional, and you can save and come back to complete the rest anytime.</p>
            </div>
            <div class="w-full md:w-72 shrink-0">
                <div class="flex justify-between items-baseline text-xs font-medium text-gray-500 mb-1.5">
                    <span>Profile completeness</span>
                    <span id="clxProgressLabel" class="text-gray-800 font-semibold text-sm">0%</span>
                </div>
                <div class="w-full h-2.5 bg-gray-200/80 rounded-full overflow-hidden">
                    <div id="clxProgressBar" class="clx-progress-fill h-2.5 rounded-full" style="width:0%"></div>
                </div>
                <p id="clxProgressStatus" class="text-xs text-gray-400 mt-1.5"></p>
            </div>
        </div>
    </div>

    <!-- Section quick-nav -->
    <div class="sticky top-0 z-20 bg-gray-50/90 backdrop-blur border border-gray-200 rounded-xl p-2 mb-6 flex flex-wrap gap-2 shadow-sm">
        <a href="#section-personal" data-section="section-personal" style="--nav-color:#3b82f6" class="clx-navlink px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"></path></svg>
            Personal
        </a>
        <a href="#section-medical" data-section="section-medical" style="--nav-color:#f43f5e" class="clx-navlink px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M3 12h3.5l2-5 3 10 2-7 1.5 2H21"></path></svg>
            Medical
        </a>
        <a href="#section-insurance" data-section="section-insurance" style="--nav-color:#f59e0b" class="clx-navlink px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z"></path><path d="M9 12l2 2 4-4"></path></svg>
            Insurance
        </a>
        <a href="#section-doctor" data-section="section-doctor" style="--nav-color:#8b5cf6" class="clx-navlink px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M6 3v6a4 4 0 0 0 8 0V3"></path><path d="M10 13v2a5 5 0 0 0 10 0v-1.5"></path><circle cx="20" cy="12" r="1.5"></circle></svg>
            Doctor
        </a>
        <a href="#section-emergency" data-section="section-emergency" style="--nav-color:#ef4444" class="clx-navlink px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-200">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 4v6"></path><path d="M12 16h.01"></path><path d="M10.3 3.5 2.8 17a1.8 1.8 0 0 0 1.6 2.7h15.2a1.8 1.8 0 0 0 1.6-2.7L13.7 3.5a1.8 1.8 0 0 0-3.4 0z"></path></svg>
            Emergency <span id="clxNavContactCount" class="ml-1 text-xs opacity-75"></span>
        </a>
    </div>

    <form method="POST" action="profile.php" id="clxProfileForm" novalidate>

        <!-- Personal Information -->
        <div id="section-personal" class="clx-card bg-white shadow rounded-xl p-6 md:p-7 mb-6 border border-gray-100">
            <div class="flex items-center gap-3 mb-5">
                <span class="clx-icon-badge bg-blue-50 text-blue-600">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><circle cx="12" cy="8" r="3.5"></circle><path d="M4.5 20c1.4-3.6 4.4-5.5 7.5-5.5s6.1 1.9 7.5 5.5"></path></svg>
                </span>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Personal Information</h2>
                    <p class="text-sm text-gray-400">Basic identity &amp; vitals</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="clx-label">Full Name</label>
                    <input type="text" readonly value="<?php echo htmlspecialchars($user_data["full_name"] ?? ""); ?>"
                        class="w-full border border-gray-300 rounded-lg p-2.5 bg-gray-100 cursor-not-allowed">
                    <p class="clx-hint">Managed in account settings</p>
                </div>
                <div>
                    <label class="clx-label">Email</label>
                    <input type="text" readonly value="<?php echo htmlspecialchars($user_data["email"] ?? ""); ?>"
                        class="w-full border border-gray-300 rounded-lg p-2.5 bg-gray-100 cursor-not-allowed">
                    <p class="clx-hint">Managed in account settings</p>
                </div>
                <div>
                    <label class="clx-label">Phone</label>
                    <input type="text" readonly value="<?php echo htmlspecialchars($user_data["phone"] ?? ""); ?>"
                        class="w-full border border-gray-300 rounded-lg p-2.5 bg-gray-100 cursor-not-allowed">
                    <p class="clx-hint">Managed in account settings</p>
                </div>
                <div>
                    <label class="clx-label">Age</label>
                    <input type="text" readonly id="clxAgeDisplay" value="<?php echo htmlspecialchars((string) $computed_age); ?>"
                        class="w-full border border-gray-300 rounded-lg p-2.5 bg-gray-100 cursor-not-allowed"
                        placeholder="Set Date of Birth to calculate">
                    <p class="clx-hint">Calculated automatically from date of birth</p>
                </div>
                <div>
                    <label class="clx-label">Gender</label>
                    <select name="gender" class="clx-input w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        <option value="">Select</option>
                        <?php
                        $genders = ["Male", "Female", "Other"];
                        foreach ($genders as $g) {
                            $selected = ($profile_data["gender"] === $g) ? "selected" : "";
                            echo "<option value=\"" . htmlspecialchars($g) . "\" $selected>" . htmlspecialchars($g) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="clx-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" id="clxDobInput" value="<?php echo htmlspecialchars($profile_data["date_of_birth"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="clx-label">Blood Group</label>
                    <select name="blood_group" class="clx-input w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        <option value="">Select</option>
                        <?php
                        $blood_groups = ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];
                        foreach ($blood_groups as $bg) {
                            $selected = ($profile_data["blood_group"] === $bg) ? "selected" : "";
                            echo "<option value=\"" . htmlspecialchars($bg) . "\" $selected>" . htmlspecialchars($bg) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="clx-label">Height (cm)</label>
                    <input type="number" step="0.01" name="height" id="clxHeightInput" value="<?php echo htmlspecialchars($profile_data["height"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="clx-label">Weight (kg)</label>
                    <input type="number" step="0.01" name="weight" id="clxWeightInput" value="<?php echo htmlspecialchars($profile_data["weight"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div class="md:col-span-2">
                    <div id="clxBmiBox" class="hidden rounded-lg p-3.5 text-sm font-medium border">
                        <span id="clxBmiText"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Information -->
        <div id="section-medical" class="clx-card bg-white shadow rounded-xl p-6 md:p-7 mb-6 border border-gray-100">
            <div class="flex items-center gap-3 mb-5">
                <span class="clx-icon-badge bg-rose-50 text-rose-600">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M3 12h3.5l2-5 3 10 2-7 1.5 2H21"></path></svg>
                </span>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Medical Information</h2>
                    <p class="text-sm text-gray-400">Conditions, treatment &amp; medication</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="clx-label">Current Disease / Condition</label>
                    <textarea name="current_condition" rows="3" class="clx-input w-full border border-gray-300 rounded-lg p-2.5"><?php echo htmlspecialchars($profile_data["current_condition"] ?? ""); ?></textarea>
                </div>
                <div>
                    <label class="clx-label">Undergoing Treatment For</label>
                    <textarea name="treatment_for" rows="3" class="clx-input w-full border border-gray-300 rounded-lg p-2.5"><?php echo htmlspecialchars($profile_data["treatment_for"] ?? ""); ?></textarea>
                </div>
                <div>
                    <label class="clx-label">Current Medications</label>
                    <textarea name="current_medications" rows="3" class="clx-input w-full border border-gray-300 rounded-lg p-2.5"><?php echo htmlspecialchars($profile_data["current_medications"] ?? ""); ?></textarea>
                </div>
                <div>
                    <label class="clx-label">Allergies</label>
                    <textarea name="allergies" rows="3" class="clx-input w-full border border-gray-300 rounded-lg p-2.5"><?php echo htmlspecialchars($profile_data["allergies"] ?? ""); ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="clx-label">Previous Complications</label>
                    <textarea name="previous_complications" rows="3" class="clx-input w-full border border-gray-300 rounded-lg p-2.5"><?php echo htmlspecialchars($profile_data["previous_complications"] ?? ""); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Insurance Details -->
        <div id="section-insurance" class="clx-card bg-white shadow rounded-xl p-6 md:p-7 mb-6 border border-gray-100">
            <div class="flex items-center gap-3 mb-5">
                <span class="clx-icon-badge bg-amber-50 text-amber-600">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z"></path><path d="M9 12l2 2 4-4"></path></svg>
                </span>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Insurance Details</h2>
                    <p class="text-sm text-gray-400">Coverage &amp; policy details</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="clx-label">Insurance Company</label>
                    <input type="text" name="insurance_company" value="<?php echo htmlspecialchars($profile_data["insurance_company"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="clx-label">Policy Number</label>
                    <input type="text" name="policy_number" value="<?php echo htmlspecialchars($profile_data["policy_number"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="clx-label">Coverage Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                        <input type="number" step="0.01" name="coverage_amount" value="<?php echo htmlspecialchars($profile_data["coverage_amount"] ?? ""); ?>"
                            class="clx-input w-full border border-gray-300 rounded-lg p-2.5 pl-7">
                    </div>
                </div>
                <div>
                    <label class="clx-label">Policy Valid Until</label>
                    <input type="date" name="policy_valid_until" id="clxPolicyValidUntil" value="<?php echo htmlspecialchars($profile_data["policy_valid_until"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                    <p id="clxPolicyHint" class="text-xs mt-1.5 hidden"></p>
                </div>
            </div>
        </div>

        <!-- Doctor Details -->
        <div id="section-doctor" class="clx-card bg-white shadow rounded-xl p-6 md:p-7 mb-6 border border-gray-100">
            <div class="flex items-center gap-3 mb-5">
                <span class="clx-icon-badge bg-violet-50 text-violet-600">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M6 3v6a4 4 0 0 0 8 0V3"></path><path d="M10 13v2a5 5 0 0 0 10 0v-1.5"></path><circle cx="20" cy="12" r="1.5"></circle></svg>
                </span>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Doctor Details</h2>
                    <p class="text-sm text-gray-400">Primary care contact</p>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="clx-label">Doctor Name</label>
                    <input type="text" name="doctor_name" value="<?php echo htmlspecialchars($profile_data["doctor_name"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="clx-label">Doctor Specialization</label>
                    <input type="text" name="doctor_specialization" value="<?php echo htmlspecialchars($profile_data["doctor_specialization"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="clx-label">Hospital / Clinic</label>
                    <input type="text" name="hospital_name" value="<?php echo htmlspecialchars($profile_data["hospital_name"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
                <div>
                    <label class="clx-label">Doctor Phone</label>
                    <input type="text" name="doctor_phone" value="<?php echo htmlspecialchars($profile_data["doctor_phone"] ?? ""); ?>"
                        class="clx-input w-full border border-gray-300 rounded-lg p-2.5">
                </div>
            </div>
        </div>

        <!-- Emergency Contacts -->
        <div id="section-emergency" class="clx-card bg-white shadow rounded-xl p-6 md:p-7 mb-6 border border-gray-100">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-3">
                    <span class="clx-icon-badge bg-red-50 text-red-600">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5"><path d="M12 4v6"></path><path d="M12 16h.01"></path><path d="M10.3 3.5 2.8 17a1.8 1.8 0 0 0 1.6 2.7h15.2a1.8 1.8 0 0 0 1.6-2.7L13.7 3.5a1.8 1.8 0 0 0-3.4 0z"></path></svg>
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Emergency Contacts</h2>
                        <p class="text-sm text-gray-400">Who to call, in order of priority</p>
                    </div>
                </div>
                <button type="button" id="addContactBtn"
                    class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-4 py-2 rounded-lg font-medium">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="w-4 h-4"><path d="M12 5v14M5 12h14"></path></svg>
                    Add Contact
                </button>
            </div>
            <p class="text-sm text-gray-500 mb-4 ml-[3.25rem]">
                <span id="clxContactCount">0</span>/5 contacts added
            </p>

            <div id="contactsContainer" class="space-y-4"></div>

            <template id="contactTemplate">
                <div class="contact-card border border-gray-200 rounded-lg p-4 relative bg-gray-50/50">
                    <div class="flex items-center justify-between mb-3">
                        <span class="clx-badge clx-priority-badge inline-flex items-center justify-center text-white text-xs font-bold rounded-full h-7 w-7 clx-priority-badge">1</span>
                        <button type="button" class="removeContactBtn text-red-600 hover:text-white hover:bg-red-600 border border-red-200 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                            Remove
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="clx-label">Contact Name</label>
                            <input type="text" name="contact_name[]" class="clx-input w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        </div>
                        <div>
                            <label class="clx-label">Relationship</label>
                            <input type="text" name="relationship[]" class="clx-input w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        </div>
                        <div>
                            <label class="clx-label">Phone</label>
                            <input type="text" name="phone[]" class="clx-input w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        </div>
                        <div>
                            <label class="clx-label">Alternate Phone</label>
                            <input type="text" name="alternate_phone[]" class="clx-input w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        </div>
                        <div>
                            <label class="clx-label">Email</label>
                            <input type="email" name="email[]" class="clx-input w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        </div>
                        <div>
                            <label class="clx-label">Address</label>
                            <input type="text" name="address[]" class="clx-input w-full border border-gray-300 rounded-lg p-2.5 bg-white">
                        </div>
                    </div>
                </div>
            </template>
        </div>

    </form>
</div>

<!-- Sticky save bar -->
<div class="clx-savebar fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur border-t border-gray-200 py-3 z-30">
    <div class="max-w-6xl mx-auto px-8 flex items-center justify-between">
        <span class="text-sm text-gray-500 hidden sm:block">Changes are saved only when you click Save.</span>
        <button type="submit" form="clxProfileForm" id="clxSaveBtn"
            class="ml-auto inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 active:scale-95 text-white px-6 py-3 rounded-lg font-semibold">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M20 6 9 17l-5-5"></path></svg>
            Save Profile
        </button>
    </div>
</div>

<!-- Toast notification (used for errors / minor notices) -->
<div id="clxToast" class="fixed top-6 right-6 z-50 opacity-0 translate-y-[-10px] pointer-events-none">
    <div id="clxToastInner" class="px-5 py-3 rounded-lg shadow-lg font-medium text-white flex items-center gap-2"></div>
</div>

<!-- Success popup (used after a successful save) -->
<div id="clxSuccessOverlay" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none flex items-center justify-center">
    <div id="clxSuccessModal" class="relative opacity-0 scale-95 bg-white rounded-2xl shadow-2xl px-10 py-8 flex flex-col items-center text-center max-w-sm mx-4">
        <div class="relative w-20 h-20 mb-4">
            <svg viewBox="0 0 60 60" class="w-20 h-20">
                <circle class="clx-check-circle" cx="30" cy="30" r="26" fill="none" stroke="#16a34a" stroke-width="4" stroke-linecap="round"></circle>
                <polyline class="clx-check-mark" points="18,31 26,39 43,20" fill="none" stroke="#16a34a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></polyline>
            </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-800">Saved Successfully</h3>
        <p id="clxSuccessSubtext" class="text-gray-500 text-sm mt-1"></p>
        <button type="button" id="clxSuccessCloseBtn" class="mt-5 bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-colors">
            Continue
        </button>
    </div>
</div>

<script>
    // ---------------------------------------------------------
    // Server-provided state
    // ---------------------------------------------------------
    const CLX_SUCCESS_MESSAGE = <?php echo json_encode($success_message); ?>;
    const CLX_ERROR_MESSAGE = <?php echo json_encode($error_message); ?>;
    const CLX_EXISTING_CONTACTS = <?php echo json_encode(array_map(function ($c) {
        return [
            "contact_name" => $c["contact_name"] ?? "",
            "relationship" => $c["relationship"] ?? "",
            "phone" => $c["phone"] ?? "",
            "alternate_phone" => $c["alternate_phone"] ?? "",
            "email" => $c["email"] ?? "",
            "address" => $c["address"] ?? "",
        ];
    }, $emergency_contacts)); ?>;

    // ---------------------------------------------------------
    // DOM references (all grabbed up front, before anything runs,
    // so every helper function below can safely rely on them)
    // ---------------------------------------------------------
    const MAX_CONTACTS = 5;
    const contactsContainer = document.getElementById("contactsContainer");
    const addContactBtn = document.getElementById("addContactBtn");
    const contactTemplate = document.getElementById("contactTemplate");
    const contactCountEl = document.getElementById("clxContactCount");
    const navContactCountEl = document.getElementById("clxNavContactCount");

    const toastEl = document.getElementById("clxToast");
    const toastInner = document.getElementById("clxToastInner");
    let toastTimer = null;

    const successOverlay = document.getElementById("clxSuccessOverlay");
    const successModal = document.getElementById("clxSuccessModal");
    const successSubtext = document.getElementById("clxSuccessSubtext");
    const successCloseBtn = document.getElementById("clxSuccessCloseBtn");

    const dobInput = document.getElementById("clxDobInput");
    const ageDisplay = document.getElementById("clxAgeDisplay");

    const heightInput = document.getElementById("clxHeightInput");
    const weightInput = document.getElementById("clxWeightInput");
    const bmiBox = document.getElementById("clxBmiBox");
    const bmiText = document.getElementById("clxBmiText");

    const policyValidUntilInput = document.getElementById("clxPolicyValidUntil");
    const policyHint = document.getElementById("clxPolicyHint");

    const navLinks = Array.from(document.querySelectorAll(".clx-navlink"));
    const sections = navLinks.map(function (link) {
        return document.getElementById(link.dataset.section);
    });

    const profileForm = document.getElementById("clxProfileForm");
    const progressBar = document.getElementById("clxProgressBar");
    const progressLabel = document.getElementById("clxProgressLabel");
    const progressStatus = document.getElementById("clxProgressStatus");
    const trackedFieldNames = [
        "gender", "date_of_birth", "blood_group", "height", "weight",
        "current_condition", "treatment_for", "current_medications", "allergies",
        "insurance_company", "policy_number", "coverage_amount", "policy_valid_until",
        "doctor_name", "doctor_specialization", "hospital_name", "doctor_phone"
    ];

    const ICON_CHECK = '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>';
    const ICON_ALERT = '<svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 8v5"></path><path d="M12 16h.01"></path></svg>';

    // ---------------------------------------------------------
    // Toast notifications (used for errors / minor notices)
    // ---------------------------------------------------------
    function showToast(message, type) {
        if (!message) return;
        toastInner.innerHTML = "";
        const iconHolder = document.createElement("span");
        iconHolder.innerHTML = (type === "error") ? ICON_ALERT : ICON_CHECK;
        const textSpan = document.createElement("span");
        textSpan.textContent = message;
        toastInner.appendChild(iconHolder.firstElementChild);
        toastInner.appendChild(textSpan);
        toastInner.className = "px-5 py-3 rounded-lg shadow-lg font-medium text-white flex items-center gap-2 " +
            (type === "error" ? "bg-red-600" : "bg-green-600");
        toastEl.classList.remove("opacity-0", "translate-y-[-10px]");
        toastEl.classList.add("opacity-100", "translate-y-0");
        if (type === "error") {
            toastEl.classList.remove("clx-shake");
            void toastEl.offsetWidth; // restart animation
            toastEl.classList.add("clx-shake");
        }
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove("opacity-100", "translate-y-0");
            toastEl.classList.add("opacity-0", "translate-y-[-10px]");
        }, 3500);
    }

    // ---------------------------------------------------------
    // "Saved Successfully" popup with checkmark + confetti burst
    // ---------------------------------------------------------
    function spawnConfetti() {
        const colors = ["#16a34a", "#2563eb", "#f59e0b", "#ef4444", "#8b5cf6"];
        for (let i = 0; i < 18; i++) {
            const dot = document.createElement("span");
            dot.className = "clx-confetti";
            dot.style.backgroundColor = colors[i % colors.length];
            const angle = (Math.random() * 360) + "deg";
            const distance = (60 + Math.random() * 60) + "px";
            dot.style.setProperty("--clx-rot", angle);
            dot.style.setProperty("--clx-dist", "-" + distance);
            dot.style.left = (45 + Math.random() * 10) + "%";
            successModal.appendChild(dot);
            setTimeout(function () { dot.remove(); }, 950);
        }
    }

    function showSuccessModal(message) {
        successSubtext.textContent = message || "Your changes have been saved.";
        successOverlay.classList.remove("opacity-0", "pointer-events-none");
        successOverlay.classList.add("opacity-100");
        successModal.classList.remove("opacity-0", "scale-95");
        successModal.classList.add("opacity-100", "scale-100");
        // Restart the checkmark draw animation each time it's shown
        const circle = successModal.querySelector(".clx-check-circle");
        const check = successModal.querySelector(".clx-check-mark");
        [circle, check].forEach(function (el) {
            el.style.animation = "none";
            void el.offsetWidth;
            el.style.animation = "";
        });
        spawnConfetti();
    }

    function hideSuccessModal() {
        successOverlay.classList.remove("opacity-100");
        successOverlay.classList.add("opacity-0", "pointer-events-none");
        successModal.classList.remove("opacity-100", "scale-100");
        successModal.classList.add("opacity-0", "scale-95");
    }

    successCloseBtn.addEventListener("click", hideSuccessModal);
    successOverlay.addEventListener("click", function (e) {
        if (e.target === successOverlay) hideSuccessModal();
    });

    // ---------------------------------------------------------
    // Profile completeness progress bar
    // Bound to BOTH "input" and "change" on the form (see init
    // block below) so it reacts immediately no matter which kind
    // of field changed: typing in a text/number/textarea fires
    // "input", while some browsers only fire "change" for
    // <select> and <input type="date"> interactions.
    // ---------------------------------------------------------
    function updateProgress() {
        let filled = 0;
        trackedFieldNames.forEach(function (name) {
            const field = profileForm.querySelector('[name="' + name + '"]');
            if (field && field.value && field.value.trim() !== "") filled++;
        });
        const hasContact = getContactCards().some(function (card) {
            const nameField = card.querySelector('[name="contact_name[]"]');
            return nameField && nameField.value.trim() !== "";
        });
        if (hasContact) filled++;
        const total = trackedFieldNames.length + 1;
        const pct = Math.round((filled / total) * 100);
        progressBar.style.width = pct + "%";
        progressLabel.textContent = pct + "%";
        if (pct < 40) {
            progressBar.style.background = "linear-gradient(90deg, #f87171, #ef4444)";
            progressStatus.textContent = pct === 0
                ? "Let's get started — fill in what you can."
                : "Just getting started.";
        } else if (pct < 80) {
            progressBar.style.background = "linear-gradient(90deg, #fbbf24, #f59e0b)";
            progressStatus.textContent = "Good progress — keep going.";
        } else if (pct < 100) {
            progressBar.style.background = "linear-gradient(90deg, #4ade80, #16a34a)";
            progressStatus.textContent = "Almost there.";
        } else {
            progressBar.style.background = "linear-gradient(90deg, #34d399, #16a34a)";
            progressStatus.textContent = "Profile complete.";
        }
    }

    // ---------------------------------------------------------
    // Emergency contact cards
    // ---------------------------------------------------------
    function getContactCards() {
        return Array.from(contactsContainer.querySelectorAll(".contact-card"));
    }

    function renumberContacts() {
        const cards = getContactCards();
        cards.forEach(function (card, idx) {
            const badge = card.querySelector(".clx-priority-badge");
            if (badge) badge.textContent = String(idx + 1);
        });
        contactCountEl.textContent = String(cards.length);
        navContactCountEl.textContent = "(" + cards.length + "/5)";
        addContactBtn.disabled = cards.length >= MAX_CONTACTS;
        updateProgress();
    }

    function bindRemoveButton(card) {
        const removeBtn = card.querySelector(".removeContactBtn");
        removeBtn.addEventListener("click", function () {
            const cards = getContactCards();
            if (cards.length <= 1) {
                showToast("At least one emergency contact is required.", "error");
                return;
            }
            card.classList.add("clx-removing");
            setTimeout(function () {
                card.remove();
                renumberContacts();
            }, 200);
        });
    }

    function createContactCard(data) {
        const fragment = contactTemplate.content.cloneNode(true);
        const card = fragment.querySelector(".contact-card");
        if (data) {
            card.querySelector('[name="contact_name[]"]').value = data.contact_name || "";
            card.querySelector('[name="relationship[]"]').value = data.relationship || "";
            card.querySelector('[name="phone[]"]').value = data.phone || "";
            card.querySelector('[name="alternate_phone[]"]').value = data.alternate_phone || "";
            card.querySelector('[name="email[]"]').value = data.email || "";
            card.querySelector('[name="address[]"]').value = data.address || "";
        }
        bindRemoveButton(card);
        return card;
    }

    function addContactCard(data, animate) {
        const cards = getContactCards();
        if (cards.length >= MAX_CONTACTS) {
            showToast("Maximum 5 emergency contacts allowed.", "error");
            return;
        }
        const card = createContactCard(data);
        if (animate) card.classList.add("clx-entering");
        contactsContainer.appendChild(card);
        renumberContacts();
    }

    addContactBtn.addEventListener("click", function () {
        addContactCard(null, true);
    });

    // ---------------------------------------------------------
    // Live age calculation from Date of Birth
    // Listens on both "input" (fires immediately as a date is
    // picked/typed) and "change" (fires on final commit) so the
    // age updates the moment a date is chosen, not just on blur.
    // ---------------------------------------------------------
    function recalcAge() {
        if (!dobInput.value) {
            ageDisplay.value = "";
            return;
        }
        const dob = new Date(dobInput.value);
        if (isNaN(dob.getTime())) return;
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        ageDisplay.value = age >= 0 ? age : "";
    }
    ["input", "change"].forEach(function (evt) {
        dobInput.addEventListener(evt, recalcAge);
    });

    // ---------------------------------------------------------
    // Live BMI calculation
    // ---------------------------------------------------------
    function recalcBmi() {
        const h = parseFloat(heightInput.value);
        const w = parseFloat(weightInput.value);
        if (!h || !w || h <= 0 || w <= 0) {
            bmiBox.classList.add("hidden");
            return;
        }
        const meters = h / 100;
        const bmi = w / (meters * meters);
        let category = "Normal";
        let boxClasses = "bg-green-50 border-green-200 text-green-700";
        let pillClasses = "bg-green-600 text-white";
        if (bmi < 18.5) {
            category = "Underweight";
            boxClasses = "bg-yellow-50 border-yellow-200 text-yellow-700";
            pillClasses = "bg-yellow-500 text-white";
        } else if (bmi >= 25 && bmi < 30) {
            category = "Overweight";
            boxClasses = "bg-yellow-50 border-yellow-200 text-yellow-700";
            pillClasses = "bg-yellow-500 text-white";
        } else if (bmi >= 30) {
            category = "Obese";
            boxClasses = "bg-red-50 border-red-200 text-red-700";
            pillClasses = "bg-red-600 text-white";
        }
        bmiBox.classList.remove("hidden");
        bmiBox.className = "rounded-lg p-3.5 text-sm font-medium border flex items-center gap-2 " + boxClasses;
        bmiText.innerHTML = "BMI&nbsp;<span class=\"text-base font-bold\">" + bmi.toFixed(1) + "</span>" +
            "<span class=\"" + pillClasses + " inline-flex ml-2 px-2 py-0.5 rounded-full text-xs font-semibold\">" + category + "</span>";
    }
    heightInput.addEventListener("input", recalcBmi);
    weightInput.addEventListener("input", recalcBmi);

    // ---------------------------------------------------------
    // Policy expiry hint
    // ---------------------------------------------------------
    function recalcPolicyHint() {
        if (!policyValidUntilInput.value) {
            policyHint.classList.add("hidden");
            return;
        }
        const validUntil = new Date(policyValidUntilInput.value);
        const today = new Date();
        const diffDays = Math.ceil((validUntil - today) / (1000 * 60 * 60 * 24));
        policyHint.classList.remove("hidden");
        if (diffDays < 0) {
            policyHint.textContent = "This policy has expired.";
            policyHint.className = "text-xs mt-1.5 text-red-600 font-medium";
        } else if (diffDays <= 30) {
            policyHint.textContent = "Expires in " + diffDays + " day(s) — renewal recommended.";
            policyHint.className = "text-xs mt-1.5 text-yellow-600 font-medium";
        } else {
            policyHint.textContent = "Policy is active.";
            policyHint.className = "text-xs mt-1.5 text-green-600 font-medium";
        }
    }
    ["input", "change"].forEach(function (evt) {
        policyValidUntilInput.addEventListener(evt, recalcPolicyHint);
    });

    // ---------------------------------------------------------
    // Section scrollspy nav
    // ---------------------------------------------------------
    navLinks.forEach(function (link) {
        link.addEventListener("click", function (e) {
            e.preventDefault();
            const target = document.getElementById(link.dataset.section);
            if (target) {
                window.scrollTo({
                    top: target.getBoundingClientRect().top + window.scrollY - 90,
                    behavior: "smooth"
                });
            }
        });
    });

    function updateActiveNav() {
        let currentIndex = 0;
        sections.forEach(function (section, idx) {
            if (section && section.getBoundingClientRect().top - 110 <= 0) {
                currentIndex = idx;
            }
        });
        navLinks.forEach(function (link, idx) {
            link.classList.toggle("active", idx === currentIndex);
        });
    }
    window.addEventListener("scroll", updateActiveNav);

    // ---------------------------------------------------------
    // Reveal cards as they scroll into view
    // ---------------------------------------------------------
    const revealObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add("clx-revealed");
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll(".clx-card").forEach(function (card) {
        revealObserver.observe(card);
    });

    // ---------------------------------------------------------
    // Initialization — runs after every helper above is defined
    // ---------------------------------------------------------
    if (CLX_EXISTING_CONTACTS.length > 0) {
        CLX_EXISTING_CONTACTS.forEach(function (c) {
            addContactCard(c, false);
        });
    } else {
        addContactCard(null, false);
    }

    // Bind progress recalculation to BOTH "input" and "change" at the
    // form level (event delegation) so every field type — text,
    // textarea, number, <select>, and <input type="date"> — reliably
    // updates the completeness bar as soon as it's filled in, on
    // every browser.
    ["input", "change"].forEach(function (evt) {
        profileForm.addEventListener(evt, updateProgress);
    });

    updateProgress();
    recalcAge();
    recalcBmi();
    recalcPolicyHint();
    updateActiveNav();

    if (CLX_SUCCESS_MESSAGE) showSuccessModal(CLX_SUCCESS_MESSAGE);
    if (CLX_ERROR_MESSAGE) showToast(CLX_ERROR_MESSAGE, "error");
</script>

<?php include "../includes/footer.php"; ?>