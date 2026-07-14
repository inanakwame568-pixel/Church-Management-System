<?php
// donation_receipt.php - Generate Printable Donation Receipt
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/member_auth.php';

// Require login
requireLogin();

// Get database connection
$db = Database::getInstance()->getConnection();

// Check if donation ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: member_donations.php');
    exit();
}

$donation_id = (int)$_GET['id'];
$member_id = getCurrentMemberId();

// Get donation details with member info
$query = "SELECT d.*, 
                 m.first_name, m.last_name, m.email, m.phone, m.address, m.city, m.state, m.zip_code,
                 CONCAT(m.first_name, ' ', m.last_name) as member_full_name
          FROM donations d
          JOIN members m ON d.member_id = m.member_id
          WHERE d.donation_id = ? AND d.member_id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("ii", $donation_id, $member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: member_donations.php');
    exit();
}

$donation = $result->fetch_assoc();

// Generate receipt number
$receipt_number = 'RCP-' . strtoupper(uniqid()) . '-' . $donation['donation_id'];

// Set page title
$page_title = "Donation Receipt";

// Include member header
include 'member_header.php';
?>

<div class="fade-in" id="receiptContainer">
    <!-- Receipt Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-receipt me-2 text-primary"></i>Donation Receipt</h2>
            <p class="text-muted mb-0">Receipt #: <?php echo $receipt_number; ?></p>
        </div>
        <div>
            <button class="btn btn-primary me-2" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Receipt
            </button>
            <button class="btn btn-outline-secondary" onclick="downloadAsPDF()">
                <i class="fas fa-download me-2"></i>Download PDF
            </button>
        </div>
    </div>

    <!-- Receipt Card -->
    <div class="member-card receipt-card" id="receiptCard">
        <div class="card-body p-4 p-lg-5">
            <!-- Church Header -->
            <div class="text-center mb-5">
                <div class="church-logo mb-3">
                    <i class="fas fa-church fa-3x text-primary"></i>
                </div>
                <h2 class="fw-bold mb-1"><?php echo APP_NAME; ?></h2>
                <p class="text-muted mb-0">123 Church Street, City, ST 12345</p>
                <p class="text-muted">Phone: (555) 123-4567 | Email: info@church.org</p>
                <hr class="my-3">
                <h4 class="fw-bold">Tax-Deductible Donation Receipt</h4>
                <p class="text-muted small">Thank you for your generous support</p>
            </div>

            <!-- Receipt Info Row -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-box bg-light p-3 rounded">
                        <h6 class="fw-bold mb-2"><i class="fas fa-ticket-alt me-2 text-primary"></i>Receipt Information</h6>
                        <p class="mb-1"><strong>Receipt Number:</strong> <?php echo $receipt_number; ?></p>
                        <p class="mb-1"><strong>Transaction ID:</strong> <?php echo $donation['transaction_id'] ?? 'TXN-' . $donation['donation_id']; ?></p>
                        <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($donation['donation_date'])); ?></p>
                        <p class="mb-0"><strong>Status:</strong> <span class="badge bg-success">Completed</span></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box bg-light p-3 rounded">
                        <h6 class="fw-bold mb-2"><i class="fas fa-user me-2 text-primary"></i>Donor Information</h6>
                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($donation['member_full_name']); ?></p>
                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($donation['email']); ?></p>
                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($donation['phone'] ?? 'Not provided'); ?></p>
                        <?php if (!empty($donation['address'])): ?>
                            <p class="mb-0">
                                <strong>Address:</strong> <?php echo htmlspecialchars($donation['address']); ?>
                                <?php if (!empty($donation['city'])) echo ', ' . htmlspecialchars($donation['city']); ?>
                                <?php if (!empty($donation['state'])) echo ', ' . htmlspecialchars($donation['state']); ?>
                                <?php if (!empty($donation['zip_code'])) echo ' ' . htmlspecialchars($donation['zip_code']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Donation Details Table -->
            <table class="table table-bordered donation-table">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th class="text-center">Fund</th>
                        <th class="text-center">Payment Method</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Donation to <?php echo htmlspecialchars($donation['fund_type']); ?></strong>
                            <?php if (!empty($donation['notes'])): ?>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($donation['notes']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo htmlspecialchars($donation['fund_type']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($donation['payment_method']); ?></td>
                        <td class="text-end fw-bold text-success">$<?php echo number_format($donation['amount'], 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Total Donation:</td>
                        <td class="text-end fw-bold text-success h5">$<?php echo number_format($donation['amount'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>

            <!-- Donation Summary -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="border rounded p-3">
                        <h6 class="fw-bold mb-2"><i class="fas fa-chart-pie me-2 text-primary"></i>Year-to-Date Summary</h6>
                        <?php
                        $current_year = date('Y');
                        $ytd_query = "SELECT SUM(amount) as total, COUNT(*) as count 
                                     FROM donations 
                                     WHERE member_id = ? AND YEAR(donation_date) = ?";
                        $ytd_stmt = $db->prepare($ytd_query);
                        $ytd_stmt->bind_param("ii", $member_id, $current_year);
                        $ytd_stmt->execute();
                        $ytd_result = $ytd_stmt->get_result()->fetch_assoc();
                        ?>
                        <p class="mb-1"><strong>Total (<?php echo $current_year; ?>):</strong> $<?php echo number_format($ytd_result['total'] ?? 0, 2); ?></p>
                        <p class="mb-0"><strong>Number of Donations:</strong> <?php echo $ytd_result['count'] ?? 0; ?></p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded p-3">
                        <h6 class="fw-bold mb-2"><i class="fas fa-quote-right me-2 text-primary"></i>Thank You Note</h6>
                        <p class="mb-0 fst-italic text-muted">
                            "Thank you for your faithful giving. Your generosity helps us fulfill our mission 
                            and serve our community. May God continue to bless you abundantly."
                        </p>
                    </div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="text-center mt-5 pt-3 border-top">
                <p class="small text-muted mb-0">
                    <i class="fas fa-shield-alt me-1"></i>
                    This receipt serves as official documentation for your tax-deductible contribution.
                    No goods or services were provided in exchange for this contribution.
                </p>
                <p class="small text-muted mt-2">
                    <?php echo APP_NAME; ?> is a 501(c)(3) non-profit organization. EIN: XX-XXXXXXX
                </p>
                <div class="mt-2">
                    <i class="fas fa-qrcode fa-2x text-muted opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Receipt styling */
.receipt-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    background: white;
}

.donation-table th, 
.donation-table td {
    padding: 12px 15px;
    vertical-align: middle;
}

.donation-table tfoot td {
    background-color: #f8fafc;
    font-weight: 600;
}

.info-box {
    border-radius: 12px;
}

/* Print styles */
@media print {
    body {
        background: white;
        padding: 0;
        margin: 0;
    }
    
    .navbar, .footer, .btn, .nav-tabs, .member-header {
        display: none !important;
    }
    
    .member-container {
        padding: 0;
        margin: 0;
        max-width: 100%;
    }
    
    .receipt-card {
        border: none;
        box-shadow: none;
        margin: 0;
        padding: 0;
    }
    
    .info-box {
        border: 1px solid #ddd;
        break-inside: avoid;
    }
    
    .donation-table {
        break-inside: avoid;
    }
    
    @page {
        size: portrait;
        margin: 1.5cm;
    }
}

/* Fade-in animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-in {
    animation: fadeIn 0.5s ease-out;
}

/* Responsive */
@media (max-width: 768px) {
    .donation-table th, 
    .donation-table td {
        padding: 8px 10px;
        font-size: 0.85rem;
    }
    
    .info-box {
        margin-bottom: 15px;
    }
    
    .btn {
        font-size: 0.85rem;
        padding: 6px 12px;
    }
}
</style>

<script>
// Print receipt
function printReceipt() {
    window.print();
}

// Download as PDF (using browser print with PDF destination)
function downloadAsPDF() {
    // Show print dialog and user can select "Save as PDF"
    window.print();
}

// Email receipt (optional)
function emailReceipt() {
    const email = "<?php echo $donation['email']; ?>";
    const subject = "Donation Receipt - <?php echo APP_NAME; ?>";
    const body = "Dear <?php echo htmlspecialchars($donation['member_full_name']); ?>,\n\n"
               + "Thank you for your donation of $<?php echo number_format($donation['amount'], 2); ?>.\n"
               + "Your receipt is attached.\n\n"
               + "Receipt #: <?php echo $receipt_number; ?>\n"
               + "Date: <?php echo date('F j, Y', strtotime($donation['donation_date'])); ?>\n"
               + "Fund: <?php echo $donation['fund_type']; ?>\n\n"
               + "You can also view and print your receipt from your member dashboard.\n\n"
               + "Thank you for your generosity!\n\n"
               + "<?php echo APP_NAME; ?>";
    
    window.location.href = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

// Social share (optional)
function shareReceipt() {
    alert("You can download or print this receipt for your records.");
}

// Track receipt view
document.addEventListener('DOMContentLoaded', function() {
    // Optional: Send analytics that receipt was viewed
    console.log('Receipt viewed for donation #<?php echo $donation_id; ?>');
    
    // Add animation to the receipt card
    const receiptCard = document.querySelector('.receipt-card');
    if (receiptCard) {
        receiptCard.classList.add('fade-in');
    }
});

// Prevent image blur on print
window.onbeforeprint = function() {
    const images = document.querySelectorAll('.church-logo i');
    images.forEach(img => {
        img.style.fontSize = '3rem';
    });
};

window.onafterprint = function() {
    const images = document.querySelectorAll('.church-logo i');
    images.forEach(img => {
        img.style.fontSize = '';
    });
};
</script>

<?php
// Include member footer
include 'member_footer.php';
?>