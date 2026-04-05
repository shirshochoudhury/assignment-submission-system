<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
redirectIfNotLoggedIn();

$teacher_id = $_SESSION['user_id'];

// Handle create assignment with AI recommendations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_assignment'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $max_size = $_POST['max_file_size'] * 1048576;
    $allowed_types = $_POST['allowed_types'];
    $max_submissions = $_POST['max_submissions'];
    $ai_difficulty = $_POST['ai_difficulty'] ?? 3;
    
    $stmt = $pdo->prepare("INSERT INTO assignments (teacher_id, title, description, deadline, max_file_size, allowed_types, max_submissions) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$teacher_id, $title, $description, $deadline, $max_size, $allowed_types, $max_submissions]);
    
    $assignment_id = $pdo->lastInsertId();
    
    // Add analytics record
    $stmt = $pdo->prepare("INSERT INTO assignment_analytics (assignment_id, difficulty_rating) VALUES (?, ?)");
    $stmt->execute([$assignment_id, $ai_difficulty]);
    
    // Create notification for all students
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) 
                           SELECT id, 'New Assignment', ? FROM users WHERE role = 'student'");
    $stmt->execute(["New assignment posted: $title. Deadline: " . date('M j, Y', strtotime($deadline))]);
    
    $success = "Assignment created successfully! AI analytics initialized.";
}

