<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
// require_once '../includes/notifications.php'; // Temporarily disabled
redirectIfNotLoggedIn();

$student_id = $_SESSION['user_id'];

// Get student performance
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.id) as total,
        COUNT(s.id) as submitted,
        AVG(s.grade) as avg_grade,
        sp.gpa,
        sp.rank
    FROM assignments a
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    LEFT JOIN student_performance sp ON sp.student_id = ?
    WHERE 1=1
");
$stmt->execute([$student_id, $student_id]);
$performance = $stmt->fetch();

// Get assignments with AI recommendations
$stmt = $pdo->prepare("
    SELECT a.*, u.name as teacher_name,
    (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id AND student_id = ?) as submission_count,
    (SELECT grade FROM submissions WHERE assignment_id = a.id AND student_id = ? ORDER BY submitted_at DESC LIMIT 1) as last_grade,
    (SELECT feedback FROM submissions WHERE assignment_id = a.id AND student_id = ? ORDER BY submitted_at DESC LIMIT 1) as last_feedback
    FROM assignments a
    JOIN users u ON a.teacher_id = u.id
    ORDER BY 
        CASE WHEN a.deadline < NOW() THEN 1 ELSE 0 END ASC,
        a.deadline ASC
");
$stmt->execute([$student_id, $student_id, $student_id]);
$assignments = $stmt->fetchAll();

// Calculate GPA prediction
$predicted_gpa = $performance['avg_grade'] ? round(($performance['avg_grade'] / 100) * 4, 2) : 0;
?>
<!DOCTYPE html>
<html>
<head>
   <title>TEST 123 - If you see this, it worked</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card:hover { transform: translateY(-5px); }
        .assignment-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 5px solid #667eea;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .assignment-card:hover { transform: translateX(5px); }
        .gpa-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: conic-gradient(#28a745 0deg 270deg, #e0e0e0 270deg 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .gpa-inner {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .navbar-brand { font-weight: bold; font-size: 1.5rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background: linear-gradient(135deg, #667eea, #764ba2);">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-user-graduate"></i> PlusWork | Student Portal</span>
            <div>
                <span class="text-white me-3"><i class="fas fa-bell"></i> 👋 <?php echo $_SESSION['user_name']; ?></span>
                <a href="../logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-chalkboard"></i> Welcome back, <?php echo $_SESSION['user_name']; ?>!</h1>
                    <p>Your AI-powered learning assistant is ready. Track your progress and never miss a deadline.</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="gpa-circle">
                        <div class="gpa-inner">
                            <?php echo $predicted_gpa ?: 'N/A'; ?>
                        </div>
                    </div>
                    <small class="text-white">Predicted GPA</small>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-tasks fa-3x" style="color: #667eea;"></i>
                    <h3><?php echo $performance['total'] ?? 0; ?></h3>
                    <p>Total Assignments</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-check-circle fa-3x" style="color: #28a745;"></i>
                    <h3><?php echo $performance['submitted'] ?? 0; ?></h3>
                    <p>Submitted</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <i class="fas fa-star fa-3x" style="color: #ffc107;"></i>
                    <h3><?php echo round($performance['avg_grade'] ?? 0, 1); ?>%</h3>
                    <p>Average Grade</p>
                </div>
            </div>
        </div>
        
        <h3 class="mb-3"><i class="fas fa-clipboard-list"></i> Your Assignments</h3>
        
        <?php if(count($assignments) == 0): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> No assignments posted yet. Check back later!
            </div>
        <?php endif; ?>
        
        <?php foreach($assignments as $assignment):
            $days_left = (new DateTime($assignment['deadline']))->diff(new DateTime())->days;
            $status_class = $days_left <= 2 ? 'danger' : ($days_left <= 5 ? 'warning' : 'success');
            $can_submit = $assignment['submission_count'] < $assignment['max_submissions'];
            $is_late = (new DateTime() > new DateTime($assignment['deadline']));
        ?>
        <div class="assignment-card">
            <div class="row">
                <div class="col-md-8">
                    <h5><?php echo htmlspecialchars($assignment['title']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($assignment['description']); ?></p>
                    <small><i class="fas fa-user"></i> <?php echo $assignment['teacher_name']; ?></small><br>
                    <small><i class="fas fa-calendar"></i> Deadline: <?php echo date('F j, Y g:i A', strtotime($assignment['deadline'])); ?></small>
                    
                    <?php if($assignment['last_grade']): ?>
                        <div class="mt-2">
                            <span class="badge bg-info">Grade: <?php echo $assignment['last_grade']; ?>%</span>
                            <?php if($assignment['last_feedback']): ?>
                                <small class="text-muted ms-2">📝 Feedback: <?php echo htmlspecialchars($assignment['last_feedback']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-2">
                        <small class="text-muted">Submissions: <?php echo $assignment['submission_count']; ?>/<?php echo $assignment['max_submissions']; ?></small>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-<?php echo $status_class; ?> mb-2">
                        <?php echo $days_left > 0 ? "$days_left days left" : "Closed"; ?>
                    </span>
                    
                    <?php if($can_submit): ?>
                        <?php if($is_late): ?>
                            <div class="alert alert-warning alert-sm p-1 mb-2 small">⚠️ Late submission</div>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#submitModal<?php echo $assignment['id']; ?>">
                            <i class="fas fa-upload"></i> Submit Assignment
                        </button>
                    <?php elseif($assignment['submission_count'] > 0): ?>
                        <button class="btn btn-secondary btn-sm w-100" disabled>
                            <i class="fas fa-check"></i> Submitted
                        </button>
                    <?php else: ?>
                        <button class="btn btn-danger btn-sm w-100" disabled>
                            <i class="fas fa-times"></i> Deadline Passed
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Submit Modal -->
        <div class="modal fade" id="submitModal<?php echo $assignment['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="submit.php" method="POST" enctype="multipart/form-data">
                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                            <h5 class="modal-title"><i class="fas fa-upload"></i> Submit: <?php echo htmlspecialchars($assignment['title']); ?></h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Choose File</label>
                                <input type="file" name="submission_file" class="form-control" accept=".pdf,.docx,.zip" required>
                                <small class="text-muted">Allowed: PDF, DOCX, ZIP | Max size: <?php echo $assignment['max_file_size']/1048576; ?>MB</small>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> You have used <?php echo $assignment['submission_count']; ?> out of <?php echo $assignment['max_submissions']; ?> submissions.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Upload Submission</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>