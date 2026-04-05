<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
redirectIfNotLoggedIn();

$assignment_id = $_GET['id'];

// Get assignment info
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    die("Assignment not found or you don't have permission.");
}

// Get all submissions
$stmt = $pdo->prepare("
    SELECT s.*, u.name as student_name, u.email 
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at DESC
");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();

// Handle grading
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_grade'])) {
    $submission_id = $_POST['submission_id'];
    $grade = $_POST['grade'];
    $feedback = $_POST['feedback'];
    
    $stmt = $pdo->prepare("UPDATE submissions SET grade = ?, feedback = ? WHERE id = ?");
    $stmt->execute([$grade, $feedback, $submission_id]);
    $success = "Grade saved!";
    header("Location: view_submissions.php?id=$assignment_id");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>PlusWork - <?php echo htmlspecialchars($assignment['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand">📋 Submissions: <?php echo htmlspecialchars($assignment['title']); ?></span>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5>Assignment Details</h5>
            </div>
            <div class="card-body">
                <p><strong>Deadline:</strong> <?php echo $assignment['deadline']; ?></p>
                <p><strong>Total Submissions:</strong> <?php echo count($submissions); ?></p>
            </div>
        </div>
        
        <h3 class="mt-4">Student Submissions</h3>
        
        <?php if(count($submissions) == 0): ?>
            <div class="alert alert-warning">No submissions yet for this assignment.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Student</th>
                            <th>File Name</th>
                            <th>Submitted At</th>
                            <th>Status</th>
                            <th>Submission #</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($submissions as $sub): ?>
                        <tr>
                            <td><?php echo $sub['student_name']; ?><br><small><?php echo $sub['email']; ?></small></td>
                            <td><?php echo $sub['original_filename']; ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($sub['submitted_at'])); ?></td>
                            <td>
                                <?php if($sub['is_late']): ?>
                                    <span class="badge bg-danger">⚠️ LATE</span>
                                <?php else: ?>
                                    <span class="badge bg-success">✓ On Time</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $sub['submission_number']; ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                    <input type="number" name="grade" step="0.01" class="form-control form-control-sm" style="width: 70px; display: inline;" value="<?php echo $sub['grade']; ?>" placeholder="Grade">
                                    <button type="submit" name="save_grade" class="btn btn-sm btn-success">Save</button>
                                </form>
                            </td>
                            <td>
                                <a href="../assets/uploads/assignments/<?php echo $sub['file_path']; ?>" class="btn btn-sm btn-primary" download>📥 Download</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>