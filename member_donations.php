<?php
// member_donations.php - Member Donations History
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

// Get all donations
$donations_query = "SELECT * FROM donations 
                    WHERE member_id = ? 
                    ORDER BY donation_date DESC";
$donations_stmt = $db->prepare($donations_query);
$donations_stmt->bind_param("i", $member_id);
$donations_stmt->execute();
$donations = $donations_stmt->get_result();

// Get donation summary by fund type
$summary_query = "SELECT 
                   fund_type,
                   COUNT(*) as count,
                   SUM(amount) as total
                  FROM donations 
                  WHERE member_id = ? 
                  GROUP BY fund_type";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->bind_param("i", $member_id);
$summary_stmt->execute();
$summary = $summary_stmt->get_result();

// Get yearly summary
$yearly_query = "SELECT 
                  YEAR(donation_date) as year,
                  COUNT(*) as count,
                  SUM(amount) as total
                 FROM donations 
                 WHERE member_id = ? 
                 GROUP BY YEAR(donation_date)
                 ORDER BY year DESC";
$yearly_stmt = $db->prepare($yearly_query);
$yearly_stmt->bind_param("i", $member_id);
$yearly_stmt->execute();
$yearly = $yearly_stmt->get_result();

// Set page title
$page_title = "My Donations";

// Include member header
include 'member_header.php';
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-hand-holding-heart me-2 text-primary"></i>My Donations</h2>
        <a href="give.php" class="btn btn-member-primary">
            <i class="fas fa-plus-circle me-2"></i>Make a Donation
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <?php
        $total_donations = 0;
        $total_amount = 0;
        $summary->data_seek(0);
        while ($row = $summary->fetch_assoc()) {
            $total_donations += $row['count'];
            $total_amount += $row['total'];
        }
        ?>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_donations; ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number text-success"><?php echo formatCurrency($total_amount); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-number"><?php echo $summary->num_rows; ?></div>
                <div class="stat-label">Fund Types</div>
            </div>
        </div>
    </div>

    <!-- Donation Tables -->
    <div class="row">
        <div class="col-lg-8">
            <div class="member-card">
                <div class="card-header-custom">
                    <h5 class="mb-0">Donation History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Fund</th>
                                    <th>Method</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($donations->num_rows > 0): ?>
                                    <?php while ($donation = $donations->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency($donation['amount']); ?></td>
                                            <td><?php echo $donation['fund_type']; ?></td>
                                            <td><?php echo $donation['payment_method']; ?></td>
                                            <td>
                                                <a href="donation_receipt.php?id=<?php echo $donation['donation_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5">
                                            <i class="fas fa-hand-holding-heart fa-3x text-muted mb-3"></i>
                                            <p class="mb-0">No donations yet</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Summary by Fund Type -->
            <div class="member-card mb-4">
                <div class="card-header-custom">
                    <h5 class="mb-0">By Fund Type</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php 
                        $summary->data_seek(0);
                        while ($row = $summary->fetch_assoc()): 
                        ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $row['fund_type']; ?></span>
                                    <span class="fw-bold"><?php echo formatCurrency($row['total']); ?></span>
                                </div>
                                <small class="text-muted"><?php echo $row['count']; ?> donations</small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Yearly Summary -->
            <div class="member-card">
                <div class="card-header-custom">
                    <h5 class="mb-0">By Year</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php while ($row = $yearly->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $row['year']; ?></span>
                                    <span class="fw-bold"><?php echo formatCurrency($row['total']); ?></span>
                                </div>
                                <small class="text-muted"><?php echo $row['count']; ?> donations</small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'member_footer.php';
?>