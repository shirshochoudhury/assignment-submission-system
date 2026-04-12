<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
redirectIfNotLoggedIn();

$student_id = $_SESSION['user_id'];

// Handle join request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_code = strtoupper($_POST['class_code']);
    
    $stmt = $pdo->prepare("SELECT * FROM classrooms WHERE class_code = ?");
    $stmt->execute([$class_code]);
    $classroom = $stmt->fetch();
    
    if($classroom) {
        // Check if already enrolled
        $check = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND classroom_id = ?");
        $check->execute([$student_id, $classroom['id']]);
        
        if(!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, classroom_id) VALUES (?, ?)");
            $stmt->execute([$student_id, $classroom['id']]);
            $success = "Successfully joined " . htmlspecialchars($classroom['class_name']) . "!";
        } else {
            $error = "You are already enrolled in this class!";
        }
    } else {
        $error = "Invalid class code! Please check and try again.";
    }
}

// Get enrolled classrooms
$stmt = $pdo->prepare("
    SELECT c.*, u.name as teacher_name, COUNT(a.id) as assignment_count
    FROM enrollments e
    JOIN classrooms c ON e.classroom_id = c.id
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN assignments a ON c.id = a.classroom_id
    WHERE e.student_id = ? AND e.status = 'active'
    GROUP BY c.id
");
$stmt->execute([$student_id]);
$myClasses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>PlusWork - My Classes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .class-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            border-left: 5px solid #667eea;
        }
        .class-card:hover { transform: translateX(5px); }
        .join-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 30px;
        }
        .join-card input {
            background: rgba(255,255,255,0.9);
            border: none;
            font-size: 24px;
            text-align: center;
            letter-spacing: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark" style="background: linear-gradient(135deg, #667eea, #764ba2);">
        <div class="container">
            <span class="navbar-brand"><i class="fas fa-users"></i> PlusWork | My Classes</span>
            <div>
                <span class="text-white me-3">👋 <?php echo $_SESSION['user_name']; ?></span>
                <a href="dashboard.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-5">
                <div class="join-card">
                    <h4><i class="fas fa-plus-circle"></i> Join a Class</h4>
                    <p>Enter the 6-digit code from your teacher</p>
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if(isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <input type="text" name="class_code" class="form-control form-control-lg text-center" 
                                   placeholder="XXXXXX" maxlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-light w-100">Join Classroom</button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-7">
                <h4><i class="fas fa-door-open"></i> Your Classes</h4>
                <?php if(count($myClasses) == 0): ?>
                    <div class="alert alert-info">You haven't joined any class yet. Enter a class code to get started!</div>
                <?php endif; ?>
                
                <?php foreach($myClasses as $class): ?>
                <div class="class-card">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5><?php echo htmlspecialchars($class['class_name']); ?></h5>
                            <p class="text-muted small">
                                <?php echo htmlspecialchars($class['subject']); ?> | 
                                Section <?php echo $class['section']; ?> | 
                                <?php echo $class['semester']; ?>
                            </p>
                            <p><small><i class="fas fa-user"></i> Teacher: <?php echo $class['teacher_name']; ?></small></p>
                            <p><small><i class="fas fa-tasks"></i> <?php echo $class['assignment_count']; ?> assignments</small></p>
                        </div>
                        <div>
                            <span class="badge bg-primary">Code: <?php echo $class['class_code']; ?></span>
                        </div>
                    </div>
                    <hr>
                    <a href="classroom.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> Open Class
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>