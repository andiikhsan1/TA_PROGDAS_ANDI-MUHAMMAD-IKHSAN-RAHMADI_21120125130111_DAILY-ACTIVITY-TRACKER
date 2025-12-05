<?php
// BAGIAN KONFIGURASI DAN KONEKSI DATABASE
include "config.php"; // File konfigurasi untuk koneksi database
$db2 = new mysqli("localhost", "root", "", "tracker2"); // Koneksi ke database tracker2

// BAGIAN PENANGANAN FORM TAMBAH AKTIVITAS
if (isset($_POST['add'])) {
    $name = $_POST['name'];
    // Menyimpan aktivitas baru dengan progress default 0%
    $db2->query("INSERT INTO activities (name, progress) VALUES ('$name', 0)");
    header("Location: index.php"); // Redirect setelah berhasil
    exit;
}
// BAGIAN PENANGANAN HAPUS AKTIVITAS
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Menghapus aktivitas berdasarkan ID
    $db2->query("DELETE FROM activities WHERE id='$id'");
    header("Location: index.php"); // Redirect setelah berhasil
    exit;
}
// BAGIAN PENGAMBILAN DAN PERHITUNGAN DATA
$data = $db2->query("SELECT * FROM activities"); // Ambil semua data aktivitas

$totalProgress = 0;
$totalActivities = 0;
$completed = 0;
$overallPercentage = 0;

// Hitung statistik progres jika ada data
if ($data && $data->num_rows > 0) {
    $totalActivities = $data->num_rows; // Hitung total aktivitas
    $data->data_seek(0); // Reset pointer data
    
    // Loop melalui setiap aktivitas
    while ($row = $data->fetch_assoc()) {
        $totalProgress += $row['progress']; // Akumulasi progres
        if ($row['progress'] == 100) $completed++; // Hitung yang selesai
    }
    
    // Hitung persentase keseluruhan
    if ($totalActivities > 0) {
        $overallPercentage = round($totalProgress / $totalActivities, 1);
    }
    
    $data->data_seek(0); // Reset pointer untuk penggunaan berikutnya
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daily Activity Tracker</title>
    <link rel="stylesheet" href="style.css">

</head>
<body>

<div class="container">
    <!-- HEADER UTAMA -->
    <div class="header">
        <h1>📊 Daily Activity Tracker</h1>
    </div>

    <!-- KARTU STATISTIK PROGRES -->
    <div class="card">
        <h2><i class="fas fa-chart-line"></i> Overall Progress</h2>
        <div class="progress-summary">
            <div class="progress-circle">
                <svg width="100" height="100" viewBox="0 0 100 100">
                    <circle class="circle-bg" cx="50" cy="50" r="40"></circle>
                    <circle class="circle-progress" cx="50" cy="50" r="40" 
                            stroke-dasharray="251.2" 
                            stroke-dashoffset="<?= 251.2 - ($overallPercentage * 2.512) ?>"></circle>
                </svg>
                <div class="circle-text"><?= $overallPercentage ?>%</div>
            </div>
            <div class="progress-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= $totalActivities ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" style="color: #28a745;"><?= $completed ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $totalActivities > 0 ? $totalActivities - $completed : 0 ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" style="color: #6c5ce7;"><?= $overallPercentage ?>%</div>
                    <div class="stat-label">Average Progress</div>
                </div>
            </div>
        </div>
        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span style="font-weight: 500;">Overall Progress</span>
                <span style="font-weight: bold;"><?= $overallPercentage ?>%</span>
            </div>
            <div class="progress-container">
                <div class="progress-fill" style="width: <?= $overallPercentage ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- FORM TAMBAH AKTIVITAS -->
    <div class="grid">
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Add Daily Activity</h2>
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-tasks"></i> Activity Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="Enter activity name...">
                </div>
                <button type="submit" name="add" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-plus"></i> Add Daily Activity
                </button>
            </form>
        </div>
    </div>

    <!-- TABEL DAFTAR AKTIVITAS -->
    <div class="card">
        <h2><i class="fas fa-list-alt"></i> Daily Activities</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Activity Name</th>
                        <th style="width: 300px;">Progress</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($data && $data->num_rows > 0): ?>
                        <?php while ($row = $data->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="progress-container" style="flex: 1;">
                                            <div class="progress-fill" style="width: <?= $row['progress'] ?>%;"></div>
                                        </div>
                                        <span style="font-weight: bold; min-width: 40px; text-align: center;"><?= $row['progress'] ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['progress'] == 100): ?>
                                        <span class="badge badge-done">
                                            <i class="fas fa-check-circle"></i> Completed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-progress">
                                            <i class="fas fa-spinner"></i> In Progress
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 10px; justify-content: center;">
                                        <a href="detail_activity.php?id=<?= $row['id'] ?>" class="btn btn-edit">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <a href="?delete=<?= $row['id'] ?>" class="btn btn-delete" onclick="return confirm('Delete this activity?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-clipboard-list" style="font-size: 48px; margin-bottom: 10px;"></i>
                                <h3 style="margin-bottom: 10px; color: rgba(255,255,255,0.7);">No daily activities</h3>
                                <p>Add your first daily activity!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
