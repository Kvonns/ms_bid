<?php
$host = "aws-1-ap-northeast-2.pooler.supabase.com";
$port = "5432";
$dbname = "postgres";
$user = "postgres.nljktpweonrollpodkpu";
$password = "nHrUOk0t3rFeNc0D";

try {
    // Connect to Supabase PostgreSQL database
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
