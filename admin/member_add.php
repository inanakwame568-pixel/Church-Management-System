<?php
// admin/member_add.php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
requireLogin();

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and process form
    $firstName = $db->real_escape_string($_POST['first_name']);
    $lastName = $db->real_escape_string($_POST['last_name']);
    $email = $db->real_escape_string($_POST['email']);
    $phone = $db->real_escape_string($_POST['phone']);
    $dob = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $maritalStatus = $_POST['marital_status'];
    $address = $db->real_escape_string($_POST['address']);
    $city = $db->real_escape_string($_POST['city']);
    $state = $_POST['state'];
    $zip = $_POST['zip_code'];
    $membershipDate = $_POST['membership_date'];
    
    // Insert member
    $stmt = $db->prepare("
        INSERT INTO members (
            first_name, last_name, email, phone, date_of_birth, gender, 
            marital_status, address, city, state, zip_code, membership_date,
            membership_status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)
    ");
    
    $stmt->bind_param(
        "ssssssssssssi",
        $firstName, $lastName, $email, $phone, $dob, $gender,
        $maritalStatus, $address, $city, $state, $zip, $membershipDate,
        $_SESSION['user_id']
    );
    
    if ($stmt->execute()) {
        $memberId = $db->insert_id;
        $_SESSION['message'] = 'Member added successfully!';
        header('Location: members.php');
        exit();
    } else {
        $error = 'Error adding member: ' . $db->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Member - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- ... sidebar content ... -->
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h1>Add New Member</h1>
                <div class="header-actions">
                    <a href="members.php" class="btn btn-secondary">Back to Members</a>
                </div>
            </header>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="form-container" id="memberForm">
                <div class="form-section">
                    <h2>Personal Information</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="marital_status">Marital Status</label>
                            <select id="marital_status" name="marital_status">
                                <option value="">Select Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Address Information</h2>
                    
                    <div class="form-group">
                        <label for="address">Street Address</label>
                        <textarea id="address" name="address" rows="2"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city">
                        </div>
                        
                        <div class="form-group">
                            <label for="state">State</label>
                            <select id="state" name="state">
                                <option value="">Select State</option>
                                <option value="AL">Alabama</option>
                                <option value="AK">Alaska</option>
                                <!-- Add all states -->
                                <option value="CA">California</option>
                                <option value="NY">New York</option>
                                <option value="TX">Texas</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="zip_code">ZIP Code</label>
                            <input type="text" id="zip_code" name="zip_code">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Church Information</h2>
                    
                    <div class="form-group">
                        <label for="membership_date">Membership Date</label>
                        <input type="date" id="membership_date" name="membership_date" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Member</button>
                    <button type="reset" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </main>
    </div>

    <script src="../assets/js/script.js"></script>
    <script>
        // Form validation
        document.getElementById('memberForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            
            if (!firstName.trim() || !lastName.trim()) {
                e.preventDefault();
                alert('First name and last name are required!');
            }
        });
    </script>
</body>
</html>