// Fetch assignments with analytics
$stmt = $pdo->prepare("
    SELECT a.*, 
           COUNT(DISTINCT s.id) as submission_count,
           COUNT(DISTINCT s.student_id) as unique_students,
           AVG(s.grade) as avg_grade,
           SUM(CASE WHEN s.is_late = 1 THEN 1 ELSE 0 END) as late_count,
           aa.difficulty_rating,
           aa.total_views
    FROM assignments a 
    LEFT JOIN submissions s ON a.id = s.assignment_id 
    LEFT JOIN assignment_analytics aa ON a.id = aa.assignment_id
    WHERE a.teacher_id = ? 
    GROUP BY a.id
    ORDER BY 
        CASE WHEN a.deadline < NOW() THEN 1 ELSE 0 END ASC,
        a.deadline ASC
");
$stmt->execute([$teacher_id]);
$assignments = $stmt->fetchAll();

// Get overall statistics
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_assignments,
        SUM(submission_count) as total_submissions,
        AVG(avg_grade) as overall_grade
    FROM (
        SELECT COUNT(s.id) as submission_count, AVG(s.grade) as avg_grade
        FROM assignments a
        LEFT JOIN submissions s ON a.id = s.assignment_id
        WHERE a.teacher_id = ?
        GROUP BY a.id
    ) as sub
");
$stats->execute([$teacher_id]);
$overall = $stats->fetch();
?>
<!DOCTYPE html>
<html>
<head>
    <title>PlusWork - Faculty Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-brand { font-weight: bold; font-size: 1.5rem; }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border-left: 4px solid var(--primary);
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-number { font-size: 32px; font-weight: bold; color: var(--primary); }
        .assignment-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .assignment-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .deadline-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .deadline-urgent { background: #dc3545; color: white; }
        .deadline-soon { background: #ffc107; color: #333; }
        .deadline-normal { background: #28a745; color: white; }
        .progress-custom { height: 6px; border-radius: 3px; margin: 10px 0; }
        .btn-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            transition: transform 0.2s;
        }
        .btn-custom:hover { transform: scale(1.05); color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <span class="navbar-brand">
                <i class="fas fa-chalkboard-teacher"></i> PlusWork | Faculty Portal
            </span>
            <div>
                <span class="text-white me-3"><i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?></span>
                <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Exit</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- Statistics Row -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-tasks fa-2x" style="color: #667eea;"></i>
                    <div class="stat-number"><?php echo $overall['total_assignments'] ?? 0; ?></div>
                    <div>Active Assignments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-upload fa-2x" style="color: #28a745;"></i>
                    <div class="stat-number"><?php echo $overall['total_submissions'] ?? 0; ?></div>
                    <div>Total Submissions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-chart-line fa-2x" style="color: #ffc107;"></i>
                    <div class="stat-number"><?php echo round($overall['overall_grade'] ?? 0, 1); ?>%</div>
                    <div>Average Grade</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-clock fa-2x" style="color: #fd7e14;"></i>
                    <div class="stat-number"><?php 
                        $late = array_sum(array_column($assignments, 'late_count'));
                        echo $late;
                    ?></div>
                    <div>Late Submissions</div>
                </div>
            </div>
        </div>
        
        <!-- Create Assignment Card -->
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                <h4 class="mb-0"><i class="fas fa-plus-circle"></i> AI-Powered Assignment Creator</h4>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-heading"></i> Assignment Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g., Machine Learning Project">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><i class="fas fa-calendar-alt"></i> Deadline</label>
                            <input type="datetime-local" name="deadline" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label><i class="fas fa-align-left"></i> Description & Guidelines</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Provide detailed instructions..."></textarea>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label><i class="fas fa-database"></i> Max File Size (MB)</label>
                            <input type="number" name="max_file_size" value="10" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label><i class="fas fa-file-alt"></i> Allowed Types</label>
                            <input type="text" name="allowed_types" value="pdf,docx,zip,pptx" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label><i class="fas fa-redo-alt"></i> Max Submissions</label>
                            <input type="number" name="max_submissions" value="5" class="form-control">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label><i class="fas fa-brain"></i> AI Difficulty Rating</label>
                            <select name="ai_difficulty" class="form-control">
                                <option value="1">⭐ Easy</option>
                                <option value="2">⭐⭐ Medium</option>
                                <option value="3" selected>⭐⭐⭐ Hard</option>
                                <option value="4">⭐⭐⭐⭐ Expert</option>
                                <option value="5">⭐⭐⭐⭐⭐ Master</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="create_assignment" class="btn-custom">
                                <i class="fas fa-magic"></i> Create with AI Analytics
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Assignments List -->
        <h3 class="mb-3"><i class="fas fa-folder-open"></i> Active Assignments</h3>
        
        <?php if(count($assignments) == 0): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> No assignments created yet. Create your first assignment above!
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php foreach($assignments as $assignment):
                $days_left = (new DateTime($assignment['deadline']))->diff(new DateTime())->days;
                $deadline_class = $days_left <= 2 ? 'deadline-urgent' : ($days_left <= 5 ? 'deadline-soon' : 'deadline-normal');
                $submission_rate = $assignment['submission_count'] > 0 ? round(($assignment['unique_students'] / 30) * 100, 1) : 0;
            ?>
            <div class="col-md-6 mb-3">
                <div class="assignment-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5><?php echo htmlspecialchars($assignment['title']); ?></h5>
                        <span class="deadline-badge <?php echo $deadline_class; ?>">
                            <?php echo $days_left > 0 ? "$days_left days left" : "Closed"; ?>
                        </span>
                    </div>
                    <p class="text-muted small">Created: <?php echo date('M j, Y', strtotime($assignment['created_at'])); ?></p>
                    
                    <div class="row mt-3">
                        <div class="col-6">
                            <small>Submissions</small>
                            <strong><?php echo $assignment['submission_count']; ?> total</strong>
                        </div>
                        <div class="col-6">
                            <small>Average Grade</small>
                            <strong><?php echo round($assignment['avg_grade'] ?? 0, 1); ?>%</strong>
                        </div>
                    </div>
                    
                    <div class="progress-custom">
                        <div class="progress-bar" style="width: <?php echo $submission_rate; ?>%; background: linear-gradient(90deg, #667eea, #764ba2);"></div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="view_submissions.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> Review (<?php echo $assignment['submission_count']; ?>)
                        </a>
                        <a href="download_bulk.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-download"></i> Export ZIP
                        </a>
                        <a href="analytics.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-chart-line"></i> Insights
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>