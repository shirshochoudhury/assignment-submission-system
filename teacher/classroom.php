<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
redirectIfNotLoggedIn();

$classroom_id = $_GET['id'];
$teacher_id = $_SESSION['user_id'];

// Verify teacher owns this classroom
$stmt = $pdo->prepare("SELECT * FROM classrooms WHERE id = ? AND teacher_id = ?");
$stmt->execute([$classroom_id, $teacher_id]);
$classroom = $stmt->fetch();

if (!$classroom) {
    die("Classroom not found or you don't have permission.");
}

// Handle announcement post
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_announcement'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    
    $stmt = $pdo->prepare("INSERT INTO announcements (classroom_id, teacher_id, title, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([$classroom_id, $teacher_id, $title, $content]);
    $success = "Announcement posted!";
}

// Handle material upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_material'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $topic = $_POST['topic'];
    
    $file_path = null;
    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] == 0) {
        $uploadDir = "../assets/uploads/materials/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $file_path = time() . "_" . basename($_FILES['material_file']['name']);
        move_uploaded_file($_FILES['material_file']['tmp_name'], $uploadDir . $file_path);
    }
    
    $stmt = $pdo->prepare("INSERT INTO materials (classroom_id, teacher_id, title, description, file_path, topic) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$classroom_id, $teacher_id, $title, $description, $file_path, $topic]);
    $success = "Material uploaded!";
}

// Get announcements
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE classroom_id = ? ORDER BY pinned DESC, created_at DESC");
$stmt->execute([$classroom_id]);
$announcements = $stmt->fetchAll();

// Get materials
$stmt = $pdo->prepare("SELECT * FROM materials WHERE classroom_id = ? ORDER BY created_at DESC");
$stmt->execute([$classroom_id]);
$materials = $stmt->fetchAll();

// Get students
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, e.enrolled_at,
           COUNT(s.id) as submissions_count
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    LEFT JOIN submissions s ON s.student_id = u.id
    WHERE e.classroom_id = ? AND e.status = 'active'
    GROUP BY u.id
");
$stmt->execute([$classroom_id]);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($classroom['class_name']); ?> - PlusWork</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark" style="background: linear-gradient(135deg, #667eea, #764ba2);">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-chalkboard"></i> <?php echo htmlspecialchars($classroom['class_name']); ?></span>
            <div>
                <a href="classrooms.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> All Classes</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- Class Header -->
        <div class="card mb-4 bg-light">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h3><?php echo htmlspecialchars($classroom['class_name']); ?></h3>
                        <p><?php echo htmlspecialchars($classroom['description']); ?></p>
                        <div class="row">
                            <div class="col-md-3"><small><i class="fas fa-book"></i> <?php echo $classroom['subject']; ?></small></div>
                            <div class="col-md-3"><small><i class="fas fa-users"></i> Section <?php echo $classroom['section']; ?></small></div>
                            <div class="col-md-3"><small><i class="fas fa-calendar"></i> <?php echo $classroom['semester']; ?></small></div>
                            <div class="col-md-3"><small><i class="fas fa-door-open"></i> Room <?php echo $classroom['room']; ?></small></div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="alert alert-info">
                            <strong><i class="fas fa-code"></i> Class Code:</strong>
                            <h4 class="mb-0"><?php echo $classroom['class_code']; ?></h4>
                            <small>Share this code with students</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#stream">Stream</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#materials">Materials</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#people">People (<?php echo count($students); ?>)</button>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- STREAM TAB -->
            <div class="tab-pane fade show active" id="stream">
                <!-- Post Announcement -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-bullhorn"></i> Make an Announcement
                    </div>
                    <div class="card-body">
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <input type="text" name="title" class="form-control" placeholder="Announcement title" required>
                            </div>
                            <div class="mb-3">
                                <textarea name="content" class="form-control" rows="3" placeholder="Write your announcement here..." required></textarea>
                            </div>
                            <button type="submit" name="post_announcement" class="btn btn-primary">Post Announcement</button>
                        </form>
                    </div>
                </div>
                
                <!-- Announcements List -->
                <?php foreach($announcements as $ann): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <strong><i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($ann['title']); ?></strong>
                        <small class="text-muted float-end"><?php echo date('M j, Y g:i A', strtotime($ann['created_at'])); ?></small>
                    </div>
                    <div class="card-body">
                        <p><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if(count($announcements) == 0): ?>
                    <div class="alert alert-info">No announcements yet. Post your first announcement!</div>
                <?php endif; ?>
            </div>
            
            <!-- MATERIALS TAB -->
            <div class="tab-pane fade" id="materials">
                <div class="row">
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-upload"></i> Upload Material
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label>Title</label>
                                        <input type="text" name="title" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Topic</label>
                                        <input type="text" name="topic" class="form-control" placeholder="e.g., Lecture 1, Reading">
                                    </div>
                                    <div class="mb-3">
                                        <label>Description</label>
                                        <textarea name="description" class="form-control" rows="2"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label>File (PDF, DOC, PPT, ZIP)</label>
                                        <input type="file" name="material_file" class="form-control">
                                    </div>
                                    <button type="submit" name="upload_material" class="btn btn-success w-100">Upload Material</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <h5><i class="fas fa-folder-open"></i> Class Materials</h5>
                        <?php foreach($materials as $material): ?>
                        <div class="card mb-2">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($material['title']); ?></strong>
                                        <?php if($material['topic']): ?>
                                            <span class="badge bg-secondary"><?php echo $material['topic']; ?></span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($material['description']); ?></small>
                                    </div>
                                    <?php if($material['file_path']): ?>
                                    <a href="../assets/uploads/materials/<?php echo $material['file_path']; ?>" class="btn btn-sm btn-primary" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- PEOPLE TAB -->
            <div class="tab-pane fade" id="people">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-users"></i> Enrolled Students
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr><th>Student Name</th><th>Email</th><th>Enrolled Date</th><th>Submissions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach($students as $student): ?>
                                    <tr>
                                        <td><?php echo $student['name']; ?></td>
                                        <td><?php echo $student['email']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($student['enrolled_at'])); ?></td>
                                        <td><?php echo $student['submissions_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>