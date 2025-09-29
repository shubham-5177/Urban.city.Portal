<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type
header('Content-Type: application/json');

// Enable CORS if needed (adjust origin as needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Database configuration - UPDATE THESE VALUES FOR YOUR CLOUD DATABASE
$db_config = [
    // Example for AWS RDS MySQL
    'host' => 'your-rds-endpoint.region.rds.amazonaws.com',
    'port' => '3306',
    'dbname' => 'urbancityportal',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4'
];


try {

    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
    
    $pdo_options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $pdo_options);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}


$required_fields = ['fullName', 'email', 'category', 'title', 'description', 'address'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
    exit;
}

// Sanitize and validate input data
$full_name = trim($_POST['fullName']);
$email = trim($_POST['email']);
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;
$category = trim($_POST['category']);
$title = trim($_POST['title']);
$description = trim($_POST['description']);
$priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'medium';
$address = trim($_POST['address']);
$landmarks = isset($_POST['landmarks']) ? trim($_POST['landmarks']) : null;

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

// Validate category
$valid_categories = ['infrastructure', 'public-safety', 'environment', 'transportation', 'public-services', 'community', 'other'];
if (!in_array($category, $valid_categories)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid category']);
    exit;
}

// Validate priority
$valid_priorities = ['low', 'medium', 'high'];
if (!in_array($priority, $valid_priorities)) {
    $priority = 'medium'; // Default fallback
}

// Handle file uploads
$uploaded_files = [];
$upload_dir = 'uploads/reports/';

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $file_count = count($_FILES['photos']['name']);
    
    for ($i = 0; $i < $file_count && $i < 5; $i++) {
        if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['photos']['tmp_name'][$i];
            $file_name = $_FILES['photos']['name'][$i];
            $file_size = $_FILES['photos']['size'][$i];
            $file_type = $_FILES['photos']['type'][$i];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file_type, $allowed_types)) {
                continue; // Skip invalid file types
            }
            
            // Validate file size (5MB max)
            if ($file_size > 5 * 1024 * 1024) {
                continue; // Skip files that are too large
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_name = uniqid('report_') . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $unique_name;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $file_path)) {
                $uploaded_files[] = $file_path;
            }
        }
    }
}

try {
    // Insert report into database
    $sql = "INSERT INTO reports (
        full_name, email, phone, category, title, description, 
        priority, address, landmarks, photos, status, created_at
    ) VALUES (
        :full_name, :email, :phone, :category, :title, :description,
        :priority, :address, :landmarks, :photos, 'pending', NOW()
    )";
    
    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':full_name' => $full_name,
        ':email' => $email,
        ':phone' => $phone,
        ':category' => $category,
        ':title' => $title,
        ':description' => $description,
        ':priority' => $priority,
        ':address' => $address,
        ':landmarks' => $landmarks,
        ':photos' => json_encode($uploaded_files)
    ];
    
    $stmt->execute($params);
    
    $report_id = $pdo->lastInsertId();
    
    // Send success response
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully',
        'report_id' => $report_id,
        'uploaded_files' => count($uploaded_files)
    ]);
    
    // Optional: Send email notification (uncomment and configure if needed)
    /*
    $to = 'admin@urbancityportal.com';
    $subject = 'New Problem Report: ' . $title;
    $message = "A new problem report has been submitted:\n\n";
    $message .= "Name: " . $full_name . "\n";
    $message .= "Email: " . $email . "\n";
    $message .= "Category: " . $category . "\n";
    $message .= "Title: " . $title . "\n";
    $message .= "Priority: " . $priority . "\n";
    $message .= "Address: " . $address . "\n\n";
    $message .= "Description: " . $description . "\n";
    
    $headers = 'From: noreply@urbancityportal.com' . "\r\n" .
               'Reply-To: ' . $email . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    
    mail($to, $subject, $message, $headers);
    */
    
} catch (PDOException $e) {
    // Clean up uploaded files if database insert fails
    foreach ($uploaded_files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save report: ' . $e->getMessage()]);
}
?>