<?php

// Fetch featured/active jobs from database with company details
try {
    $stmt = $pdo->prepare("
        SELECT 
            j.*,
            c.company_name,
            c.logo as company_logo,
            c.id as company_id
        FROM jobs_post j
        INNER JOIN companies c ON j.company_id = c.id
        WHERE j.status = 'active' 
        AND c.is_active = 1
        ORDER BY j.created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $jobs = $stmt->fetchAll();
} catch (PDOException $e) {
    $jobs = [];
    error_log("Error fetching jobs: " . $e->getMessage());
}

// Color array for company logos
$colors = ['indigo-600', 'pink-500', 'emerald-500', 'orange-500', 'blue-500', 'purple-500', 'red-500', 'teal-500', 'yellow-500', 'cyan-500'];

// Helper function to format salary
function formatSalary($amount)
{
    if ($amount >= 100000) {
        return '₹' . number_format($amount / 100000, 1) . ' LPA';
    }
    return '₹' . number_format($amount);
}
?>

<section id="jobs" class="bg-slate-50 py-20 lg:py-24">
    <div class="mx-auto max-w-7xl px-5 lg:px-8">

        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">
                    Handpicked for you
                </p>

                <h2 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
                    Featured jobs
                </h2>

                <p class="mt-3 text-slate-500">
                    Explore roles from companies hiring right now.
                </p>
            </div>

            <a href="jobs.php" class="text-sm font-bold text-indigo-600 hover:text-indigo-700">
                See all jobs →
            </a>
        </div>

        <!-- Job List -->
        <div id="jobList" class="mt-10 grid gap-5 lg:grid-cols-2">

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

                    <article class="job-card rounded-2xl border border-slate-200 bg-white p-6 hover:shadow-lg transition-shadow duration-300" data-title="<?php echo htmlspecialchars($job['job_title']); ?>">
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
                    <i data-lucide="search-x" class="mx-auto h-10 w-10 text-slate-300"></i>
                    <h3 class="mt-4 font-bold">No jobs found</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        No active job listings available at the moment.
                    </p>
                </div>
            <?php endif; ?>

        </div>

        <?php if (!empty($jobs)): ?>
            <div class="mt-10 text-center">
                <a href="jobs.php" class="inline-block rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600">
                    Load More Jobs
                </a>
            </div>
        <?php endif; ?>

    </div>
</section>