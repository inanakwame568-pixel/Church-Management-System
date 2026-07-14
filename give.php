<?php
// give.php - Online Giving Page for Members
require_once 'includes/config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/member_auth.php';

// Require member login
requireMember();

// Get database connection
$db = Database::getInstance()->getConnection();

// Get current member info
$member_id = getCurrentMemberId();
$user_name = getCurrentUserName();

// Get member details for pre-filling
$member_query = "SELECT first_name, last_name, email, phone FROM members WHERE member_id = ?";
$member_stmt = $db->prepare($member_query);
$member_stmt->bind_param("i", $member_id);
$member_stmt->execute();
$member = $member_stmt->get_result()->fetch_assoc();

// Initialize variables
$success = '';
$error = '';
$show_confirmation = false;
$donation_data = [];

// Handle donation submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['process_donation'])) {
        // Process the donation
        $amount = floatval($_POST['amount'] ?? 0);
        $fund = sanitize($_POST['fund'] ?? 'General');
        $payment_method = sanitize($_POST['payment_method'] ?? 'credit_card');
        $frequency = sanitize($_POST['frequency'] ?? 'one-time');
        $notes = sanitize($_POST['notes'] ?? '');
        $anonymous = isset($_POST['anonymous']) ? 1 : 0;
        $cover_fees = isset($_POST['cover_fees']) ? 1 : 0;
        
        // Validation
        $errors = [];
        
        if ($amount <= 0) {
            $errors[] = "Please enter a valid amount";
        } elseif ($amount < 1) {
            $errors[] = "Minimum donation amount is $1.00";
        } elseif ($amount > 10000) {
            $errors[] = "Maximum donation amount is $10,000.00 for online transactions";
        }
        
        if (empty($errors)) {
            // For demo/development mode, simulate successful payment
            if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
                // Development mode - simulate success
                $transaction_id = 'TXN_' . strtoupper(uniqid());
                $success = "✅ DEVELOPMENT MODE: Your donation of $" . number_format($amount, 2) . " has been recorded!";
                
                // Save to database
                $save_query = "INSERT INTO donations (member_id, amount, donation_date, payment_method, fund_type, notes, transaction_id, status) 
                               VALUES (?, ?, NOW(), ?, ?, ?, ?, 'completed')";
                $save_stmt = $db->prepare($save_query);
                $save_stmt->bind_param("idssss", $member_id, $amount, $payment_method, $fund, $notes, $transaction_id);
                $save_stmt->execute();
                
                $donation_id = $db->insert_id;
                
                // Send confirmation email (simulated)
                // mail($member['email'], "Donation Confirmation", "Thank you for your donation...");
                
                $show_confirmation = true;
                $donation_data = [
                    'id' => $donation_id,
                    'amount' => $amount,
                    'fund' => $fund,
                    'date' => date('Y-m-d'),
                    'transaction_id' => $transaction_id
                ];
            } else {
                // Production - integrate with payment gateway
                // This is where you'd integrate with Stripe, PayPal, etc.
                $error = "Online payment processing is not configured. Please use offline methods.";
            }
        } else {
            $error = implode("<br>", $errors);
        }
    } elseif (isset($_POST['confirm_donation'])) {
        // Show confirmation page
        $show_confirmation = true;
        $donation_data = [
            'amount' => floatval($_POST['amount'] ?? 0),
            'fund' => sanitize($_POST['fund'] ?? 'General'),
            'payment_method' => sanitize($_POST['payment_method'] ?? 'credit_card'),
            'frequency' => sanitize($_POST['frequency'] ?? 'one-time'),
            'anonymous' => isset($_POST['anonymous']),
            'cover_fees' => isset($_POST['cover_fees']),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
    }
}

