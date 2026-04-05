<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
redirectIfNotLoggedIn();

$assignment_id = $_GET['id'];

// Verify teacher owns this assignment
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
$stmt->execute([$assignment_id, $_SESSION['user_id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    die("Assignment not found.");
}

// Get all submissions
$stmt = $pdo->prepare("
    SELECT s.*, u.name as student_name
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();

if (empty($submissions)) {
    die("No submissions to download.");
}

// Create ZIP file
$zip = new ZipArchive();
$zipName = "assignment_" . $assignment_id . "_" . date('Ymd_His') . ".zip";
$zipPath = "../assets/uploads/" . $zipName;

if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
    foreach ($submissions as $submission) {
        $filePath = "../assets/uploads/assignments/" . $submission['file_path'];
        if (file_exists($filePath)) {
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $submission['student_name']);
            $newName = $safeName . "_" . $submission['original_filename'];
            $zip->addFile($filePath, $newName);
        }
    }
    $zip->close();
    
    // Download the file
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    
    // Delete temp file
    unlink($zipPath);
} else {
    die("Could not create ZIP file.");
}
?>