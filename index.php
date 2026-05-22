<?php
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPassword = getenv('DB_PASSWORD') ?: '';
$dbName = getenv('DB_NAME') ?: 'robo_system';
$arduinoPort = getenv('ARDUINO_PORT') ?: 'COM3';
$refreshIntervalMs = (int)(getenv('REFRESH_INTERVAL_MS') ?: 2000);
$devicePath = '\\\\.\\' . $arduinoPort;

function e(mixed $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function latestStatusFromFill(int $fillPercent): array
{
	if ($fillPercent < 50) {
		return ['AVAILABLE', 'success', '🟢 Bin Status: AVAILABLE (Optimal Capacity)'];
	}

	if ($fillPercent < 85) {
		return ['NEARLY FULL', 'warning', '🟡 Bin Status: NEARLY FULL (Schedule Collection Soon)'];
	}

	return ['COLLECTION REQUIRED', 'danger', '🔴 Bin Status: COLLECTION REQUIRED (Urgent Waste Overflow Risk!)'];
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

function connectServer(string $host, string $user, string $password): ?PDO
{
	try {
		$dsn = sprintf('mysql:host=%s;charset=utf8mb4', $host);
		return new PDO($dsn, $user, $password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	} catch (Throwable $exception) {
		return null;
	}
}

function bootstrapDatabase(string $host, string $user, string $password, string $database): bool
{
	$server = connectServer($host, $user, $password);
	if ($server === null) {
		return false;
	}

	$quotedDatabase = str_replace('`', '``', $database);

	try {
		$server->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $quotedDatabase));
		$server->exec(sprintf('USE `%s`', $quotedDatabase));
		$server->exec('CREATE TABLE IF NOT EXISTS waste_logs (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			sensor_distance_cm DECIMAL(10,2) NOT NULL,
			fill_percentage TINYINT UNSIGNED NOT NULL,
			bin_status VARCHAR(32) NOT NULL,
			PRIMARY KEY (id),
			KEY idx_waste_logs_timestamp (`timestamp`),
			KEY idx_waste_logs_fill_percentage (fill_percentage)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
		return true;
	} catch (Throwable $exception) {
		return false;
	}
}

function fetchLogs(PDO $pdo, int $limit = 50): array
{
	$statement = $pdo->prepare('SELECT id, `timestamp`, sensor_distance_cm, fill_percentage, bin_status FROM waste_logs ORDER BY `timestamp` ASC, id ASC LIMIT :limit');
	$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
	$statement->execute();
	return $statement->fetchAll();
}

function sampleLogs(): array
{
	return [
		['id' => 1, 'timestamp' => '2026-05-22 08:00:00', 'sensor_distance_cm' => 18.40, 'fill_percentage' => 8, 'bin_status' => 'AVAILABLE'],
		['id' => 2, 'timestamp' => '2026-05-22 09:00:00', 'sensor_distance_cm' => 15.90, 'fill_percentage' => 20, 'bin_status' => 'AVAILABLE'],
		['id' => 3, 'timestamp' => '2026-05-22 10:00:00', 'sensor_distance_cm' => 10.30, 'fill_percentage' => 49, 'bin_status' => 'AVAILABLE'],
		['id' => 4, 'timestamp' => '2026-05-22 11:00:00', 'sensor_distance_cm' => 6.20, 'fill_percentage' => 69, 'bin_status' => 'NEARLY FULL'],
		['id' => 5, 'timestamp' => '2026-05-22 12:00:00', 'sensor_distance_cm' => 2.10, 'fill_percentage' => 89, 'bin_status' => 'COLLECTION REQUIRED'],
	];
}

function testArduinoConnection(string $devicePath): bool
{
	$handle = @fopen($devicePath, 'r+');
	if ($handle === false) {
		return false;
	}

	fclose($handle);
	return true;
}

$pdo = connectDatabase($dbHost, $dbUser, $dbPassword, $dbName);
$logs = [];
$noticeMessage = null;
$isDemoMode = false;

if ($pdo === null) {
	if (bootstrapDatabase($dbHost, $dbUser, $dbPassword, $dbName)) {
		$pdo = connectDatabase($dbHost, $dbUser, $dbPassword, $dbName);
	}

	if ($pdo === null) {
		$noticeMessage = 'Design preview mode: database is unavailable, so sample analytics are shown for layout work.';
	} else {
		$noticeMessage = 'Database was missing and has now been created automatically.';
	}
} else {
	try {
		$logs = fetchLogs($pdo, 50);
	} catch (Throwable $exception) {
		$noticeMessage = 'Design preview mode: data could not be loaded, so sample analytics are shown for layout work.';
	}
}

if (empty($logs)) {
	$logs = sampleLogs();
	$isDemoMode = true;
}

$latestRow = !empty($logs) ? $logs[count($logs) - 1] : null;
$fillValues = array_map(static fn ($row) => (int) $row['fill_percentage'], $logs);
$latestFill = $latestRow !== null ? (int) $latestRow['fill_percentage'] : 0;
$statusInfo = latestStatusFromFill($latestFill);
$isArduinoConnected = testArduinoConnection($devicePath);
$maxFill = !empty($fillValues) ? max($fillValues) : 0;
$avgFill = !empty($fillValues) ? (int) round(array_sum($fillValues) / count($fillValues)) : 0;
$timestamps = array_map(static fn ($row) => (string) $row['timestamp'], $logs);
$chartLabels = json_encode($timestamps, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$chartValues = json_encode($fillValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Eco-Waste Monitor</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
	<style>
		:root {
			--bg-a: #07111c;
			--bg-b: #0f2235;
			--panel: rgba(12, 24, 39, 0.76);
			--panel-border: rgba(255, 255, 255, 0.08);
			--text: #ecf4ff;
			--muted: #9eb4cc;
		}

		* { box-sizing: border-box; }

		body {
			min-height: 100vh;
			font-family: 'Plus Jakarta Sans', sans-serif;
			color: var(--text);
			line-height: 1.5;
			background:
				radial-gradient(circle at top left, rgba(55, 197, 182, 0.18), transparent 22%),
				radial-gradient(circle at 85% 8%, rgba(56, 189, 248, 0.18), transparent 24%),
				linear-gradient(160deg, var(--bg-a), var(--bg-b));
		}

		body::before {
			content: '';
			position: fixed;
			inset: 0;
			pointer-events: none;
			background-image: radial-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px);
			background-size: 26px 26px;
			opacity: 0.32;
		}

		.container-fluid {
			position: relative;
			z-index: 1;
			max-width: 1560px;
		}

		.hero {
			position: relative;
			overflow: hidden;
			background: linear-gradient(135deg, rgba(7, 17, 28, 0.96), rgba(8, 75, 105, 0.18));
			border: 1px solid var(--panel-border);
			box-shadow: 0 18px 60px rgba(0, 0, 0, 0.34);
		}

		.hero::after {
			content: '';
			position: absolute;
			inset: auto -12% -40% auto;
			width: 340px;
			height: 340px;
			background: radial-gradient(circle, rgba(55, 197, 182, 0.24), transparent 68%);
			filter: blur(12px);
		}

		.panel {
			background: var(--panel);
			border: 1px solid var(--panel-border);
			box-shadow: 0 10px 40px rgba(0, 0, 0, 0.24);
			backdrop-filter: blur(12px);
			border-radius: 1.35rem;
		}

		.metric-card {
			position: relative;
			overflow: hidden;
			border-radius: 1.35rem;
			padding: 1.35rem;
			min-height: 148px;
		}

		.metric-card::before {
			content: '';
			position: absolute;
			inset: 0 auto auto 0;
			width: 4px;
			height: 100%;
			background: linear-gradient(180deg, #37c5b6, #38bdf8);
		}

		.metric-label {
			color: var(--muted);
			font-size: 0.82rem;
			letter-spacing: 0.12em;
			text-transform: uppercase;
		}

		.metric-value {
			margin-top: 0.55rem;
			font-size: 2.4rem;
			font-weight: 800;
			line-height: 1.1;
		}

		.metric-meta {
			margin-top: 0.45rem;
			color: #c3d3e6;
			font-size: 0.92rem;
		}

		.section-title {
			font-size: 1.1rem;
			font-weight: 800;
			letter-spacing: -0.02em;
		}

		.section-subtitle {
			color: var(--muted);
			font-size: 0.95rem;
		}

		.status-pill {
			display: inline-flex;
			align-items: center;
			gap: 0.5rem;
			padding: 0.68rem 0.95rem;
			border-radius: 999px;
			font-weight: 700;
			box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
		}

		.status-strip {
			display: flex;
			flex-wrap: wrap;
			gap: 0.6rem;
		}

		.soft-chip {
			display: inline-flex;
			align-items: center;
			gap: 0.45rem;
			padding: 0.52rem 0.8rem;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.06);
			border: 1px solid rgba(255, 255, 255, 0.08);
			color: #d9e5f2;
			font-size: 0.88rem;
		}

		.hero-kicker {
			color: #b8d3e8;
			text-transform: uppercase;
			letter-spacing: 0.18em;
			font-size: 0.8rem;
			font-weight: 700;
		}

		.hero-title {
			margin-top: 0.45rem;
			font-size: clamp(2rem, 4vw, 3.35rem);
			font-weight: 800;
			letter-spacing: -0.04em;
			line-height: 1.05;
		}

		.hero-copy {
			margin-top: 0.85rem;
			max-width: 62ch;
			color: #c7d8e7;
			font-size: 1.02rem;
		}

		.surface-divider {
			height: 1px;
			background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.12), transparent);
		}

		table thead th { white-space: nowrap; }
		table tbody td { vertical-align: middle; }

		.chart-wrap { height: 390px; }

		.table-modern {
			overflow: hidden;
			border-radius: 1rem;
		}

		.table-modern thead {
			background: rgba(255, 255, 255, 0.06);
			text-transform: uppercase;
			font-size: 0.8rem;
			letter-spacing: 0.08em;
		}

		.table-modern tbody tr:hover { background: rgba(55, 197, 182, 0.06); }

		.empty-state {
			padding: 2rem;
			text-align: center;
		}

		.empty-icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 72px;
			height: 72px;
			border-radius: 22px;
			background: rgba(55, 197, 182, 0.12);
			border: 1px solid rgba(55, 197, 182, 0.2);
		}
	</style>