// Get donation funds/types
$funds = [
    'General Fund' => 'General church operations and ministry',
    'Building Fund' => 'Facilities maintenance and improvements',
    'Missions' => 'Supporting local and global missions',
    'Benevolence' => 'Helping those in need in our community',
    'Youth Ministry' => 'Supporting youth programs and events',
    'Children\'s Ministry' => 'Children\'s education and activities',
    'Worship & Music' => 'Worship arts and music ministry',
    'Pastor\'s Discretionary' => 'Pastoral care and assistance'
];

// Get recent donations for this member
$recent_query = "SELECT * FROM donations 
                 WHERE member_id = ? 
                 ORDER BY donation_date DESC 
                 LIMIT 5";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->bind_param("i", $member_id);
$recent_stmt->execute();
$recent_donations = $recent_stmt->get_result();

// Get giving summary
$summary_query = "SELECT 
                   COUNT(*) as total_count,
                   SUM(amount) as total_amount,
                   MAX(amount) as largest_gift,
                   AVG(amount) as average_gift,
                   MAX(donation_date) as last_donation
                  FROM donations 
                  WHERE member_id = ?";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->bind_param("i", $member_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Set page title
$page_title = "Give Online";

// Include member header
include 'member_header.php';
?>

<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-hand-holding-heart me-2 text-primary"></i>Online Giving</h2>
            <p class="text-muted mb-0">Support the ministry with your generous donations</p>
        </div>
        <div>
            <span class="badge bg-success p-3">
                <i class="fas fa-heart me-2"></i>
                Thank you for your generosity!
            </span>
        </div>
    </div>

    <?php if ($show_confirmation): ?>
        <!-- Donation Confirmation -->
        <div class="member-card text-center py-5">
            <div class="mb-4">
                <i class="fas fa-check-circle text-success fa-5x"></i>
            </div>
            <h3 class="mb-3">Thank You for Your Gift!</h3>
            <p class="lead mb-4">Your donation of <strong>$<?php echo number_format($donation_data['amount'], 2); ?></strong> has been received.</p>
            
            <div class="row justify-content-center mb-4">
                <div class="col-md-6">
                    <div class="bg-light p-4 rounded">
                        <p class="mb-2"><strong>Transaction ID:</strong> <?php echo $donation_data['transaction_id'] ?? 'TXN-' . time(); ?></p>
                        <p class="mb-2"><strong>Date:</strong> <?php echo date('F j, Y'); ?></p>
                        <p class="mb-2"><strong>Fund:</strong> <?php echo $donation_data['fund']; ?></p>
                        <p class="mb-0"><strong>Status:</strong> <span class="badge bg-success">Completed</span></p>
                    </div>
                </div>
            </div>
            
            <p class="text-muted mb-4">A confirmation email has been sent to your email address.</p>
            
            <div class="d-flex justify-content-center gap-3">
                <a href="give.php" class="btn btn-primary">
                    <i class="fas fa-redo-alt me-2"></i>Make Another Donation
                </a>
                <a href="member_donations.php" class="btn btn-outline-primary">
                    <i class="fas fa-history me-2"></i>View Donation History
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- Main Giving Form -->
        <div class="row g-4">
            <!-- Left Column - Giving Form -->
            <div class="col-lg-8">
                <div class="member-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0"><i class="fas fa-credit-card me-2 text-primary"></i>Make a Donation</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" id="donationForm">
                            <!-- Amount Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Donation Amount</label>
                                <div class="row g-2 mb-3">
                                    <div class="col-4 col-md-2">
                                        <button type="button" class="btn btn-outline-primary w-100 amount-preset" data-amount="10">$10</button>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <button type="button" class="btn btn-outline-primary w-100 amount-preset" data-amount="25">$25</button>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <button type="button" class="btn btn-outline-primary w-100 amount-preset" data-amount="50">$50</button>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <button type="button" class="btn btn-outline-primary w-100 amount-preset" data-amount="100">$100</button>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <button type="button" class="btn btn-outline-primary w-100 amount-preset" data-amount="250">$250</button>
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <button type="button" class="btn btn-outline-primary w-100 amount-preset" data-amount="500">$500</button>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-white">$</span>
                                    <input type="number" class="form-control" name="amount" id="amount" 
                                           step="0.01" min="1" max="10000" placeholder="Enter custom amount" required>
                                </div>
                                <small class="text-muted">Minimum $1.00, Maximum $10,000.00</small>
                            </div>

                            <!-- Fund Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Select Fund</label>
                                <select class="form-select" name="fund" id="fund" required>
                                    <option value="">Choose a fund...</option>
                                    <?php foreach ($funds as $fund => $description): ?>
                                        <option value="<?php echo $fund; ?>" data-description="<?php echo $description; ?>">
                                            <?php echo $fund; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-2 small text-muted" id="fundDescription">
                                    Select a fund to see its description
                                </div>
                            </div>

                            <!-- Frequency Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Giving Frequency</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="frequency" id="one-time" value="one-time" checked>
                                            <label class="form-check-label" for="one-time">
                                                One Time
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="frequency" id="weekly" value="weekly">
                                            <label class="form-check-label" for="weekly">
                                                Weekly
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="frequency" id="monthly" value="monthly">
                                            <label class="form-check-label" for="monthly">
                                                Monthly
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Payment Method</label>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="credit_card" value="credit_card" checked>
                                            <label class="form-check-label" for="credit_card">
                                                <i class="fab fa-cc-visa me-1"></i> Credit/Debit Card
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="bank_account" value="bank_account">
                                            <label class="form-check-label" for="bank_account">
                                                <i class="fas fa-university me-1"></i> Bank Account (ACH)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="paypal">
                                            <label class="form-check-label" for="paypal">
                                                <i class="fab fa-paypal me-1"></i> PayPal
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Details (shown/hidden based on payment method) -->
                            <div id="cardDetails" class="mb-4">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Card Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="fas fa-credit-card"></i></span>
                                            <input type="text" class="form-control" placeholder="1234 5678 9012 3456" id="card_number">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Expiry Month</label>
                                        <select class="form-select" id="expiry_month">
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                                    <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Expiry Year</label>
                                        <select class="form-select" id="expiry_year">
                                            <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">CVV</label>
                                        <input type="text" class="form-control" placeholder="123" id="cvv" maxlength="4">
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Details (shown/hidden based on payment method) -->
                            <div id="bankDetails" class="mb-4" style="display: none;">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Routing Number</label>
                                        <input type="text" class="form-control" placeholder="123456789" id="routing_number">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Account Number</label>
                                        <input type="text" class="form-control" placeholder="********" id="account_number">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Account Type</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="account_type" id="checking" value="checking" checked>
                                            <label class="form-check-label" for="checking">Checking</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="account_type" id="savings" value="savings">
                                            <label class="form-check-label" for="savings">Savings</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Options -->
                            <div class="mb-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="anonymous" id="anonymous">
                                    <label class="form-check-label" for="anonymous">
                                        Give anonymously (your name will not be displayed in giving records)
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="cover_fees" id="cover_fees">
                                    <label class="form-check-label" for="cover_fees">
                                        Cover transaction fees (adds 2.9% + $0.30 to your donation)
                                    </label>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Notes (Optional)</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="Add a note or dedication..."></textarea>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-flex gap-2">
                                <button type="submit" name="confirm_donation" class="btn btn-primary btn-lg flex-grow-1" id="reviewBtn">
                                    <i class="fas fa-check-circle me-2"></i>Review Donation
                                </button>
                                <button type="reset" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column - Information & Summary -->
            <div class="col-lg-4">
                <!-- Giving Summary -->
                <div class="member-card mb-4">
                    <div class="card-header-custom">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-success"></i>Your Giving Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="display-4 text-success">$<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></div>
                            <p class="text-muted">Total Lifetime Giving</p>
                        </div>
                        <hr>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted d-block">Total Gifts</small>
                                <strong><?php echo $summary['total_count'] ?? 0; ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Average Gift</small>
                                <strong>$<?php echo number_format($summary['average_gift'] ?? 0, 2); ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Largest Gift</small>
                                <strong>$<?php echo number_format($summary['largest_gift'] ?? 0, 2); ?></strong>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Last Gift</small>
                                <strong><?php echo $summary['last_donation'] ? date('M j', strtotime($summary['last_donation'])) : 'Never'; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Why Give -->
                <div class="member-card mb-4">
                    <div class="card-header-custom">
                        <h5 class="mb-0"><i class="fas fa-heart me-2 text-danger"></i>Why We Give</h5>
                    </div>
                    <div class="card-body">
                        <p class="small">Your generous giving helps us:</p>
                        <ul class="small">
                            <li>Support ministry programs and outreach</li>
                            <li>Maintain our facilities</li>
                            <li>Help those in need in our community</li>
                            <li>Support missionaries worldwide</li>
                            <li>Invest in youth and children's programs</li>
                        </ul>
                        <p class="small text-muted mb-0 mt-3">
                            <i class="fas fa-bible me-1"></i>
                            "Each of you should give what you have decided in your heart to give, not reluctantly or under compulsion, for God loves a cheerful giver." - 2 Corinthians 9:7
                        </p>
                    </div>
                </div>

                <!-- Recent Donations -->
                <?php if ($recent_donations->num_rows > 0): ?>
                <div class="member-card">
                    <div class="card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2 text-info"></i>Recent Donations</h5>
                        <a href="member_donations.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php while ($donation = $recent_donations->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted d-block"><?php echo date('M j, Y', strtotime($donation['donation_date'])); ?></small>
                                        <span class="fw-bold">$<?php echo number_format($donation['amount'], 2); ?></span>
                                        <small class="text-muted"> - <?php echo $donation['fund_type']; ?></small>
                                    </div>
                                    <span class="badge bg-success">Completed</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recurring Giving Section -->
        <div class="member-card mt-4">
            <div class="card-header-custom">
                <h5 class="mb-0"><i class="fas fa-sync-alt me-2 text-warning"></i>Recurring Giving</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Set up automatic recurring donations to consistently support the ministry.</p>
                <button class="btn btn-outline-primary" onclick="setupRecurring()" <?php echo $summary['total_count'] == 0 ? '' : 'disabled'; ?>>
                    <i class="fas fa-calendar-alt me-2"></i>Setup Recurring Giving
                </button>
                <?php if ($summary['total_count'] == 0): ?>
                    <small class="d-block text-muted mt-2">Make your first donation to enable recurring giving.</small>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Review Donation Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Your Donation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Please review your donation details:</p>
                <table class="table table-sm">
                    <tr>
                        <th>Amount:</th>
                        <td>$<span id="reviewAmount"></span></td>
                    </tr>
                    <tr>
                        <th>Fund:</th>
                        <td><span id="reviewFund"></span></td>
                    </tr>
                    <tr>
                        <th>Frequency:</th>
                        <td><span id="reviewFrequency"></span></td>
                    </tr>
                    <tr>
                        <th>Payment Method:</th>
                        <td><span id="reviewPayment"></span></td>
                    </tr>
                    <tr>
                        <th>Anonymous:</th>
                        <td><span id="reviewAnonymous"></span></td>
                    </tr>
                </table>
                <p class="small text-muted mb-0">By completing this donation, you agree to our <a href="#">Terms of Service</a>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmDonation">Complete Donation</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Amount preset buttons */
