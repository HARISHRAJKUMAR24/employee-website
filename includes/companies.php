<?php

// Fetch companies from database
try {
    $stmt = $pdo->query("SELECT id, company_name, logo, company_id, is_active FROM companies WHERE is_active = 1 ORDER BY company_name ASC");
    $companies = $stmt->fetchAll();
} catch (PDOException $e) {
    $companies = [];
    error_log("Error fetching companies: " . $e->getMessage());
}

// Color array for company logos (you can expand this)
$colors = ['indigo-600', 'orange-500', 'emerald-500', 'pink-500', 'blue-500', 'purple-500', 'red-500', 'teal-500', 'yellow-500', 'cyan-500'];
?>

<section id="companies" class="py-20 lg:py-24">
    <div class="mx-auto max-w-7xl px-5 lg:px-8">

        <div class="text-center">
            <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">
                Great companies
            </p>

            <h2 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
                Work with the best
            </h2>

            <p class="mx-auto mt-3 max-w-xl text-slate-500">
                Join companies building the future and discover teams where you can grow.
            </p>
        </div>

        <div class="mt-12 grid grid-cols-2 gap-4 md:grid-cols-4">

            <?php if (!empty($companies)): ?>
                <?php foreach ($companies as $index => $company): ?>
                    <?php
                    // Get the first letter of company name for logo
                    $initial = strtoupper(substr($company['company_name'], 0, 1));
                    // Assign color based on index
                    $color = $colors[$index % count($colors)];
                    // Use logo if available, otherwise use initial
                    $logo_html = !empty($company['logo']) 
                        ? '<img src="' . htmlspecialchars($company['logo']) . '" alt="' . htmlspecialchars($company['company_name']) . '" class="h-14 w-14 rounded-xl object-cover">'
                        : '<div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-' . $color . ' text-xl font-extrabold text-white">' . $initial . '</div>';
                    ?>
                    <div class="company-logo rounded-2xl border border-slate-200 bg-white p-7 text-center">
                        <?php echo $logo_html; ?>
                        <h3 class="mt-4 font-bold"><?php echo htmlspecialchars($company['company_name']); ?></h3>
                        <?php 
                        // Optionally, you can fetch job count for each company
                        try {
                            $jobStmt = $pdo->prepare("SELECT COUNT(*) as job_count FROM jobs_post WHERE company_id = ? AND status = 'active'");
                            $jobStmt->execute([$company['id']]);
                            $jobData = $jobStmt->fetch();
                            $jobCount = $jobData['job_count'] ?? 0;
                        } catch (PDOException $e) {
                            $jobCount = 0;
                        }
                        ?>
                        <p class="mt-1 text-xs text-slate-400"><?php echo $jobCount; ?> open jobs</p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-2 md:col-span-4 text-center py-10">
                    <p class="text-slate-500">No companies found. Please check back later.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>