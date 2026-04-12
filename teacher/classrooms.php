<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
if (isStudent()) header("Location: ../student/dashboard.php");

$teacher_id = $_SESSION['user_id'];

// Handle create classroom
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_classroom'])) {
    $class_name = $_POST['class_name'];
    $section = $_POST['section'];
    $semester = $_POST['semester'];
    $subject = $_POST['subject'];
    $room = $_POST['room'];
    $description = $_POST['description'];
    
    // Generate unique 6-character code
    $class_code = strtoupper(substr(md5(uniqid()), 0, 6));
    
    $stmt = $pdo->prepare("INSERT INTO classrooms (teacher_id, class_name, class_code, section, semester, subject, room, description) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$teacher_id, $class_name, $class_code, $section, $semester, $subject, $room, $description]);
    
    $success = "Classroom created! Share code: <strong>$class_code</strong>";
}

// Handle delete classroom
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM classrooms WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$_GET['delete'], $teacher_id]);
    header("Location: classrooms.php");
    exit();
}

// Get teacher's classrooms
$stmt = $pdo->prepare("
    SELECT c.*, COUNT(DISTINCT e.student_id) as student_count,
           COUNT(DISTINCT a.id) as assignment_count
    FROM classrooms c
    LEFT JOIN enrollments e ON c.id = e.classroom_id AND e.status = 'active'
    LEFT JOIN assignments a ON c.id = a.classroom_id
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute([$teacher_id]);
$classrooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>PlusWork - My Classrooms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .classroom-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .classroom-card:hover { transform: translateY(-5px); }
        .classroom-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
        }
        .class-code {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 2px;
        }
        .stat-badge {
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background: linear-gradient(135deg, #667eea, #764ba2);">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-chalkboard"></i> PlusWork | My Classrooms</span>
            <div>
                <span class="text-white me-3">👨‍🏫 <?php echo $_SESSION['user_name']; ?></span>
                <a href="dashboard.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- Create Classroom Button -->
        <div class="d-flex justify-content-between mb-4">
            <h3><i class="fas fa-door-open"></i> Your Classrooms</h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="fas fa-plus-circle"></i> New Classroom
            </button>
        </div>
        
        <?php if(isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php if(count($classrooms) == 0): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-2x"></i>
                        <p>No classrooms yet. Click "New Classroom" to get started!</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php foreach($classrooms as $class): ?>
            <div class="col-md-6 col-lg-4">
                <div class="classroom-card">
                    <div class="classroom-header">
                        <h5><i class="fas fa-school"></i> <?php echo htmlspecialchars($class['class_name']); ?></h5>
                        <div class="mt-2">
                            <span class="class-code"><i class="fas fa-code"></i> <?php echo $class['class_code']; ?></span>
                        </div>
                    </div>
                    <div class="p-3">
                        <p class="text-muted small"><?php echo htmlspecialchars($class['subject']); ?> | <?php echo $class['section']; ?> | <?php echo $class['semester']; ?></p>
                        <p><small><?php echo htmlspecialchars($class['description']); ?></small></p>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="stat-badge"><i class="fas fa-users"></i> <?php echo $class['student_count']; ?> students</span>
                            <span class="stat-badge"><i class="fas fa-tasks"></i> <?php echo $class['assignment_count']; }} assignments</span>
                        </div>
                        <hr>
                        <div class="d-flex gap-2">
                            <a href="classroom.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary flex-grow-1">
                                <i class="fas fa-eye"></i> Open
                            </a>
                            <a href="?delete=<?php echo $class['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this classroom?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Create Classroom Modal -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5><i class="fas fa-plus-circle"></i> Create New Classroom</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Class Name *</label>
                            <input type="text" name="class_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Subject</label>
                                <input type="text" name="subject" class="form-control" placeholder="e.g., Mathematics">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Section</label>
                                <input type="text" name="section" class="form-control" placeholder="e.g., A">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Semester</label>
                                <input type="text" name="semester" class="form-control" placeholder="e.g., Spring 2024">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Room</label>
                                <input type="text" name="room" class="form-control" placeholder="e.g., Hall 301">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_classroom" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>