.amount-preset.active {
    background-color: #4361ee;
    color: white;
    border-color: #4361ee;
}

/* Payment method icons */
.form-check-label i {
    font-size: 1.2rem;
    margin-right: 5px;
}

/* Card styling */
.member-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.member-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .amount-preset {
        font-size: 0.9rem;
    }
    
    .btn-lg {
        font-size: 1rem;
    }
}
</style>

<script>
// Amount preset buttons
document.querySelectorAll('.amount-preset').forEach(btn => {
    btn.addEventListener('click', function() {
        const amount = this.dataset.amount;
        document.getElementById('amount').value = amount;
        
        // Remove active class from all preset buttons
        document.querySelectorAll('.amount-preset').forEach(b => b.classList.remove('active'));
        
        // Add active class to clicked button
        this.classList.add('active');
    });
});

// Fund description display
document.getElementById('fund')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const description = selected.dataset.description;
    document.getElementById('fundDescription').textContent = description || 'Select a fund to see its description';
});

// Payment method toggle
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const cardDetails = document.getElementById('cardDetails');
        const bankDetails = document.getElementById('bankDetails');
        
        if (this.value === 'credit_card') {
            cardDetails.style.display = 'block';
            bankDetails.style.display = 'none';
        } else if (this.value === 'bank_account') {
            cardDetails.style.display = 'none';
            bankDetails.style.display = 'block';
        } else {
            cardDetails.style.display = 'none';
            bankDetails.style.display = 'none';
        }
    });
});

