<?php
require_once './config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pagination settings
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$job_type = isset($_GET['job_type']) ? trim($_GET['job_type']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$experience = isset($_GET['experience']) ? trim($_GET['experience']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

// Build the WHERE clause
$where_conditions = ["j.status = 'active'", "c.is_active = 1"];

if (!empty($search)) {
    $where_conditions[] = "(j.job_title LIKE :search OR j.description LIKE :search OR c.company_name LIKE :search OR j.location LIKE :search)";
}

if (!empty($job_type)) {
    $where_conditions[] = "j.job_type = :job_type";
}

if (!empty($location)) {
    $where_conditions[] = "j.location LIKE :location";
}

if (!empty($experience)) {
    if ($experience == 'fresher') {
        $where_conditions[] = "j.experience_level = 'fresher'";
    } else {
        $where_conditions[] = "j.experience_min >= :experience";
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Build the ORDER BY clause
$order_by = match($sort) {
    'oldest' => 'j.created_at ASC',
    'salary_high' => 'j.salary_max DESC',
    'salary_low' => 'j.salary_min ASC',
    default => 'j.created_at DESC'
};

// Get total job count for pagination
try {
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM jobs_post j
        INNER JOIN companies c ON j.company_id = c.id
        WHERE $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    
    // Bind parameters
    if (!empty($search)) {
        $count_stmt->bindValue(':search', '%' . $search . '%');
    }
    if (!empty($job_type)) {
        $count_stmt->bindValue(':job_type', $job_type);
    }
    if (!empty($location)) {
        $count_stmt->bindValue(':location', '%' . $location . '%');
    }
    if (!empty($experience) && $experience != 'fresher') {
        $count_stmt->bindValue(':experience', intval($experience));
    }
    
    $count_stmt->execute();
    $total_jobs = $count_stmt->fetchColumn();
    $total_pages = ceil($total_jobs / $per_page);

} catch (PDOException $e) {
    error_log("Error counting jobs: " . $e->getMessage());
    $total_jobs = 0;
    $total_pages = 0;
}

// Fetch jobs from database with company details
try {
    $sql = "
        SELECT 
            j.*,
            c.company_name,
            c.logo as company_logo,
            c.id as company_id
        FROM jobs_post j
        INNER JOIN companies c ON j.company_id = c.id
        WHERE $where_clause
        ORDER BY $order_by
        LIMIT :offset, :per_page
    ";
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    if (!empty($job_type)) {
        $stmt->bindValue(':job_type', $job_type);
    }
    if (!empty($location)) {
        $stmt->bindValue(':location', '%' . $location . '%');
    }
    if (!empty($experience) && $experience != 'fresher') {
        $stmt->bindValue(':experience', intval($experience));
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    $jobs = [];
    error_log("Error fetching jobs: " . $e->getMessage());
}

// Get unique job types and locations for filters
try {
    $job_types_stmt = $pdo->query("SELECT DISTINCT job_type FROM jobs_post WHERE status = 'active' ORDER BY job_type");
    $job_types = $job_types_stmt->fetchAll();
    
    $locations_stmt = $pdo->query("SELECT DISTINCT location FROM jobs_post WHERE status = 'active' ORDER BY location");
    $locations = $locations_stmt->fetchAll();
} catch (PDOException $e) {
    $job_types = [];
    $locations = [];
    error_log("Error fetching filter options: " . $e->getMessage());
}

// Color array for company logos
$colors = ['indigo-600', 'pink-500', 'emerald-500', 'orange-500', 'blue-500', 'purple-500', 'red-500', 'teal-500', 'yellow-500', 'cyan-500'];

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

    <title>All Jobs - JobHub</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        .job-card {
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            border-color: rgb(165 180 252);
        }
        
        /* Filter dropdown styles */
        .filter-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .filter-select:focus {
            outline: none;
        }
        
        /* Pagination active state */
        .pagination-active {
            background-color: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }
        .pagination-active:hover {
            background-color: #4338ca;
            border-color: #4338ca;
        }
    </style>
</head>

<body>

    <?php include 'includes/nav-bar.php'; ?>

    <main class="py-12">
        <div class="mx-auto max-w-7xl px-5 lg:px-8">

            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">
                    All Jobs
                </h1>
                <p class="mt-2 text-slate-500">
                    <?php echo $total_jobs; ?> jobs found
                    <?php if (!empty($search)): ?>
                        for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    <?php endif; ?>
                </p>
            </div>

            <!-- Search and Filters -->
            <div class="mb-8">
                <form method="GET" action="jobs.php" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <!-- Search Input -->
                        <div class="md:col-span-4 lg:col-span-1">
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Search jobs, companies, locations..."
                                       class="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition">
                            </div>
                        </div>

                        <!-- Job Type Filter -->
                        <div>
                            <select name="job_type" class="filter-select w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition bg-white">
                                <option value="">All Job Types</option>
                                <?php foreach ($job_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['job_type']); ?>" 
                                        <?php echo $job_type == $type['job_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['job_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Location Filter -->
                        <div>
                            <select name="location" class="filter-select w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition bg-white">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc['location']); ?>" 
                                        <?php echo $location == $loc['location'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['location']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Experience Filter -->
                        <div>
                            <select name="experience" class="filter-select w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition bg-white">
                                <option value="">All Experience</option>
                                <option value="fresher" <?php echo $experience == 'fresher' ? 'selected' : ''; ?>>Fresher</option>
                                <option value="1" <?php echo $experience == '1' ? 'selected' : ''; ?>>1+ Years</option>
                                <option value="2" <?php echo $experience == '2' ? 'selected' : ''; ?>>2+ Years</option>
                                <option value="3" <?php echo $experience == '3' ? 'selected' : ''; ?>>3+ Years</option>
                                <option value="5" <?php echo $experience == '5' ? 'selected' : ''; ?>>5+ Years</option>
                                <option value="8" <?php echo $experience == '8' ? 'selected' : ''; ?>>8+ Years</option>
                                <option value="10" <?php echo $experience == '10' ? 'selected' : ''; ?>>10+ Years</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Sort Options -->
                            <select name="sort" class="filter-select rounded-xl border border-slate-300 px-4 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition bg-white">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="salary_high" <?php echo $sort == 'salary_high' ? 'selected' : ''; ?>>Highest Salary</option>
                                <option value="salary_low" <?php echo $sort == 'salary_low' ? 'selected' : ''; ?>>Lowest Salary</option>
                            </select>

                            <button type="submit" class="rounded-xl bg-indigo-600 px-6 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                                <i data-lucide="filter" class="inline h-4 w-4 mr-1"></i>
                                Apply Filters
                            </button>

                            <?php if (!empty($search) || !empty($job_type) || !empty($location) || !empty($experience)): ?>
                                <a href="jobs.php" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                                    Clear All
                                </a>
                            <?php endif; ?>
                        </div>

                        <span class="text-sm text-slate-500">
                            Showing <?php echo count($jobs); ?> of <?php echo $total_jobs; ?> jobs
                        </span>
                    </div>
                </form>
            </div>

            <!-- Job List -->
            <div id="jobList" class="grid gap-5 md:grid-cols-2">

                <?php if (!empty($jobs)): ?>
                    <?php foreach ($jobs as $index => $job): ?>
                        <?php
                        // Get first letter of company name for logo
                        $initial = strtoupper(substr($job['company_name'], 0, 1));
                        // Assign color based on index
                        $color = $colors[$index % count($colors)];

                        // Determine job type badge color
                        $jobTypeColors = [
                            'Full-time' => 'indigo',
                            'Part-time' => 'blue',
                            'Contract' => 'orange',
                            'Internship' => 'emerald',
                            'Freelance' => 'purple'
                        ];
                        $jobTypeColor = $jobTypeColors[$job['job_type']] ?? 'slate';

                        // Format salary range
                        $salaryDisplay = '';
                        if ($job['salary_min'] > 0 && $job['salary_max'] > 0) {
                            $salaryDisplay = formatSalary($job['salary_min']) . ' – ' . formatSalary($job['salary_max']);
                        } elseif ($job['salary_min'] > 0) {
                            $salaryDisplay = 'From ' . formatSalary($job['salary_min']);
                        } elseif ($job['salary_max'] > 0) {
                            $salaryDisplay = 'Up to ' . formatSalary($job['salary_max']);
                        } else {
                            $salaryDisplay = 'Salary not disclosed';
                        }

                        // Determine experience level
                        $experienceDisplay = '';
                        if ($job['experience_min'] > 0 && $job['experience_max'] > 0) {
                            $experienceDisplay = $job['experience_min'] . '+' . ' Years';
                        } elseif ($job['experience_level'] == 'fresher') {
                            $experienceDisplay = 'Fresher';
                        } else {
                            $experienceDisplay = 'Not specified';
                        }
                        ?>

                        <article class="job-card rounded-2xl border border-slate-200 bg-white p-6 transition-shadow duration-300" data-title="<?php echo htmlspecialchars($job['job_title']); ?>">
                            <div class="flex items-start justify-between">

                                <div class="flex gap-4">
                                    <?php if (!empty($job['company_logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($job['company_logo']); ?>"
                                            alt="<?php echo htmlspecialchars($job['company_name']); ?>"
                                            class="h-14 w-14 shrink-0 rounded-xl object-cover">
                                    <?php else: ?>
                                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-<?php echo $color; ?> text-xl font-extrabold text-white">
                                            <?php echo $initial; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div>
                                        <a href="job-details.php?id=<?php echo $job['id']; ?>" class="hover:text-indigo-600 transition-colors">
                                            <h3 class="font-bold text-slate-900">
                                                <?php echo htmlspecialchars($job['job_title']); ?>
                                            </h3>
                                        </a>
                                        <p class="mt-1 text-sm text-slate-500">
                                            <?php echo htmlspecialchars($job['company_name']); ?>
                                        </p>
                                    </div>
                                </div>

                                <button class="bookmark rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-indigo-600"
                                    data-job-id="<?php echo $job['id']; ?>">
                                    <i data-lucide="bookmark"></i>
                                </button>
                            </div>

                            <div class="mt-6 flex flex-wrap gap-2">
                                <span class="rounded-full bg-<?php echo $jobTypeColor; ?>-50 px-3 py-1.5 text-xs font-semibold text-<?php echo $jobTypeColor; ?>-700">
                                    <?php echo htmlspecialchars($job['job_type']); ?>
                                </span>

                                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                                    <?php echo htmlspecialchars($job['location']); ?>
                                </span>

                                <?php if (!empty($experienceDisplay) && $experienceDisplay != 'Not specified'): ?>
                                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                                        <?php echo $experienceDisplay; ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($job['skills'])): ?>
                                    <?php
                                    $skills = explode(',', $job['skills']);
                                    $displaySkills = array_slice($skills, 0, 2);
                                    foreach ($displaySkills as $skill):
                                        if (trim($skill)): ?>
                                            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                                                <?php echo htmlspecialchars(trim($skill)); ?>
                                            </span>
                                        <?php endif;
                                    endforeach;
                                    if (count($skills) > 2): ?>
                                        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">
                                            +<?php echo (count($skills) - 2); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-5">
                                <div>
                                    <span class="text-lg font-extrabold"><?php echo $salaryDisplay; ?></span>
                                    <?php if ($job['salary_min'] > 0 && $job['salary_max'] > 0): ?>
                                        <span class="ml-1 text-xs text-slate-400">/ year</span>
                                    <?php endif; ?>
                                </div>

                                <span class="flex items-center gap-1 text-xs text-slate-400">
                                    <i data-lucide="map-pin" class="h-3.5 w-3.5"></i>
                                    <?php echo htmlspecialchars($job['location']); ?>
                                </span>
                            </div>

                            <?php if (!empty($job['application_deadline'])): ?>
                                <div class="mt-2 text-xs text-slate-400">
                                    <i data-lucide="calendar" class="inline h-3 w-3 mr-1"></i>
                                    Deadline: <?php echo date('M d, Y', strtotime($job['application_deadline'])); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Apply Now Button -->
                            <div class="mt-4 pt-4 border-t border-slate-100">
                                <a href="job-details.php?id=<?php echo $job['id']; ?>#apply-form" 
                                   class="flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-700 hover:shadow-lg">
                                    <i data-lucide="send" class="mr-2 h-4 w-4"></i>
                                    Apply Now
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div id="noJobs" class="col-span-2 rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
                        <i data-lucide="search-x" class="mx-auto h-12 w-12 text-slate-300"></i>
                        <h3 class="mt-4 text-xl font-bold text-slate-900">No jobs found</h3>
                        <p class="mt-2 text-slate-500">
                            <?php if (!empty($search)): ?>
                                No jobs match your search criteria. Try adjusting your filters.
                            <?php else: ?>
                                No active job listings available at the moment.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search) || !empty($job_type) || !empty($location) || !empty($experience)): ?>
                            <a href="jobs.php" class="mt-4 inline-block rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition">
                                Clear All Filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-2">
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&job_type=<?php echo urlencode($job_type); ?>&location=<?php echo urlencode($location); ?>&experience=<?php echo urlencode($experience); ?>&sort=<?php echo urlencode($sort); ?>" 
                           class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            ← Previous
                        </a>
                    <?php else: ?>
                        <span class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400 cursor-not-allowed">
                            ← Previous
                        </span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?page=1&search=' . urlencode($search) . '&job_type=' . urlencode($job_type) . '&location=' . urlencode($location) . '&experience=' . urlencode($experience) . '&sort=' . urlencode($sort) . '" 
                                 class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="px-2 text-slate-400">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<span class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white border border-indigo-600">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&job_type=' . urlencode($job_type) . '&location=' . urlencode($location) . '&experience=' . urlencode($experience) . '&sort=' . urlencode($sort) . '" 
                                      class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">' . $i . '</a>';
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="px-2 text-slate-400">...</span>';
                        }
                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&job_type=' . urlencode($job_type) . '&location=' . urlencode($location) . '&experience=' . urlencode($experience) . '&sort=' . urlencode($sort) . '" 
                                 class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">' . $total_pages . '</a>';
                    }
                    ?>

                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&job_type=<?php echo urlencode($job_type); ?>&location=<?php echo urlencode($location); ?>&experience=<?php echo urlencode($experience); ?>&sort=<?php echo urlencode($sort); ?>" 
                           class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Next →
                        </a>
                    <?php else: ?>
                        <span class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400 cursor-not-allowed">
                            Next →
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- Lucide Icons -->
    <script>
        lucide.createIcons();
        
        // Bookmark functionality
        document.querySelectorAll('.bookmark').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const jobId = this.dataset.jobId;
                const icon = this.querySelector('i');
                
                // Toggle bookmark state
                if (icon.getAttribute('data-lucide') === 'bookmark') {
                    icon.setAttribute('data-lucide', 'bookmark-check');
                    this.classList.add('text-indigo-600');
                    this.classList.remove('text-slate-400');
                    lucide.createIcons();
                    // You can add AJAX call here to save bookmark
                } else {
                    icon.setAttribute('data-lucide', 'bookmark');
                    this.classList.remove('text-indigo-600');
                    this.classList.add('text-slate-400');
                    lucide.createIcons();
                    // You can add AJAX call here to remove bookmark
                }
            });
        });
    </script>

</body>
</html>