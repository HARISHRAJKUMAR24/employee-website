<?php
require_once './config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define upload path using MAIN_URL
define('UPLOAD_URL', MAIN_URL . 'uploads/resumes/');
define('UPLOAD_PATH', dirname(__DIR__) . '/company.job.panel/uploads/resumes/');

// Get job ID from URL
$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($job_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch job details with company information
try {
    $stmt = $pdo->prepare("
        SELECT 
            j.*,
            c.company_name,
            c.logo as company_logo,
            c.company_id as company_code,
            c.industry,
            c.company_size,
            c.description as company_description,
            c.website,
            c.city,
            c.state,
            c.country,
            c.phone as company_phone,
            c.email as company_email
        FROM jobs_post j
        INNER JOIN companies c ON j.company_id = c.id
        WHERE j.id = ? AND j.status = 'active'
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    if (!$job) {
        header('Location: index.php');
        exit;
    }

    // Get company ID for reference
    $company_id = $job['company_id'];
    $company_code = $job['company_code'];

} catch (PDOException $e) {
    error_log("Error fetching job details: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Get logged in user data
$user_data = null;
$is_logged_in = isset($_SESSION['employee_id']) && !empty($_SESSION['employee_id']);
$subscription_plan = null;
$has_reached_limit = false;
$limit_message = '';
$job_views_limit = 0;
$job_views_used = 0;
$can_view_job = false;

if ($is_logged_in) {
    try {
        $user_stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND is_active = 1");
        $user_stmt->execute([$_SESSION['employee_id']]);
        $user_data = $user_stmt->fetch();
        
        // Check if user has active subscription and get limits
        if ($user_data && $user_data['subscription_status'] == 'active' && strtotime($user_data['subscription_expiry_date']) > time()) {
            // Get subscription plan details
            $sub_stmt = $pdo->prepare("
                SELECT * FROM employee_subscriptions_plans 
                WHERE id = ? AND is_active = 1
            ");
            $sub_stmt->execute([$user_data['subscription_id']]);
            $subscription_plan = $sub_stmt->fetch();
            
            if ($subscription_plan) {
                // Get job views limit and used
                $job_views_limit = $subscription_plan['job_views_limit'] ?? 0;
                $job_views_used = $user_data['job_views_used'] ?? 0;
                
                // Check if user has remaining views
                if ($job_views_limit > 0 && $job_views_used >= $job_views_limit) {
                    $has_reached_limit = true;
                    $limit_message = 'You have reached your job view limit (' . $job_views_limit . '). Please upgrade your subscription to continue.';
                } else {
                    // User can view this job
                    $can_view_job = true;
                    
                    // Increment job views
                    $update_views = $pdo->prepare("
                        UPDATE employees 
                        SET job_views_used = job_views_used + 1 
                        WHERE id = ?
                    ");
                    $update_views->execute([$_SESSION['employee_id']]);
                    
                    // Update local variable
                    $job_views_used = $job_views_used + 1;
                }
            }
        } elseif ($user_data) {
            // No active subscription
            $has_reached_limit = true;
            $limit_message = 'You need an active subscription to view job details and apply. Please subscribe to continue.';
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

// Check if user has already applied (using email from session or user data)
$has_applied = false;
$existing_application = null;
$applicant_email = '';

if ($is_logged_in && $user_data) {
    $applicant_email = $user_data['email'];
} elseif (isset($_SESSION['applicant_email'])) {
    $applicant_email = $_SESSION['applicant_email'];
}

if (!empty($applicant_email)) {
    try {
        $check_stmt = $pdo->prepare("
            SELECT * FROM job_applications 
            WHERE job_id = ? AND applicant_email = ?
            ORDER BY applied_at DESC LIMIT 1
        ");
        $check_stmt->execute([$job_id, $applicant_email]);
        $existing_application = $check_stmt->fetch();
        $has_applied = !empty($existing_application);
    } catch (PDOException $e) {
        error_log("Error checking application: " . $e->getMessage());
    }
}

// Handle application submission
$application_success = false;
$application_error = '';
$show_success_popup = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    // Check if user is logged in
    if (!$is_logged_in) {
        $_SESSION['redirect_after_login'] = 'job-details.php?id=' . $job_id;
        header('Location: signin.php?message=login_to_apply');
        exit;
    }

    // Check if user has reached limit
    if (!$can_view_job) {
        $application_error = $limit_message;
    } elseif ($has_applied) {
        $application_error = 'You have already applied for this position.';
    } else {
        $applicant_name = trim($_POST['applicant_name'] ?? $user_data['full_name'] ?? '');
        $applicant_email = trim($_POST['applicant_email'] ?? $user_data['email'] ?? '');
        $applicant_phone = trim($_POST['applicant_phone'] ?? $user_data['phone'] ?? '');
        $cover_letter = trim($_POST['cover_letter'] ?? '');
        $experience_years = intval($_POST['experience_years'] ?? $user_data['experience_years'] ?? 0);
        $current_company = trim($_POST['current_company'] ?? $user_data['current_company'] ?? '');
        $current_position = trim($_POST['current_position'] ?? $user_data['current_position'] ?? '');

        // Validate required fields
        if (empty($applicant_name) || empty($applicant_email)) {
            $application_error = 'Name and email are required fields.';
        } elseif (!filter_var($applicant_email, FILTER_VALIDATE_EMAIL)) {
            $application_error = 'Please enter a valid email address.';
        } elseif (!isset($_FILES['resume_file']) || $_FILES['resume_file']['error'] !== UPLOAD_ERR_OK) {
            $application_error = 'Please upload your resume.';
        } else {
            // Process file upload - Use MAIN_URL path
            $upload_dir = dirname(__DIR__) . '/company.job.panel/uploads/resumes/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['resume_file']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['pdf', 'doc', 'docx'];
            
            if (!in_array(strtolower($file_extension), $allowed_extensions)) {
                $application_error = 'Only PDF, DOC, and DOCX files are allowed.';
            } else {
                $file_name = uniqid('resume_') . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                $file_size = $_FILES['resume_file']['size'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if ($file_size > $max_size) {
                    $application_error = 'File size must be less than 5MB.';
                } elseif (move_uploaded_file($_FILES['resume_file']['tmp_name'], $file_path)) {
                    // Store relative path for database (relative to employees.website)
                    $db_file_path = 'uploads/resumes/' . $file_name;
                    
                    // Insert application into database
                    try {
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO job_applications (
                                job_id, company_id, applicant_name, applicant_email, 
                                applicant_phone, resume_file, cover_letter, 
                                experience_years, current_company, current_position
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $insert_stmt->execute([
                            $job_id, $company_id, $applicant_name, $applicant_email,
                            $applicant_phone, $db_file_path, $cover_letter,
                            $experience_years, $current_company, $current_position
                        ]);

                        // Store applicant email in session
                        $_SESSION['applicant_email'] = $applicant_email;
                        $application_success = true;
                        $show_success_popup = true;
                        $has_applied = true;
                        
                    } catch (PDOException $e) {
                        $application_error = 'Failed to submit application. Please try again.';
                        error_log("Application submission error: " . $e->getMessage());
                        // Delete uploaded file if database insert fails
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                } else {
                    $application_error = 'Failed to upload resume. Please try again.';
                }
            }
        }
    }
}

// Helper function to format salary
function formatSalary($amount) {
    if ($amount >= 100000) {
        return '₹' . number_format($amount / 100000, 1) . ' LPA';
    }
    return '₹' . number_format($amount);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title><?php echo htmlspecialchars($job['job_title']); ?> - JobHub</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .job-card { transition: all 0.3s ease; }
        .job-card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        
        /* Success Popup Animation */
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
        
        /* Already Applied Badge */
        .already-applied-badge {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Login Required Card */
        .login-required-card {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8edff 100%);
            border: 2px dashed #818cf8;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }

        /* Limit Reached Card */
        .limit-reached-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }
        
        .limit-reached-card .icon {
            color: #d97706;
        }
        
        /* Remaining Views Badge */
        .views-badge {
            display: inline-block;
            background: #e0e7ff;
            color: #4f46e5;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900">

    <?php include 'includes/nav-bar.php'; ?>

    <!-- Success Popup -->
    <div class="popup-overlay <?php echo $show_success_popup ? 'active' : ''; ?>" id="successPopup">
        <div class="popup-content">
            <div class="popup-check">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h3 class="text-2xl font-extrabold text-slate-900">Application Submitted!</h3>
            <p class="mt-2 text-slate-600">Your application for <strong><?php echo htmlspecialchars($job['job_title']); ?></strong> at <strong><?php echo htmlspecialchars($job['company_name']); ?></strong> has been sent successfully.</p>
            <p class="mt-1 text-sm text-slate-500">We'll notify you about the next steps.</p>
            <button onclick="closePopup()" class="mt-6 w-full rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-indigo-700">
                Great, Thanks!
            </button>
        </div>
    </div>

    <main class="py-12">
        <div class="mx-auto max-w-7xl px-5 lg:px-8">

            <?php if ($application_error): ?>
                <div class="mb-8 rounded-2xl bg-red-50 border border-red-200 p-6">
                    <i data-lucide="alert-circle" class="inline h-5 w-5 text-red-500 mr-2"></i>
                    <span class="text-red-700"><?php echo htmlspecialchars($application_error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Show remaining views info -->
            <?php if ($is_logged_in && $user_data && $subscription_plan): ?>
                <div class="mb-6 flex items-center justify-between flex-wrap gap-2 bg-white rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center gap-3">
                        <i data-lucide="eye" class="h-5 w-5 text-indigo-500"></i>
                        <span class="text-sm text-slate-600">
                            <strong>Job Views:</strong> 
                            <?php echo $job_views_used; ?> / <?php echo $job_views_limit; ?>
                        </span>
                    </div>
                    <div>
                        <?php if ($can_view_job): ?>
                            <span class="views-badge">
                                <i data-lucide="check-circle" class="inline h-3 w-3 mr-1"></i>
                                <?php echo $job_views_limit - $job_views_used; ?> views remaining
                            </span>
                        <?php else: ?>
                            <span class="views-badge" style="background: #fef3c7; color: #92400e;">
                                <i data-lucide="alert-circle" class="inline h-3 w-3 mr-1"></i>
                                Limit Reached
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="subscription-plans.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">
                            Upgrade Plan →
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main Job Details -->
                <div class="lg:col-span-2">
                    <?php if ($can_view_job): ?>
                        <div class="rounded-2xl border border-slate-200 bg-white p-8">
                            <!-- Company Info -->
                            <div class="flex items-start gap-4 border-b border-slate-200 pb-6">
                                <?php if (!empty($job['company_logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($job['company_name']); ?>" 
                                         class="h-16 w-16 shrink-0 rounded-xl object-cover">
                                <?php else: ?>
                                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-2xl font-extrabold text-white">
                                        <?php echo strtoupper(substr($job['company_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div>
                                    <h1 class="text-2xl font-extrabold"><?php echo htmlspecialchars($job['job_title']); ?></h1>
                                    <p class="mt-1 text-lg text-slate-600">
                                        <?php echo htmlspecialchars($job['company_name']); ?>
                                        <span class="text-sm text-slate-400 ml-2">(<?php echo htmlspecialchars($job['company_code']); ?>)</span>
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="text-sm text-slate-500">
                                            <i data-lucide="map-pin" class="inline h-4 w-4"></i>
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </span>
                                        <span class="text-sm text-slate-500">
                                            <i data-lucide="calendar" class="inline h-4 w-4"></i>
                                            Posted: <?php echo date('M d, Y', strtotime($job['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Job Details -->
                            <div class="mt-6 space-y-6">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Job Details</h3>
                                    <div class="mt-3 grid grid-cols-2 gap-4">
                                        <div>
                                            <span class="text-sm text-slate-500">Job Type</span>
                                            <p class="font-semibold"><?php echo htmlspecialchars($job['job_type']); ?></p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-slate-500">Experience</span>
                                            <p class="font-semibold">
                                                <?php 
                                                if ($job['experience_min'] > 0 && $job['experience_max'] > 0) {
                                                    echo $job['experience_min'] . ' - ' . $job['experience_max'] . ' Years';
                                                } elseif ($job['experience_level'] == 'fresher') {
                                                    echo 'Fresher';
                                                } else {
                                                    echo 'Not specified';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-slate-500">Salary</span>
                                            <p class="font-semibold">
                                                <?php 
                                                if ($job['salary_min'] > 0 && $job['salary_max'] > 0) {
                                                    echo formatSalary($job['salary_min']) . ' - ' . formatSalary($job['salary_max']);
                                                } else {
                                                    echo 'Not disclosed';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-slate-500">Deadline</span>
                                            <p class="font-semibold">
                                                <?php 
                                                if (!empty($job['application_deadline'])) {
                                                    echo date('M d, Y', strtotime($job['application_deadline']));
                                                } else {
                                                    echo 'No deadline';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Job Description</h3>
                                    <div class="mt-3 prose max-w-none text-slate-700">
                                        <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                    </div>
                                </div>

                                <?php if (!empty($job['requirements'])): ?>
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Requirements</h3>
                                    <div class="mt-3 prose max-w-none text-slate-700">
                                        <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($job['skills'])): ?>
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Skills</h3>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <?php 
                                        $skills = explode(',', $job['skills']);
                                        foreach ($skills as $skill):
                                            if (trim($skill)):
                                        ?>
                                            <span class="rounded-full bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700">
                                                <?php echo htmlspecialchars(trim($skill)); ?>
                                            </span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($job['benefits'])): ?>
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Benefits</h3>
                                    <div class="mt-3 prose max-w-none text-slate-700">
                                        <?php echo nl2br(htmlspecialchars($job['benefits'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Limit Reached - Hide Job Details -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
                            <i data-lucide="eye-off" class="mx-auto h-16 w-16 text-slate-300"></i>
                            <h3 class="mt-4 text-xl font-bold text-slate-900">Job Details Locked</h3>
                            <p class="mt-2 text-slate-600 max-w-md mx-auto">
                                <?php echo htmlspecialchars($limit_message); ?>
                            </p>
                            <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                                <a href="subscription-plans.php" 
                                   class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                                    <i data-lucide="crown" class="inline h-4 w-4 mr-2"></i>
                                    Upgrade Subscription
                                </a>
                                <a href="jobs.php" 
                                   class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                    Browse Jobs
                                </a>
                            </div>
                            <?php if ($user_data && $subscription_plan): ?>
                                <div class="mt-4 text-sm text-slate-500">
                                    Used: <?php echo $job_views_used; ?> / <?php echo $job_views_limit; ?> job views
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar - Company Info & Application Form -->
                <div class="space-y-6">
                    <!-- Company Info Card -->
                    <?php if ($can_view_job): ?>
                        <div class="rounded-2xl border border-slate-200 bg-white p-6">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">About Company</h3>
                            <div class="mt-4">
                                <p class="font-semibold text-lg"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                <?php if (!empty($job['company_code'])): ?>
                                    <p class="text-sm text-slate-500">ID: <?php echo htmlspecialchars($job['company_code']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($job['industry'])): ?>
                                    <p class="mt-2 text-sm text-slate-600">Industry: <?php echo htmlspecialchars($job['industry']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($job['company_size'])): ?>
                                    <p class="text-sm text-slate-600">Size: <?php echo htmlspecialchars($job['company_size']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($job['website'])): ?>
                                    <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-700">
                                        Visit Website →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center">
                            <i data-lucide="building-2" class="mx-auto h-12 w-12 text-slate-300"></i>
                            <p class="mt-2 text-sm text-slate-500">Company details are locked</p>
                            <p class="text-xs text-slate-400">Upgrade to view company information</p>
                        </div>
                    <?php endif; ?>

                    <!-- Application Form -->
                    <?php if ($can_view_job): ?>
                        <div class="rounded-2xl border border-slate-200 bg-white p-6" id="apply-form">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Apply Now</h3>
                            
                            <?php if ($has_applied): ?>
                                <!-- Already Applied Message -->
                                <div class="mt-4 already-applied-badge">
                                    <i data-lucide="check-circle" class="h-6 w-6 text-amber-600 flex-shrink-0"></i>
                                    <div>
                                        <p class="font-semibold text-amber-800">You've already applied!</p>
                                        <?php if ($existing_application): ?>
                                            <p class="text-sm text-amber-700">
                                                Applied on <?php echo date('M d, Y', strtotime($existing_application['applied_at'])); ?>
                                                <?php if ($existing_application['status'] != 'pending'): ?>
                                                    <span class="font-medium">
                                                        • Status: <?php echo ucfirst($existing_application['status']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mt-4 p-4 bg-slate-50 rounded-xl text-center">
                                    <p class="text-sm text-slate-600">You've already submitted your application for this position.</p>
                                    <p class="text-xs text-slate-500 mt-1">We'll notify you about the next steps.</p>
                                </div>
                                
                                <div class="mt-4 text-center">
                                    <a href="jobs.php" class="inline-block rounded-xl border border-slate-200 px-6 py-2.5 text-sm font-bold text-slate-700 hover:border-indigo-200 hover:text-indigo-600 transition">
                                        Browse More Jobs →
                                    </a>
                                </div>
                                
                            <?php elseif (!$is_logged_in): ?>
                                <!-- Not Logged In - Show Login Required -->
                                <div class="mt-4 login-required-card">
                                    <i data-lucide="lock" class="mx-auto h-14 w-14 text-indigo-400"></i>
                                    <h4 class="mt-3 text-lg font-bold text-slate-900">Sign in to Apply</h4>
                                    <p class="mt-2 text-sm text-slate-600">Please sign in or create an account to apply for this job.</p>
                                    <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                                        <a href="signin.php?redirect=job-details.php?id=<?php echo $job_id; ?>" 
                                           class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                                            Sign In
                                        </a>
                                        <a href="signup.php" 
                                           class="rounded-xl border border-slate-200 px-6 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                            Create Account
                                        </a>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <!-- Logged In - Show Application Form with Pre-filled Data -->
                                <?php if ($user_data && $user_data['subscription_status'] == 'active'): ?>
                                    <form method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Full Name *</label>
                                            <input type="text" name="applicant_name" required
                                                   value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition bg-slate-50">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Email Address *</label>
                                            <input type="email" name="applicant_email" required
                                                   value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition bg-slate-50">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Phone Number</label>
                                            <input type="tel" name="applicant_phone"
                                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Years of Experience</label>
                                            <input type="number" name="experience_years" min="0" max="50"
                                                   value="<?php echo htmlspecialchars($user_data['experience_years'] ?? 0); ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Current Company</label>
                                            <input type="text" name="current_company"
                                                   value="<?php echo htmlspecialchars($user_data['current_company'] ?? ''); ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Current Position</label>
                                            <input type="text" name="current_position"
                                                   value="<?php echo htmlspecialchars($user_data['current_position'] ?? ''); ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Upload Resume *</label>
                                            <input type="file" name="resume_file" required accept=".pdf,.doc,.docx"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                            <p class="mt-1 text-xs text-slate-500">PDF, DOC, DOCX (Max 5MB)</p>
                                            <?php if (!empty($user_data['resume'])): ?>
                                                <p class="mt-1 text-xs text-emerald-600">
                                                    <i data-lucide="file" class="inline h-3 w-3"></i>
                                                    Your uploaded resume is available. You can upload a new one if needed.
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Cover Letter</label>
                                            <textarea name="cover_letter" rows="4"
                                                      class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                                                      placeholder="Tell us why you're a great fit..."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="flex items-center gap-2 text-xs text-slate-500 bg-slate-50 rounded-xl p-3">
                                            <i data-lucide="info" class="h-4 w-4 text-indigo-500"></i>
                                            <span>Your profile information has been auto-filled. You can edit as needed.</span>
                                        </div>
                                        
                                        <button type="submit" name="apply" 
                                                class="w-full rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-indigo-700 shadow-lg shadow-indigo-200">
                                            <i data-lucide="send" class="inline h-4 w-4 mr-2"></i>
                                            Submit Application
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- No Active Subscription -->
                                    <div class="mt-4 limit-reached-card">
                                        <i data-lucide="crown" class="mx-auto h-14 w-14 text-amber-500"></i>
                                        <h4 class="mt-3 text-lg font-bold text-slate-900">Subscription Required</h4>
                                        <p class="mt-2 text-sm text-slate-600">You need an active subscription to apply for jobs.</p>
                                        <a href="subscription-plans.php" 
                                           class="mt-4 inline-block rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                                            View Plans
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Limit Reached - Hide Application Form -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Apply Now</h3>
                            <div class="mt-4 limit-reached-card">
                                <i data-lucide="alert-triangle" class="mx-auto h-14 w-14 text-amber-500"></i>
                                <h4 class="mt-3 text-lg font-bold text-slate-900">Application Locked</h4>
                                <p class="mt-2 text-sm text-slate-600">You've reached your job view limit. Upgrade to apply.</p>
                                <div class="mt-4 flex flex-col gap-3">
                                    <a href="subscription-plans.php" 
                                       class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                                        <i data-lucide="crown" class="inline h-4 w-4 mr-2"></i>
                                        Upgrade Now
                                    </a>
                                    <a href="jobs.php" 
                                       class="rounded-xl border border-slate-200 px-6 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                        Browse Jobs
                                    </a>
                                </div>
                                <?php if ($user_data && $subscription_plan): ?>
                                    <div class="mt-4 text-sm text-slate-500">
                                        Used: <?php echo $job_views_used; ?> / <?php echo $job_views_limit; ?> job views
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    
    <!-- Lucide Icons -->
    <script>
        lucide.createIcons();
        
        function closePopup() {
            document.getElementById('successPopup').classList.remove('active');
            window.location.href = 'job-details.php?id=<?php echo $job_id; ?>';
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
    </script>

</body>
</html>