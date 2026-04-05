<?php
function checkLateSubmission($deadline) {
    $now = new DateTime();
    $deadlineDate = new DateTime($deadline);
    return $now > $deadlineDate;
}

function validateFile($file, $allowedTypes, $maxSize) {
    $errors = [];
    $allowed = explode(',', $allowedTypes);
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowed)) {
        $errors[] = "File type not allowed. Allowed: " . $allowedTypes;
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = "File too large. Max size: " . ($maxSize/1048576) . "MB";
    }
    
    return $errors;
}

function getSubmissionCount($assignmentId, $studentId, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignmentId, $studentId]);
    return $stmt->fetchColumn();
}
?>