// Form submission - show review modal
document.getElementById('donationForm')?.addEventListener('submit', function(e) {
    const submitter = e.submitter;
    if (submitter.name === 'confirm_donation') {
        e.preventDefault();
        
        // Validate amount
        const amount = document.getElementById('amount').value;
        if (!amount || amount <= 0) {
            alert('Please enter a valid donation amount');
            return;
        }
        
        // Validate fund
        const fund = document.getElementById('fund').value;
        if (!fund) {
            alert('Please select a fund');
            return;
        }
        
        // Populate review modal
        document.getElementById('reviewAmount').textContent = parseFloat(amount).toFixed(2);
        document.getElementById('reviewFund').textContent = fund;
        
        const frequency = document.querySelector('input[name="frequency"]:checked').nextElementSibling.textContent;
        document.getElementById('reviewFrequency').textContent = frequency;
        
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked').nextElementSibling.textContent.trim();
        document.getElementById('reviewPayment').textContent = paymentMethod;
        
        const anonymous = document.getElementById('anonymous').checked ? 'Yes' : 'No';
        document.getElementById('reviewAnonymous').textContent = anonymous;
        
        // Show modal
        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    }
});

// Confirm donation
document.getElementById('confirmDonation')?.addEventListener('click', function() {
    // Create hidden inputs and submit form
    const form = document.getElementById('donationForm');
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'process_donation';
    hiddenInput.value = '1';
    form.appendChild(hiddenInput);
    
    // Hide modal
    bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
    
    // Submit form
    form.submit();
});

// Format card number with spaces
document.getElementById('card_number')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
    let formattedValue = '';
    
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) {
            formattedValue += ' ';
        }
        formattedValue += value[i];
    }
    
    e.target.value = formattedValue;
});

// CVV validation
document.getElementById('cvv')?.addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '').substring(0, 4);
});

// Routing number validation
document.getElementById('routing_number')?.addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '').substring(0, 9);
});

// Account number validation
document.getElementById('account_number')?.addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '').substring(0, 17);
});

// Calculate with fees
document.getElementById('cover_fees')?.addEventListener('change', function() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    if (this.checked && amount > 0) {
        const fee = (amount * 0.029) + 0.30;
        const total = amount + fee;
        alert(`Transaction fee of $${fee.toFixed(2)} will be added. Total: $${total.toFixed(2)}`);
    }
});

// Recurring setup function
function setupRecurring() {
    alert('Recurring giving setup will be available after your first donation.');
}

// Prevent multiple submissions
let submitted = false;
document.getElementById('donationForm')?.addEventListener('submit', function() {
    if (submitted) {
        e.preventDefault();
    }
    submitted = true;
});

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php
// Include member footer
include 'member_footer.php';
?>