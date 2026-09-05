<?php
require_once './config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['employee_id']) && !empty($_SESSION['employee_id']);
$user_data = null;
$current_subscription = null;

if ($is_logged_in) {
    try {
        $user_stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND is_active = 1");
        $user_stmt->execute([$_SESSION['employee_id']]);
        $user_data = $user_stmt->fetch();
        
        // Get current subscription details if any
        if ($user_data && !empty($user_data['subscription_id'])) {
            $sub_stmt = $pdo->prepare("
                SELECT * FROM employee_subscriptions_plans 
                WHERE id = ? AND is_active = 1
            ");
            $sub_stmt->execute([$user_data['subscription_id']]);
            $current_subscription = $sub_stmt->fetch();
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

// Get Razorpay keys from admin_settings
$razorpay_key_id = '';
$razorpay_key_secret = '';
try {
    $settings_stmt = $pdo->prepare("SELECT razorpay_key_id, razorpay_key_secret FROM admin_settings WHERE id = 1");
    $settings_stmt->execute();
    $settings = $settings_stmt->fetch();
    if ($settings) {
        $razorpay_key_id = $settings['razorpay_key_id'];
        $razorpay_key_secret = $settings['razorpay_key_secret'];
    }
} catch (PDOException $e) {
    error_log("Error fetching Razorpay settings: " . $e->getMessage());
}

// Handle AJAX order creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order_ajax']) && $is_logged_in) {
    header('Content-Type: application/json');
    
    $plan_id = intval($_POST['plan_id'] ?? 0);
    
    if ($plan_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid plan selected.']);
        exit;
    }
    
    if (empty($razorpay_key_id) || empty($razorpay_key_secret)) {
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured.']);
        exit;
    }
    
    try {
        // Fetch plan details
        $plan_stmt = $pdo->prepare("SELECT * FROM employee_subscriptions_plans WHERE id = ? AND is_active = 1");
        $plan_stmt->execute([$plan_id]);
        $plan = $plan_stmt->fetch();
        
        if (!$plan) {
            echo json_encode(['success' => false, 'message' => 'Plan not found.']);
            exit;
        }
        
        // Create Razorpay Order
        $amount = $plan['price_inr'] * 100; // Convert to paise
        $currency = 'INR';
        
        $order_data = [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => 'order_' . uniqid(),
            'payment_capture' => 1
        ];
        
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ':' . $razorpay_key_secret);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing, remove in production
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            echo json_encode(['success' => false, 'message' => 'CURL Error: ' . curl_error($ch)]);
            curl_close($ch);
            exit;
        }
        
        curl_close($ch);
        
        if ($http_code == 200) {
            $order = json_decode($response, true);
            $razorpay_order_id = $order['id'];
            
            // Store order in database
            $insert_payment = $pdo->prepare("
                INSERT INTO employee_payments (
                    employee_id, subscription_id, razorpay_order_id, amount, currency, status
                ) VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $insert_payment->execute([
                $_SESSION['employee_id'], 
                $plan_id, 
                $razorpay_order_id,
                $plan['price_inr'],
                $currency
            ]);
            
            echo json_encode([
                'success' => true, 
                'order_id' => $razorpay_order_id,
                'amount' => $amount,
                'currency' => $currency,
                'plan_name' => $plan['plan_name']
            ]);
        } else {
            $error_response = json_decode($response, true);
            $error_message = $error_response['error']['description'] ?? 'Failed to create order.';
            echo json_encode(['success' => false, 'message' => $error_message]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle subscription purchase with Razorpay (non-AJAX fallback)
$purchase_success = false;
$purchase_error = '';
$selected_plan = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order']) && $is_logged_in) {
    $plan_id = intval($_POST['plan_id'] ?? 0);
    
    if ($plan_id <= 0) {
        $purchase_error = 'Invalid plan selected.';
    } elseif (empty($razorpay_key_id) || empty($razorpay_key_secret)) {
        $purchase_error = 'Payment gateway not configured. Please contact support.';
    } else {
        try {
            // Fetch plan details
            $plan_stmt = $pdo->prepare("SELECT * FROM employee_subscriptions_plans WHERE id = ? AND is_active = 1");
            $plan_stmt->execute([$plan_id]);
            $plan = $plan_stmt->fetch();
            
            if (!$plan) {
                $purchase_error = 'Plan not found or inactive.';
            } else {
                // Create Razorpay Order
                $amount = $plan['price_inr'] * 100;
                $currency = 'INR';
                
                $order_data = [
                    'amount' => $amount,
                    'currency' => $currency,
                    'receipt' => 'order_' . uniqid(),
                    'payment_capture' => 1
                ];
                
                $ch = curl_init('https://api.razorpay.com/v1/orders');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ':' . $razorpay_key_secret);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code == 200) {
                    $order = json_decode($response, true);
                    $_SESSION['razorpay_order_id'] = $order['id'];
                    $_SESSION['razorpay_plan_id'] = $plan_id;
                    
                    // Store order in database
                    $insert_payment = $pdo->prepare("
                        INSERT INTO employee_payments (
                            employee_id, subscription_id, razorpay_order_id, amount, currency, status
                        ) VALUES (?, ?, ?, ?, ?, 'pending')
                    ");
                    $insert_payment->execute([
                        $_SESSION['employee_id'], 
                        $plan_id, 
                        $order['id'],
                        $plan['price_inr'],
                        $currency
                    ]);
                    
                    // Redirect to payment page with order ID
                    header('Location: payment-checkout.php?order_id=' . $order['id']);
                    exit;
                } else {
                    $purchase_error = 'Failed to create payment order. Please try again.';
                }
            }
        } catch (Exception $e) {
            $purchase_error = 'Payment processing error. Please try again.';
            error_log("Razorpay order creation error: " . $e->getMessage());
        }
    }
}

// Handle Razorpay payment verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
    $razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
    $razorpay_signature = $_POST['razorpay_signature'] ?? '';
    
    if (!empty($razorpay_order_id) && !empty($razorpay_payment_id) && !empty($razorpay_signature)) {
        try {
            // Verify signature
            $generated_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $razorpay_key_secret);
            
            if ($generated_signature === $razorpay_signature) {
                // Get payment details
                $payment_stmt = $pdo->prepare("
                    SELECT * FROM employee_payments 
                    WHERE razorpay_order_id = ? AND status = 'pending'
                    ORDER BY id DESC LIMIT 1
                ");
                $payment_stmt->execute([$razorpay_order_id]);
                $payment = $payment_stmt->fetch();
                
                if ($payment) {
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Update payment record
                    $update_payment = $pdo->prepare("
                        UPDATE employee_payments 
                        SET razorpay_payment_id = ?, 
                            razorpay_signature = ?, 
                            status = 'success',
                            payment_date = NOW()
                        WHERE razorpay_order_id = ?
                    ");
                    $update_payment->execute([$razorpay_payment_id, $razorpay_signature, $razorpay_order_id]);
                    
                    // Get plan details
                    $plan_stmt = $pdo->prepare("SELECT * FROM employee_subscriptions_plans WHERE id = ?");
                    $plan_stmt->execute([$payment['subscription_id']]);
                    $plan = $plan_stmt->fetch();
                    
                    // Update user subscription
                    $start_date = date('Y-m-d H:i:s');
                    $expiry_date = date('Y-m-d H:i:s', strtotime("+{$plan['duration_months']} months"));
                    
                    $update_user = $pdo->prepare("
                        UPDATE employees 
                        SET subscription_id = ?,
                            subscription_start_date = ?,
                            subscription_expiry_date = ?,
                            subscription_status = 'active',
                            job_views_used = 0,
                            mockup_tests_used = 0,
                            payment_status = 'completed',
                            razorpay_order_id = ?,
                            razorpay_payment_id = ?
                        WHERE id = ?
                    ");
                    $update_user->execute([
                        $plan['id'],
                        $start_date,
                        $expiry_date,
                        $razorpay_order_id,
                        $razorpay_payment_id,
                        $_SESSION['employee_id']
                    ]);
                    
                    $pdo->commit();
                    
                    $purchase_success = true;
                    $selected_plan = $plan;
                    
                    // Update session
                    $_SESSION['subscription_status'] = 'active';
                    $_SESSION['subscription_expiry'] = $expiry_date;
                    
                }
            } else {
                $purchase_error = 'Payment verification failed. Please contact support.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $purchase_error = 'Payment processing error. Please try again.';
            error_log("Payment verification error: " . $e->getMessage());
        }
    }
}

// Fetch all active subscription plans
try {
    $stmt = $pdo->prepare("
        SELECT * FROM employee_subscriptions_plans 
        WHERE is_active = 1 
        ORDER BY sort_order ASC, price_inr ASC
    ");
    $stmt->execute();
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    $plans = [];
    error_log("Error fetching subscription plans: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Subscription Plans - JobHub</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Razorpay SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        .plan-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .plan-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        .plan-card.featured {
            border-color: #4f46e5;
            position: relative;
        }
        .plan-card.featured::before {
            content: 'Popular';
            position: absolute;
            top: -12px;
            right: 20px;
            background: #4f46e5;
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .feature-check {
            color: #10b981;
        }
        
        /* Success Popup */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .popup-overlay.active {
            display: flex;
        }
        .popup-content {
            background: white;
            border-radius: 24px;
            padding: 40px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: slideUp 0.4s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .popup-check {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: scaleIn 0.5s ease;
        }
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        .popup-check svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        /* Loading spinner */
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4f46e5;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body>

  

    <!-- Success Popup -->
    <div class="popup-overlay <?php echo $purchase_success ? 'active' : ''; ?>" id="successPopup">
        <div class="popup-content">
            <div class="popup-check">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h3 class="text-2xl font-extrabold text-slate-900">Payment Successful!</h3>
            <p class="mt-2 text-slate-600">
                You've successfully subscribed to the <strong><?php echo htmlspecialchars($selected_plan['plan_name'] ?? ''); ?></strong> plan.
            </p>
            <p class="mt-1 text-sm text-slate-500">
                Your subscription is valid for <?php echo $selected_plan['duration_months'] ?? 1; ?> month(s).
            </p>
            <button onclick="closePopup()" class="mt-6 w-full rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-indigo-700">
                Great, Thanks!
            </button>
        </div>
    </div>

    <main class="py-16">
        <div class="mx-auto max-w-7xl px-5 lg:px-8">

            <!-- Page Header -->
            <div class="text-center mb-12">
                <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">
                    Pricing Plans
                </p>
                <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
                    Choose Your Subscription
                </h1>
                <p class="mt-3 mx-auto max-w-xl text-slate-500">
                    Unlock premium features to accelerate your job search and career growth.
                </p>
            </div>

            <?php if ($purchase_error): ?>
                <div class="mb-8 rounded-2xl bg-red-50 border border-red-200 p-6 max-w-2xl mx-auto">
                    <i data-lucide="alert-circle" class="inline h-5 w-5 text-red-500 mr-2"></i>
                    <span class="text-red-700"><?php echo htmlspecialchars($purchase_error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$is_logged_in): ?>
                <!-- Not Logged In -->
                <div class="max-w-2xl mx-auto rounded-2xl border border-slate-200 bg-white p-8 text-center">
                    <i data-lucide="lock" class="mx-auto h-16 w-16 text-slate-300"></i>
                    <h3 class="mt-4 text-xl font-bold text-slate-900">Sign in to Subscribe</h3>
                    <p class="mt-2 text-slate-500">Please sign in or create an account to purchase a subscription plan.</p>
                    <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="signin.php?redirect=subscription-plans.php" 
                           class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                            Sign In
                        </a>
                        <a href="signup.php" 
                           class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Create Account
                        </a>
                    </div>
                </div>
            <?php elseif ($user_data && $user_data['subscription_status'] == 'active' && strtotime($user_data['subscription_expiry_date']) > time()): ?>
                <!-- Active Subscription -->
                <div class="max-w-2xl mx-auto rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center">
                    <i data-lucide="check-circle" class="mx-auto h-16 w-16 text-emerald-500"></i>
                    <h3 class="mt-4 text-xl font-bold text-emerald-800">Active Subscription</h3>
                    <p class="mt-2 text-emerald-700">
                        You are currently on the <strong><?php echo htmlspecialchars($current_subscription['plan_name'] ?? 'Premium'); ?></strong> plan.
                    </p>
                    <p class="text-sm text-emerald-600">
                        Expires: <?php echo date('M d, Y', strtotime($user_data['subscription_expiry_date'])); ?>
                    </p>
                    <?php if ($current_subscription): ?>
                        <div class="mt-4 grid grid-cols-2 gap-3 max-w-sm mx-auto">
                            <div class="bg-white rounded-xl p-3">
                                <p class="text-xs text-slate-500">Job Views</p>
                                <p class="font-bold text-slate-900"><?php echo $user_data['job_views_used'] ?? 0; ?>/<?php echo $current_subscription['job_views_limit'] ?? 0; ?></p>
                            </div>
                            <div class="bg-white rounded-xl p-3">
                                <p class="text-xs text-slate-500">Mockup Tests</p>
                                <p class="font-bold text-slate-900"><?php echo $user_data['mockup_tests_used'] ?? 0; ?>/<?php echo $current_subscription['mockup_tests_limit'] ?? 0; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <p class="mt-4 text-sm text-emerald-600">
                        You can upgrade or change your plan at any time.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Subscription Plans -->
            <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">

                <?php if (!empty($plans)): ?>
                    <?php foreach ($plans as $index => $plan): ?>
                        <?php 
                        $is_featured = $plan['is_featured'] == 1;
                        $is_current = $is_logged_in && $user_data && $user_data['subscription_id'] == $plan['id'] && 
                                     $user_data['subscription_status'] == 'active' &&
                                     strtotime($user_data['subscription_expiry_date']) > time();
                        
                        // Parse features
                        $features = [];
                        if (!empty($plan['features'])) {
                            $features = explode("\n", $plan['features']);
                            $features = array_map('trim', $features);
                            $features = array_filter($features);
                        }
                        ?>

                        <div class="plan-card <?php echo $is_featured ? 'featured' : ''; ?> rounded-2xl border-2 border-slate-200 bg-white p-8 <?php echo $is_current ? 'border-emerald-500' : ($is_featured ? 'border-indigo-500' : ''); ?>">
                            
                            <?php if ($is_current): ?>
                                <div class="mb-4 inline-block rounded-full bg-emerald-100 px-4 py-1 text-xs font-bold text-emerald-700">
                                    Current Plan
                                </div>
                            <?php endif; ?>

                            <h3 class="text-2xl font-extrabold text-slate-900">
                                <?php echo htmlspecialchars($plan['plan_name']); ?>
                            </h3>
                            
                            <p class="mt-2 text-sm text-slate-500">
                                <?php echo htmlspecialchars($plan['short_description'] ?? ''); ?>
                            </p>

                            <div class="mt-4 flex items-baseline">
                                <span class="text-4xl font-extrabold text-slate-900">
                                    ₹<?php echo number_format($plan['price_inr'], 0); ?>
                                </span>
                                <span class="ml-2 text-sm text-slate-500">
                                    / <?php echo $plan['duration_months']; ?> month<?php echo $plan['duration_months'] > 1 ? 's' : ''; ?>
                                </span>
                            </div>

                            <ul class="mt-6 space-y-3 text-sm">
                                <?php foreach ($features as $feature): ?>
                                    <li class="flex items-start gap-2">
                                        <i data-lucide="check" class="feature-check h-5 w-5 flex-shrink-0 mt-0.5"></i>
                                        <span class="text-slate-600"><?php echo htmlspecialchars($feature); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($is_current): ?>
                                <button disabled class="mt-8 w-full rounded-xl bg-emerald-500 px-6 py-3.5 text-sm font-bold text-white cursor-not-allowed opacity-75">
                                    <i data-lucide="check-circle" class="inline h-4 w-4 mr-2"></i>
                                    Active Plan
                                </button>
                            <?php elseif ($is_logged_in && !empty($razorpay_key_id)): ?>
                                <button onclick="createRazorpayOrder(<?php echo $plan['id']; ?>, <?php echo $plan['price_inr'] * 100; ?>)" 
                                        id="subscribeBtn_<?php echo $plan['id']; ?>"
                                        class="mt-8 w-full rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-indigo-700 shadow-lg shadow-indigo-200">
                                    <i data-lucide="credit-card" class="inline h-4 w-4 mr-2"></i>
                                    Pay with Razorpay
                                </button>
                            <?php elseif ($is_logged_in && empty($razorpay_key_id)): ?>
                                <button disabled class="mt-8 w-full rounded-xl bg-slate-400 px-6 py-3.5 text-sm font-bold text-white cursor-not-allowed opacity-75">
                                    Payment Not Configured
                                </button>
                            <?php else: ?>
                                <a href="signin.php?redirect=subscription-plans.php" 
                                   class="mt-8 block w-full rounded-xl bg-indigo-600 px-6 py-3.5 text-center text-sm font-bold text-white transition hover:bg-indigo-700">
                                    Sign in to Subscribe
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
                        <i data-lucide="credit-card" class="mx-auto h-12 w-12 text-slate-300"></i>
                        <h3 class="mt-4 font-bold text-slate-900">No Plans Available</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Subscription plans will be available soon. Please check back later.
                        </p>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Plan Features Comparison -->
            <?php if (!empty($plans) && count($plans) > 1): ?>
                <div class="mt-16">
                    <h2 class="text-center text-2xl font-extrabold tracking-tight sm:text-3xl">
                        Compare Plans
                    </h2>
                    <div class="mt-8 overflow-x-auto">
                        <table class="w-full min-w-[600px] rounded-2xl border border-slate-200 bg-white">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50">
                                    <th class="px-6 py-4 text-left text-sm font-bold text-slate-600">Features</th>
                                    <?php foreach ($plans as $plan): ?>
                                        <th class="px-6 py-4 text-center text-sm font-bold text-slate-900">
                                            <?php echo htmlspecialchars($plan['plan_name']); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-slate-100">
                                    <td class="px-6 py-3 text-sm text-slate-600">Price</td>
                                    <?php foreach ($plans as $plan): ?>
                                        <td class="px-6 py-3 text-center text-sm font-semibold text-slate-900">
                                            ₹<?php echo number_format($plan['price_inr'], 0); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr class="border-b border-slate-100">
                                    <td class="px-6 py-3 text-sm text-slate-600">Duration</td>
                                    <?php foreach ($plans as $plan): ?>
                                        <td class="px-6 py-3 text-center text-sm text-slate-700">
                                            <?php echo $plan['duration_months']; ?> month<?php echo $plan['duration_months'] > 1 ? 's' : ''; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr class="border-b border-slate-100">
                                    <td class="px-6 py-3 text-sm text-slate-600">Job Views</td>
                                    <?php foreach ($plans as $plan): ?>
                                        <td class="px-6 py-3 text-center text-sm text-slate-700">
                                            <?php echo $plan['job_views_limit'] > 0 ? $plan['job_views_limit'] : 'Unlimited'; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr class="border-b border-slate-100">
                                    <td class="px-6 py-3 text-sm text-slate-600">Mockup Tests</td>
                                    <?php foreach ($plans as $plan): ?>
                                        <td class="px-6 py-3 text-center text-sm text-slate-700">
                                            <?php echo $plan['mockup_tests_limit'] > 0 ? $plan['mockup_tests_limit'] : 'Unlimited'; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr class="border-b border-slate-100">
                                    <td class="px-6 py-3 text-sm text-slate-600">Resume Review</td>
                                    <?php foreach ($plans as $plan): ?>
                                        <td class="px-6 py-3 text-center">
                                            <?php if ($plan['has_resume_review']): ?>
                                                <i data-lucide="check" class="inline h-5 w-5 text-emerald-500"></i>
                                            <?php else: ?>
                                                <i data-lucide="x" class="inline h-5 w-5 text-slate-300"></i>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr class="border-b border-slate-100">
                                    <td class="px-6 py-3 text-sm text-slate-600">Interview Coaching</td>
                                    <?php foreach ($plans as $plan): ?>
                                        <td class="px-6 py-3 text-center">
                                            <?php if ($plan['has_interview_coaching']): ?>
                                                <i data-lucide="check" class="inline h-5 w-5 text-emerald-500"></i>
                                            <?php else: ?>
                                                <i data-lucide="x" class="inline h-5 w-5 text-slate-300"></i>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <td class="px-6 py-3 text-sm text-slate-600">Priority Support</td>
                                    <?php foreach ($plans as $plan): ?>
                                        <td class="px-6 py-3 text-center">
                                            <?php if ($plan['has_priority_support']): ?>
                                                <i data-lucide="check" class="inline h-5 w-5 text-emerald-500"></i>
                                            <?php else: ?>
                                                <i data-lucide="x" class="inline h-5 w-5 text-slate-300"></i>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        lucide.createIcons();
        
        function closePopup() {
            document.getElementById('successPopup').classList.remove('active');
            window.location.href = 'subscription-plans.php';
        }
        
        document.getElementById('successPopup').addEventListener('click', function(e) {
            if (e.target === this) {
                closePopup();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePopup();
            }
        });
        
        // Razorpay Payment Handler with AJAX
        function createRazorpayOrder(planId, amount) {
            const submitBtn = document.getElementById('subscribeBtn_' + planId);
            
            // Show loading state
            submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
            submitBtn.disabled = true;
            
            // Create order via AJAX
            fetch('subscription-plans.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'plan_id=' + planId + '&create_order_ajax=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Open Razorpay checkout
                    var options = {
                        key: '<?php echo $razorpay_key_id; ?>',
                        amount: data.amount,
                        currency: 'INR',
                        name: 'JobHub',
                        description: 'Subscription Plan - ' + data.plan_name,
                        order_id: data.order_id,
                        handler: function(response) {
                            // Verify payment
                            var verifyForm = document.createElement('form');
                            verifyForm.method = 'POST';
                            verifyForm.action = 'subscription-plans.php';
                            
                            var inputs = {
                                'verify_payment': '1',
                                'razorpay_order_id': response.razorpay_order_id,
                                'razorpay_payment_id': response.razorpay_payment_id,
                                'razorpay_signature': response.razorpay_signature
                            };
                            
                            for (var key in inputs) {
                                var input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = key;
                                input.value = inputs[key];
                                verifyForm.appendChild(input);
                            }
                            
                            document.body.appendChild(verifyForm);
                            verifyForm.submit();
                        },
                        prefill: {
                            name: '<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>',
                            email: '<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>',
                            contact: '<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>'
                        },
                        theme: {
                            color: '#4f46e5'
                        },
                        modal: {
                            ondismiss: function() {
                                submitBtn.innerHTML = '<i data-lucide="credit-card" class="inline h-4 w-4 mr-2"></i> Pay with Razorpay';
                                submitBtn.disabled = false;
                                lucide.createIcons();
                            }
                        }
                    };
                    
                    var rzp = new Razorpay(options);
                    rzp.open();
                } else {
                    // Error creating order
                    alert(data.message || 'Failed to create payment order. Please try again.');
                    submitBtn.innerHTML = '<i data-lucide="credit-card" class="inline h-4 w-4 mr-2"></i> Pay with Razorpay';
                    submitBtn.disabled = false;
                    lucide.createIcons();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment processing error. Please try again.');
                submitBtn.innerHTML = '<i data-lucide="credit-card" class="inline h-4 w-4 mr-2"></i> Pay with Razorpay';
                submitBtn.disabled = false;
                lucide.createIcons();
            });
        }
    </script>
        <script src="<?= MAIN_URL ?>javascript/main.js"></script>

</body>
</html>