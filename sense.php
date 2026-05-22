<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPassword = getenv('DB_PASSWORD') ?: 'Jen12345';
$dbName = getenv('DB_NAME') ?: 'robo_system';
$binHeightCm = (float) (getenv('BIN_HEIGHT_CM') ?: 20);

function respond(int $statusCode, array $payload): void
{
	http_response_code($statusCode);
	echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}

function connectDatabase(string $host, string $user, string $password, string $database): ?PDO
{
	try {
		$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $database);
		return new PDO($dsn, $user, $password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	} catch (Throwable $exception) {
		return null;
	}
}

function fillFromDistance(float $distanceCm, float $binHeightCm): int
{
	if ($binHeightCm <= 0) {
		$binHeightCm = 20;
	}

	$ratio = (($binHeightCm - $distanceCm) / $binHeightCm) * 100;
	$ratio = max(0, min(100, $ratio));
	return (int) round($ratio);
}

function statusFromFill(int $fillPercent): string
{
	if ($fillPercent < 50) {
		return 'AVAILABLE';
	}

	if ($fillPercent < 85) {
		return 'NEARLY FULL';
	}

	return 'COLLECTION REQUIRED';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	respond(405, [
		'success' => false,
		'message' => 'Use POST with sensor_distance_cm.',
		'example' => [
			'url' => '/Robotics-Trash-Bin/sense.php',
			'body' => ['sensor_distance_cm' => 7.5],
		],
	]);
}

$rawInput = file_get_contents('php://input');
$jsonBody = null;

if (is_string($rawInput) && trim($rawInput) !== '') {
	$decoded = json_decode($rawInput, true);
	if (is_array($decoded)) {
		$jsonBody = $decoded;
	}
}

$distanceValue = $_POST['sensor_distance_cm'] ?? $_GET['sensor_distance_cm'] ?? ($jsonBody['sensor_distance_cm'] ?? null);

if ($distanceValue === null || !is_numeric($distanceValue)) {
	respond(422, [
		'success' => false,
		'message' => 'sensor_distance_cm is required and must be numeric.',
	]);
}

$sensorDistanceCm = (float) $distanceValue;
$fillPercentage = fillFromDistance($sensorDistanceCm, $binHeightCm);
$binStatus = statusFromFill($fillPercentage);

$pdo = connectDatabase($dbHost, $dbUser, $dbPassword, $dbName);

if ($pdo === null) {
	respond(500, [
		'success' => false,
		'message' => 'Database connection failed.',
	]);
}

try {
	$statement = $pdo->prepare(
		'INSERT INTO waste_logs (sensor_distance_cm, fill_percentage, bin_status) VALUES (:distance, :fill, :status)'
	);
	$statement->execute([
		':distance' => $sensorDistanceCm,
		':fill' => $fillPercentage,
		':status' => $binStatus,
	]);

	respond(201, [
		'success' => true,
		'message' => 'Sensor reading saved.',
		'data' => [
			'id' => (int) $pdo->lastInsertId(),
			'sensor_distance_cm' => $sensorDistanceCm,
			'fill_percentage' => $fillPercentage,
			'bin_status' => $binStatus,
			'bin_height_cm' => $binHeightCm,
		],
	]);
} catch (Throwable $exception) {
	respond(500, [
		'success' => false,
		'message' => 'Failed to save sensor reading.',
		'error' => $exception->getMessage(),
	]);
}
