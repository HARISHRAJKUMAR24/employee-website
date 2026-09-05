<?php
require_once './config/config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['employee_id'])) {
    header('Location: signin.php');
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$employee_email = $_SESSION['employee_email'] ?? '';
$employee_id_code = $_SESSION['employee_id_code'] ?? '';

// Get employee data
try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();

    if (!$employee) {
        session_destroy();
        header('Location: signin.php');
        exit;
    }

    // Get job applications with job details
    $stmt = $pdo->prepare("
        SELECT ja.*, jp.job_title, jp.location, jp.job_type, c.company_name, c.company_id 
        FROM job_applications ja
        LEFT JOIN jobs_post jp ON ja.job_id = jp.id
        LEFT JOIN companies c ON ja.company_id = c.id
        WHERE ja.applicant_email = ?
        ORDER BY ja.applied_at DESC
    ");
    $stmt->execute([$employee['email']]);
    $applications = $stmt->fetchAll();

    // Get subscription details
    $subscription = null;
    if ($employee['subscription_id']) {
        $stmt = $pdo->prepare("
            SELECT * FROM employee_subscriptions_plans 
            WHERE id = ?
        ");
        $stmt->execute([$employee['subscription_id']]);
        $subscription = $stmt->fetch();
    }

    // Get application statistics
    $total_applications = count($applications);
    $pending = 0;
    $reviewed = 0;
    $shortlisted = 0;
    $rejected = 0;
    $hired = 0;

    foreach ($applications as $app) {
        switch ($app['status']) {
            case 'pending': $pending++; break;
            case 'reviewed': $reviewed++; break;
            case 'shortlisted': $shortlisted++; break;
            case 'rejected': $rejected++; break;
            case 'hired': $hired++; break;
        }
    }

} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    $error = 'Something went wrong. Please try again.';
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $current_position = trim($_POST['current_position'] ?? '');
    $current_company = trim($_POST['current_company'] ?? '');
    $experience_years = intval($_POST['experience_years'] ?? 0);
    $bio = trim($_POST['bio'] ?? '');
    $skills = trim($_POST['skills'] ?? '');

    try {
        $stmt = $pdo->prepare("
            UPDATE employees SET 
                full_name = ?, 
                phone = ?, 
                location = ?, 
                current_position = ?, 
                current_company = ?, 
                experience_years = ?, 
                bio = ?, 
                skills = ?
            WHERE id = ?
        ");
        $stmt->execute([$full_name, $phone, $location, $current_position, $current_company, $experience_years, $bio, $skills, $employee_id]);

        $_SESSION['employee_name'] = $full_name;
        $success = 'Profile updated successfully!';
        
        // Refresh employee data
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch();

    } catch (PDOException $e) {
        $error = 'Failed to update profile. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>My Profile - JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .profile-avatar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.06);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-reviewed { background: #dbeafe; color: #1e40af; }
        .status-shortlisted { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-hired { background: #a7f3d0; color: #065f46; }
        .tab-btn {
            transition: all 0.3s ease;
        }
        .tab-btn.active {
            border-bottom: 3px solid #667eea;
            color: #667eea;
        }
    </style>
</head>
<body>

    <?php include 'includes/nav-bar.php'; ?>

    <main class="py-8 px-5 lg:px-8">
        <div class="mx-auto max-w-7xl">

            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-extrabold text-slate-900">My Profile</h1>
                <p class="text-sm text-slate-500">Manage your profile and track your job applications</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700 flex items-start gap-2">
                    <i data-lucide="alert-circle" class="h-5 w-5 flex-shrink-0 mt-0.5"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700 flex items-start gap-2">
                    <i data-lucide="check-circle" class="h-5 w-5 flex-shrink-0 mt-0.5"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                
                <!-- Left Column - Profile Card -->
                <div class="lg:col-span-1">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <!-- Avatar -->
                        <div class="flex flex-col items-center">
                            <div class="profile-avatar flex h-24 w-24 items-center justify-center rounded-2xl text-3xl font-bold text-white shadow-lg shadow-indigo-200">
                                <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
                            </div>
                            <h3 class="mt-4 text-xl font-bold text-slate-900"><?= htmlspecialchars($employee['full_name']) ?></h3>
                            <p class="text-sm text-slate-500"><?= htmlspecialchars($employee['email']) ?></p>
                            <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <i data-lucide="badge-check" class="h-3 w-3"></i>
                                Verified
                            </span>
                            <p class="mt-2 text-xs text-slate-400">Employee ID: <?= htmlspecialchars($employee['employee_id']) ?></p>
                        </div>

                        <hr class="my-4 border-slate-200" />

                        <!-- Quick Stats -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-slate-50 p-3 text-center">
                                <p class="text-xl font-bold text-slate-900"><?= $total_applications ?></p>
                                <p class="text-xs text-slate-500">Total Apps</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 text-center">
                                <p class="text-xl font-bold text-emerald-600"><?= $hired ?></p>
                                <p class="text-xs text-slate-500">Hired</p>
                            </div>
                        </div>

                        <!-- Subscription Info -->
                        <?php if ($subscription): ?>
                            <div class="mt-4 rounded-xl bg-indigo-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Subscription</p>
                                <p class="mt-1 font-semibold text-slate-900"><?= htmlspecialchars($subscription['plan_name']) ?></p>
                                <p class="text-sm text-slate-600">
                                    <?php if ($employee['subscription_status'] === 'active'): ?>
                                        <span class="inline-flex items-center gap-1 text-emerald-600">
                                            <i data-lucide="circle" class="h-2 w-2 fill-emerald-600"></i>
                                            Active until <?= date('M d, Y', strtotime($employee['subscription_expiry_date'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-red-600">
                                            <i data-lucide="circle" class="h-2 w-2 fill-red-600"></i>
                                            Expired
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column - Tabs -->
                <div class="lg:col-span-2">
                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        
                        <!-- Tabs -->
                        <div class="border-b border-slate-200 px-6">
                            <nav class="flex gap-6" id="tabNav">
                                <button class="tab-btn active py-3 text-sm font-semibold text-slate-600" data-tab="applications">
                                    <i data-lucide="file-text" class="inline-block h-4 w-4"></i>
                                    Applications
                                </button>
                                <button class="tab-btn py-3 text-sm font-semibold text-slate-600" data-tab="edit-profile">
                                    <i data-lucide="user" class="inline-block h-4 w-4"></i>
                                    Edit Profile
                                </button>
                            </nav>
                        </div>

                        <!-- Tab Content -->
                        <div class="p-6">

                            <!-- Applications Tab -->
                            <div id="tab-applications" class="tab-content">
                                <?php if (count($applications) > 0): ?>
                                    <!-- Stats Summary -->
                                    <div class="mb-6 grid grid-cols-5 gap-2">
                                        <div class="rounded-xl bg-slate-50 p-3 text-center">
                                            <p class="text-lg font-bold text-slate-900"><?= $pending ?></p>
                                            <p class="text-xs text-slate-500">Pending</p>
                                        </div>
                                        <div class="rounded-xl bg-slate-50 p-3 text-center">
                                            <p class="text-lg font-bold text-blue-600"><?= $reviewed ?></p>
                                            <p class="text-xs text-slate-500">Reviewed</p>
                                        </div>
                                        <div class="rounded-xl bg-slate-50 p-3 text-center">
                                            <p class="text-lg font-bold text-emerald-600"><?= $shortlisted ?></p>
                                            <p class="text-xs text-slate-500">Shortlisted</p>
                                        </div>
                                        <div class="rounded-xl bg-slate-50 p-3 text-center">
                                            <p class="text-lg font-bold text-red-600"><?= $rejected ?></p>
                                            <p class="text-xs text-slate-500">Rejected</p>
                                        </div>
                                        <div class="rounded-xl bg-slate-50 p-3 text-center">
                                            <p class="text-lg font-bold text-emerald-700"><?= $hired ?></p>
                                            <p class="text-xs text-slate-500">Hired</p>
                                        </div>
                                    </div>

                                    <!-- Applications List -->
                                    <div class="space-y-4">
                                        <?php foreach ($applications as $app): ?>
                                            <div class="rounded-xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:shadow-sm">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div>
                                                        <h4 class="font-semibold text-slate-900"><?= htmlspecialchars($app['job_title'] ?? 'Unknown Position') ?></h4>
                                                        <p class="text-sm text-slate-600">
                                                            <?= htmlspecialchars($app['company_name'] ?? 'Unknown Company') ?>
                                                            <span class="mx-2 text-slate-300">·</span>
                                                            <?= htmlspecialchars($app['location'] ?? 'Remote') ?>
                                                            <span class="mx-2 text-slate-300">·</span>
                                                            <?= htmlspecialchars($app['job_type'] ?? 'N/A') ?>
                                                        </p>
                                                        <p class="mt-1 text-xs text-slate-400">
                                                            Applied: <?= date('M d, Y', strtotime($app['applied_at'])) ?>
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <span class="status-badge status-<?= $app['status'] ?>">
                                                            <?= ucfirst($app['status']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="py-12 text-center">
                                        <i data-lucide="inbox" class="mx-auto h-12 w-12 text-slate-300"></i>
                                        <h4 class="mt-4 text-lg font-semibold text-slate-900">No Applications Yet</h4>
                                        <p class="text-sm text-slate-500">Start applying to jobs to see your applications here.</p>
                                        <a href="jobs.php" class="mt-4 inline-block rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700">
                                            Browse Jobs
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Edit Profile Tab -->
                            <div id="tab-edit-profile" class="tab-content hidden">
                                <form method="POST" class="space-y-4">
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Full Name *</label>
                                            <input type="text" name="full_name" required
                                                   value="<?= htmlspecialchars($employee['full_name']) ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Email</label>
                                            <input type="email" value="<?= htmlspecialchars($employee['email']) ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-500 cursor-not-allowed" disabled>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Phone</label>
                                            <input type="tel" name="phone"
                                                   value="<?= htmlspecialchars($employee['phone'] ?? '') ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Location</label>
                                            <input type="text" name="location"
                                                   value="<?= htmlspecialchars($employee['location'] ?? '') ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Current Position</label>
                                            <input type="text" name="current_position"
                                                   value="<?= htmlspecialchars($employee['current_position'] ?? '') ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Current Company</label>
                                            <input type="text" name="current_company"
                                                   value="<?= htmlspecialchars($employee['current_company'] ?? '') ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Experience (Years)</label>
                                            <input type="number" name="experience_years" min="0"
                                                   value="<?= htmlspecialchars($employee['experience_years'] ?? 0) ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-slate-700">Skills (comma separated)</label>
                                            <input type="text" name="skills"
                                                   value="<?= htmlspecialchars($employee['skills'] ?? '') ?>"
                                                   class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-slate-700">Bio / About</label>
                                            <textarea name="bio" rows="3"
                                                      class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"><?= htmlspecialchars($employee['bio'] ?? '') ?></textarea>
                                        </div>
                                    </div>

                                    <button type="submit" name="update_profile"
                                            class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700 shadow-lg shadow-indigo-200">
                                        <i data-lucide="save" class="inline-block h-4 w-4"></i>
                                        Save Changes
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        lucide.createIcons();

        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active from all tabs
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Hide all tab contents
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));

                // Show selected tab content
                const tabId = this.dataset.tab;
                document.getElementById('tab-' + tabId).classList.remove('hidden');
            });
        });
    </script>
</body>
</html>