<?php
session_start();
require_once 'config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'employer') {
    header("Location: login.php");
    exit;
}

$plan_name = $_GET['plan'] ?? 'Basic Boost';
$amount = $_GET['price'] ?? '19.00';

// Handle Mock Payment Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Determine admin ID for notification
    $employer_id = $_SESSION['user_id'];
    try {
        $admin_stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_type = 'admin' ORDER BY user_id ASC LIMIT 1");
        $admin_stmt->execute();
        $admin_id = $admin_stmt->fetchColumn();

        if ($admin_id) {
            $notif = $pdo->prepare("INSERT INTO notifications (recipient_id, actor_id, type, reference_id) VALUES (?, ?, 'plan_purchase', 0)");
            $notif->execute([$admin_id, $employer_id]);
        }
        
        // Redirect to success/dashboard
        $_SESSION['success_message'] = "Purchase of $plan_name successful!";
        header("Location: employer_dashboard.php?tab=overview");
        exit;
    } catch (PDOException $e) {
        $error = "Payment failed: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    
    <!-- Progress Bar -->
    <div class="row justify-content-center mb-5">
        <div class="col-md-8">
            <div class="position-relative m-4">
                <div class="progress" style="height: 2px;">
                    <div class="progress-bar" role="progressbar" style="width: 50%;" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <button type="button" class="position-absolute top-0 start-0 translate-middle btn btn-sm btn-primary rounded-pill" style="width: 2rem; height:2rem;">1</button>
                <div class="position-absolute top-0 start-0 translate-middle-y mt-4 ms-n4 small fw-bold">Select Plan</div>
                
                <button type="button" class="position-absolute top-0 start-50 translate-middle btn btn-sm btn-primary rounded-pill" style="width: 2rem; height:2rem;">2</button>
                <div class="position-absolute top-0 start-50 translate-middle-y mt-4 ms-n3 small fw-bold">Checkout</div>
                
                <button type="button" class="position-absolute top-0 start-100 translate-middle btn btn-sm btn-secondary rounded-pill" style="width: 2rem; height:2rem;">3</button>
                <div class="position-absolute top-0 start-100 translate-middle-y mt-4 ms-n4 text-muted small">Done</div>
            </div>
        </div>
    </div>

    <div class="row g-5">
        <!-- Order Summary -->
        <div class="col-md-5 col-lg-4 order-md-last">
            <h4 class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-primary">Order Summary</span>
            </h4>
            <ul class="list-group mb-3 shadow-sm">
                <li class="list-group-item d-flex justify-content-between lh-sm">
                    <div>
                        <h6 class="my-0"><?php echo htmlspecialchars($plan_name); ?></h6>
                        <small class="text-muted">Subscription</small>
                    </div>
                    <span class="text-muted">$<?php echo htmlspecialchars($amount); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Total (USD)</span>
                    <strong>$<?php echo htmlspecialchars($amount); ?></strong>
                </li>
            </ul>
        </div>

        <!-- Checkout Form -->
        <div class="col-md-7 col-lg-8">
            <h4 class="mb-3">Checkout</h4>
            <form class="needs-validation" novalidate method="POST">
                
                <!-- 1. Customer Identity -->
                <h5 class="mb-3 text-muted border-bottom pb-2">1. Customer Identity</h5>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="fullName" class="form-label">Cardholder Name</label>
                        <input type="text" class="form-control" id="fullName" placeholder="Full Name as it appears on card" required value="<?php echo htmlspecialchars($_SESSION['username']); ?>">
                        <div class="invalid-feedback">Valid full name is required.</div>
                    </div>
                    
                    <div class="col-12">
                        <label for="email" class="form-label">Email Address <span class="text-muted">(for receipt)</span></label>
                        <input type="email" class="form-control" id="email" placeholder="you@example.com" required>
                        <div class="invalid-feedback">Please enter a valid email address for shipping updates.</div>
                    </div>
                     <div class="col-12">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" placeholder="+1 (555) 123-4567">
                    </div>
                </div>

                <hr class="my-4">

                <!-- 2. Billing Address -->
                <h5 class="mb-3 text-muted border-bottom pb-2">2. Billing Address</h5>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="address" class="form-label">Street Address</label>
                        <input type="text" class="form-control" id="address" placeholder="1234 Main St" required>
                        <div class="invalid-feedback">Please enter your shipping address.</div>
                    </div>

                    <div class="col-12">
                        <label for="address2" class="form-label">Apt/Suite/Unit <span class="text-muted">(Optional)</span></label>
                        <input type="text" class="form-control" id="address2" placeholder="Apartment or suite">
                    </div>

                    <div class="col-md-5">
                        <label for="country" class="form-label">Country / Region</label>
                        <select class="form-select" id="country" required>
                            <option value="">Choose...</option>
                            <option>Australia</option>
                            <option>United States</option>
                            <option>United Kingdom</option>
                            <option>Canada</option>
                        </select>
                        <div class="invalid-feedback">Please select a valid country.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="state" class="form-label">State / Province</label>
                        <select class="form-select" id="state" required>
                            <option value="">Choose...</option>
                            <option>NSW</option>
                            <option>VIC</option>
                            <option>QLD</option>
                            <option>WA</option>
                            <option>SA</option>
                        </select>
                        <div class="invalid-feedback">Please provide a valid state.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="zip" class="form-label">ZIP / Postal Code</label>
                        <input type="text" class="form-control" id="zip" placeholder="" required>
                        <div class="invalid-feedback">Zip code required.</div>
                    </div>
                    
                     <div class="col-12">
                        <label for="city" class="form-label">City / Suburb</label>
                        <input type="text" class="form-control" id="city" required>
                    </div>
                </div>

                <hr class="my-4">
                
                <!-- Convenience Toggles -->
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="same-address">
                    <label class="form-check-label" for="same-address">Billing address is the same as shipping</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="save-info">
                    <label class="form-check-label" for="save-info">Save my information for a faster checkout next time</label>
                </div>

                <hr class="my-4">

                <!-- 3. Payment Details -->
                <h5 class="mb-3 text-muted border-bottom pb-2">3. Payment Instrument Details</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="cc-number" class="form-label">Card Number</label>
                        <input type="text" class="form-control" id="cc-number" placeholder="0000 0000 0000 0000" required pattern="\d{16}">
                        <div class="invalid-feedback">Credit card number is required (16 digits).</div>
                    </div>

                    <div class="col-md-3">
                        <label for="cc-expiration" class="form-label">Expiration</label>
                        <input type="text" class="form-control" id="cc-expiration" placeholder="MM/YY" required pattern="\d{2}/\d{2}">
                        <div class="invalid-feedback">Expiration date required.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="cc-cvv" class="form-label">CVV</label>
                        <input type="text" class="form-control" id="cc-cvv" placeholder="123" required pattern="\d{3,4}">
                        <div class="invalid-feedback">Security code required.</div>
                    </div>
                </div>

                <hr class="my-5">

                <button class="w-100 btn btn-primary btn-lg" type="submit">Complete Purchase</button>
            </form>
        </div>
    </div>
</div>

<script>
// Example starter JavaScript for disabling form submissions if there are invalid fields
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>

<?php include 'includes/footer.php'; ?>
