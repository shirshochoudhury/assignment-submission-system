<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
redirectIfNotLoggedIn();

$assignment_id = $_GET['id'] ?? 0;

// Get assignment analytics
$stmt = $pdo->prepare("
    SELECT a.*, 
           COUNT(s.id) as total_submissions,
           AVG(s.grade) as avg_grade,
           MIN(s.grade) as min_grade,
           MAX(s.grade) as max_grade,
           SUM(CASE WHEN s.is_late = 1 THEN 1 ELSE 0 END) as late_count,
           aa.difficulty_rating,
           aa.total_views
    FROM assignments a
    LEFT JOIN submissions s ON a.id = s.assignment_id
    LEFT JOIN assignment_analytics aa ON a.id = aa.assignment_id
    WHERE a.id = ?
    GROUP BY a.id
");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();

// Grade distribution
$grades = $pdo->prepare("
    SELECT 
        CASE 
            WHEN grade >= 90 THEN 'A'
            WHEN grade >= 80 THEN 'B'
            WHEN grade >= 70 THEN 'C'
            WHEN grade >= 60 THEN 'D'
            ELSE 'F'
        END as grade_letter,
        COUNT(*) as count
    FROM submissions
    WHERE assignment_id = ? AND grade IS NOT NULL
    GROUP BY grade_letter
");
$grades->execute([$assignment_id]);
$grade_distribution = $grades->fetchAll();

// Submission timeline
$timeline = $pdo->prepare("
    SELECT DATE(submitted_at) as date, COUNT(*) as count
    FROM submissions
    WHERE assignment_id = ?
    GROUP BY DATE(submitted_at)
    ORDER BY date
");
$timeline->execute([$assignment_id]);
$timeline_data = $timeline->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Advanced Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f5f5f5; }
        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .insight-box {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="analytics-card">
            <h2><i class="fas fa-chart-line"></i> Assignment Analytics</h2>
            <h4><?php echo htmlspecialchars($assignment['title']); ?></h4>
            
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="insight-box">
                        <h5>Submission Rate</h5>
                        <h2><?php echo round(($assignment['total_submissions'] / 30) * 100, 1); ?>%</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="insight-box">
                        <h5>Average Grade</h5>
                        <h2><?php echo round($assignment['avg_grade'] ?? 0, 1); ?>%</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="insight-box">
                        <h5>AI Difficulty</h5>
                        <h2><?php echo str_repeat('⭐', $assignment['difficulty_rating'] ?? 3); ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <canvas id="gradeChart"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="timelineChart"></canvas>
                </div>
            </div>
            
            <div class="mt-4">
                <h5>AI Insights</h5>
                <ul>
                    <li>📊 <?php echo $assignment['late_count']; ?> students submitted late</li>
                    <li>🏆 Top grade: <?php echo round($assignment['max_grade'] ?? 0, 1); ?>%</li>
                    <li>📈 Improvement needed: <?php echo round($assignment['min_grade'] ?? 0, 1); ?>% lowest score</li>
                    <li>🎯 Recommendation: <?php echo $assignment['avg_grade'] < 70 ? 'Schedule review session' : 'Great performance overall'; ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        // Grade distribution chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($grade_distribution, 'grade_letter')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($grade_distribution, 'count')); ?>,
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545']
                }]
            }
        });
        
        // Timeline chart
        const timelineCtx = document.getElementById('timelineChart').getContext('2d');
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($timeline_data, 'date')); ?>,
                datasets: [{
                    label: 'Submissions per day',
                    data: <?php echo json_encode(array_column($timeline_data, 'count')); ?>,
                    borderColor: '#667eea',
                    tension: 0.4
                }]
            }
        });
    </script>
</body>
</html>