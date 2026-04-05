<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = $_POST['assignment_id'];
    $student_id = $_SESSION['user_id'];
    
    // Get assignment details
    $stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ?");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) die("Assignment not found!");
    
    // Check submission limit
    $submissionCount = getSubmissionCount($assignment_id, $student_id, $pdo);
    if ($submissionCount >= $assignment['max_submissions']) {
        die("Maximum submissions reached.");
    }
    
    // File validation
    if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] != 0) {
        die("Please select a file.");
    }
    
    $isLate = checkLateSubmission($assignment['deadline']);
    $fileErrors = validateFile($_FILES['submission_file'], $assignment['allowed_types'], $assignment['max_file_size']);
    if (!empty($fileErrors)) die(implode("<br>", $fileErrors));
    
    // Create upload directory
    $uploadDir = "../assets/uploads/assignments/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    // Generate unique filename with hash
    $file_hash = hash_file('sha256', $_FILES['submission_file']['tmp_name']);
    $fileName = time() . "_" . $student_id . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['submission_file']['name']));
    $uploadPath = $uploadDir . $fileName;
    
    // Check for duplicate submission
    $stmt = $pdo->prepare("SELECT id FROM submissions WHERE file_hash = ? AND assignment_id = ?");
    $stmt->execute([$file_hash, $assignment_id]);
    if($stmt->fetch()) {
        die("This file has already been submitted for this assignment!");
    }
    
    // Move file
    if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $uploadPath)) {
        // Auto-grade if possible (simple keyword matching)
        $auto_grade = null;
        $content = file_get_contents($uploadPath);
        if(strlen($content) > 100) {
            $keywords = ['excellent', 'perfect', 'good', 'average'];
            foreach($keywords as $idx => $kw) {
                if(stripos($content, $kw) !== false) {
                    $auto_grade = (5 - $idx) * 20;
                    break;
                }
            }
        }
        
        // Save submission
        $stmt = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, original_filename, submission_number, is_late, file_hash, auto_grade) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$assignment_id, $student_id, $fileName, $_FILES['submission_file']['name'], $submissionCount + 1, $isLate ? 1 : 0, $file_hash, $auto_grade]);
        
        // Update analytics
        $stmt = $pdo->prepare("UPDATE assignment_analytics SET total_views = total_views + 1 WHERE assignment_id = ?");
        $stmt->execute([$assignment_id]);
        
        // Create notification for teacher
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) 
                               SELECT teacher_id, 'New Submission', ? FROM assignments WHERE id = ?");
        $stmt->execute(["New submission for: {$assignment['title']} from {$_SESSION['user_name']}", $assignment_id]);
        
        header("Location: dashboard.php?success=1");
        exit();
    } else {
        die("Upload failed. Check folder permissions.");
    }
}
?>