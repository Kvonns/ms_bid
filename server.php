<?php
require_once 'db.php';

function loginFailedResponse()
{
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password.'
    ]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'insertUser') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : null;
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $password = isset($_POST['password']) ? trim($_POST['password']) : null;

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $prepData = $pdo->prepare("INSERT INTO userdata (username, email, password) VALUES (:username, :email, :password)");
    $prepData->execute([
        ':username' => $username,
        ':email'    => $email,
        ':password' => $hashedPassword
    ]);

    if ($prepData->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'User inserted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed']);
    }
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'loginUser') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : null;
    $password = isset($_POST['password']) ? trim($_POST['password']) : null;

    // 1. Fetch only ONE single user row using ->fetch() instead of fetchAll()
    $prepData = $pdo->prepare("SELECT * FROM userdata WHERE username = :username");
    $prepData->execute([':username' => $username]);
    $user = $prepData->fetch(PDO::FETCH_ASSOC); // Notice: singular 'user'

    // 2. Verify if the user exists and password matches
    if ($user && password_verify($password, $user['password'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!'
        ]);
        exit;
    } else {
        loginFailedResponse();
    }
}

// Helper function to send a generic error

exit;