</head>
<body>
	<div class="container-fluid py-4 px-3 px-lg-4">
		<div class="hero rounded-5 p-4 p-lg-5 mb-4">
			<div class="row align-items-center g-4 position-relative">
				<div class="col-lg-8">
					<div class="hero-kicker">Urban sanitation control room</div>
					<div class="hero-title">IoT Smart Waste Bin Level Monitoring Station</div>
					<div class="hero-copy">A clean, easy-to-read dashboard for tracking fill levels, spotting collection alerts, and checking the latest waste-bin trend in one place.</div>
					<div class="status-strip mt-4">
						<span class="soft-chip"><i class="fa-solid fa-wave-square"></i> Live fill trend</span>
						<span class="soft-chip"><i class="fa-solid fa-database"></i> XAMPP MySQL logs</span>
						<span class="soft-chip"><i class="fa-solid fa-rotate"></i> Auto refresh every <?= e((int) ($refreshIntervalMs / 1000)) ?>s</span>
					</div>
				</div>
				<div class="col-lg-4 text-lg-end">
					<div class="d-flex flex-column gap-3 align-items-lg-end">
						<?php if ($isArduinoConnected): ?>
							<span class="status-pill bg-success text-white"><i class="fa-solid fa-circle-check"></i> Connected on <?= e($arduinoPort) ?></span>
						<?php else: ?>
							<span class="status-pill bg-danger text-white"><i class="fa-solid fa-triangle-exclamation"></i> Connection Error on <?= e($arduinoPort) ?></span>
						<?php endif; ?>
						<span class="status-pill bg-dark text-white border border-light border-opacity-10"><i class="fa-solid fa-display"></i> <?= $isDemoMode ? 'Design Preview' : 'Live Dashboard' ?></span>
					</div>
				</div>
			</div>
		</div>

		<?php if ($noticeMessage !== null): ?>
			<div class="alert alert-info panel border-0 mb-4 d-flex align-items-center gap-2"><i class="fa-solid fa-circle-info"></i><span><?= e($noticeMessage) ?></span></div>
		<?php endif; ?>

		<?php if ($isDemoMode): ?>
			<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
				<span class="status-pill bg-info text-dark"><i class="fa-solid fa-palette"></i> Layout and Design Preview</span>
				<span class="soft-chip"><i class="fa-solid fa-lightbulb"></i> Safe fallback data is showing while MySQL is offline</span>
			</div>
		<?php endif; ?>

		<?php if ($latestRow !== null): ?>
			<div class="panel p-3 px-lg-4 py-lg-3 mb-4 d-flex align-items-center justify-content-between gap-3 flex-wrap border-start border-4 border-<?= e($statusInfo[1]) ?>">
				<div>
					<div class="section-title mb-1">Current Bin Status</div>
					<div class="section-subtitle"><?= e($statusInfo[2]) ?></div>
				</div>
				<span class="status-pill bg-<?= e($statusInfo[1]) ?> text-white"><?= e($statusInfo[0]) ?></span>
			</div>

			<div class="row g-3 mb-4">
				<div class="col-md-4">
					<div class="panel metric-card h-100">
						<div class="metric-label"><i class="fa-solid fa-bolt me-1"></i>Current Fill Level</div>
						<div class="metric-value"><?= e($latestFill) ?>%</div>
						<div class="metric-meta">Latest reading from the waste bin sensor</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="panel metric-card h-100">
						<div class="metric-label"><i class="fa-solid fa-chart-line me-1"></i>Maximum Fill Recorded</div>
						<div class="metric-value"><?= e($maxFill) ?>%</div>
						<div class="metric-meta">Highest recorded point in the history</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="panel metric-card h-100">
						<div class="metric-label"><i class="fa-solid fa-calculator me-1"></i>Average Bin Fullness</div>
						<div class="metric-value"><?= e($avgFill) ?>%</div>
						<div class="metric-meta">Mean fullness across all loaded logs</div>
					</div>
				</div>
			</div>

			<div class="row g-4">
				<div class="col-12">
					<div class="panel rounded-4 p-3 p-lg-4">
						<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
							<div>
								<div class="section-title">Trash Accumulation Over Time</div>
								<div class="section-subtitle">A chronological line chart that makes rising waste levels easy to spot</div>
							</div>
							<span class="soft-chip"><i class="fa-solid fa-arrow-trend-up"></i> Scaled 0 to 100%</span>
						</div>
						<div class="surface-divider mb-3"></div>
						<div class="chart-wrap"><canvas id="trashChart"></canvas></div>
					</div>
				</div>

				<div class="col-12">
					<div class="panel rounded-4 p-3 p-lg-4">
						<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
							<div>
								<div class="section-title">Local XAMPP MySQL Storage Live Records</div>
								<div class="section-subtitle">Readable table view for checking timestamps, sensor distance, and status tags</div>
							</div>
							<span class="soft-chip"><i class="fa-solid fa-table"></i> Latest <?= count($logs) ?> rows</span>
						</div>
						<div class="surface-divider mb-3"></div>
						<div class="table-responsive">
							<table class="table table-dark table-hover align-middle mb-0 table-modern">
								<thead>
									<tr>
										<th>Log ID</th>
										<th>Timestamp</th>
										<th>Raw Sensor Distance (cm)</th>
										<th>Fill Percentage (%)</th>
										<th>System Status Tag</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach (array_reverse($logs) as $row): ?>
										<tr>
											<td><?= e($row['id']) ?></td>
											<td><?= e($row['timestamp']) ?></td>
											<td><?= e(number_format((float) $row['sensor_distance_cm'], 2)) ?></td>
											<td><?= e((int) $row['fill_percentage']) ?>%</td>
											<td><?php $rowStatus = latestStatusFromFill((int) $row['fill_percentage']); echo e($rowStatus[0]); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		<?php else: ?>
			<div class="panel rounded-4 empty-state">
				<div class="empty-icon mb-3"><i class="fa-solid fa-trash-can fa-xl"></i></div>
				<div class="fs-4 fw-bold mb-2">No bin logs yet</div>
				<div class="section-subtitle mb-3">Once the database is ready, this dashboard will show live fill readings, trend lines, and table records here.</div>
				<div class="status-strip justify-content-center">
					<span class="soft-chip"><i class="fa-solid fa-plug-circle-bolt"></i> Check XAMPP MySQL</span>
					<span class="soft-chip"><i class="fa-solid fa-arrow-rotate-right"></i> Auto refresh enabled</span>
					<span class="soft-chip"><i class="fa-solid fa-chart-simple"></i> Analytics ready</span>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<script>
		const labels = <?= $chartLabels ?: '[]' ?>;
		const values = <?= $chartValues ?: '[]' ?>;

		const context = document.getElementById('trashChart');
		if (context) {
			new Chart(context, {
				type: 'line',
				data: {
					labels,
					datasets: [{
						label: 'Fill Percentage',
						data: values,
						borderColor: '#37c5b6',
						backgroundColor: 'rgba(55, 197, 182, 0.14)',
						tension: 0.35,
						fill: true,
						pointRadius: 4,
						pointHoverRadius: 7,
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					scales: {
						y: {
							min: 0,
							max: 100,
							ticks: { color: '#9eb4cc' },
							grid: { color: 'rgba(158, 180, 204, 0.14)' }
						},
						x: {
							ticks: {
								color: '#9eb4cc',
								maxRotation: 35,
								minRotation: 35
							},
							grid: { color: 'rgba(158, 180, 204, 0.08)' }
						}
					},
					plugins: {
						legend: {
							labels: { color: '#ecf4ff' }
						}
					}
				}
			});
		}

		window.setTimeout(() => {
			window.location.reload();
		}, <?= (int) $refreshIntervalMs ?>);
	</script>
</body>
</html>