<?php
class ActivityProgress {
    private $progress;
    
    public function __construct($initialProgress = 0) {
        $this->setProgress($initialProgress);
    }
    
    public function setProgress($progress) {
        $this->progress = ($progress >= 0 && $progress <= 100) ? $progress : 0;
        return $this->progress === $progress;
    }
    
    public function getProgress() { return $this->progress; }
    
    public function getStatus() {
        return match(true) {
            $this->progress == 0 => 'Belum Mulai',
            $this->progress == 100 => 'Selesai',
            $this->progress > 0 && $this->progress < 100 => 'Sedang Berjalan',
            default => 'Tidak Valid'
        };
    }
    
    public function getColor() {
        return match(true) {
            $this->progress == 0 => '#ff4444',
            $this->progress < 50 => '#ffaa00',
            $this->progress < 100 => '#44ff44',
            $this->progress == 100 => '#00aa00',
            default => '#888888'
        };
    }
}

$koneksi = new mysqli("localhost", "root", "", "tracker2");
if ($koneksi->connect_errno) die("Database connection failed");

define('DAYS', ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"]);
$activity_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: die("Invalid Activity ID");
$activity = $koneksi->query("SELECT * FROM activities WHERE id=$activity_id")->fetch_assoc() ?: die("Activity not found");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['ok' => false];
    
    switch($action) {
        case 'init_weeks':
            $n = max(1, intval($_POST['weeks'] ?? 4));
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            
            for ($w = 1; $w <= $n; $w++) {
                $week_start = date('Y-m-d', strtotime("$start_date + " . (($w-1)*7) . " days"));
                foreach (DAYS as $index => $day) {
                    $day_date = date('Y-m-d', strtotime("$week_start + $index days"));
                    $koneksi->query("INSERT IGNORE INTO weekly (activity_id, week_number, day_name, day_date) VALUES ($activity_id, $w, '{$koneksi->real_escape_string($day)}', '$day_date')");
                }
            }
            $response['ok'] = true;
            break;
            
        case 'add_week':
            $res = $koneksi->query("SELECT MAX(week_number) AS mx, MAX(day_date) AS last_date FROM weekly WHERE activity_id=$activity_id")->fetch_assoc();
            $newWeek = ($res['mx'] ?? 0) + 1;
            $week_start = date('Y-m-d', strtotime(($res['last_date'] ?? date('Y-m-d')) . " + 1 days"));
            
            foreach (DAYS as $index => $day) {
                $day_date = date('Y-m-d', strtotime("$week_start + $index days"));
                $koneksi->query("INSERT INTO weekly (activity_id, week_number, day_name, day_date) VALUES ($activity_id, $newWeek, '{$koneksi->real_escape_string($day)}', '$day_date')");
            }
            $response = ['ok' => true, 'week' => $newWeek];
            break;
            
        case 'toggle':
            $week_number = intval($_POST['week_number']);
            $day_name = $koneksi->real_escape_string($_POST['day_name']);
            $is_done = intval($_POST['is_done']) ? 1 : 0;
            
            $koneksi->query("UPDATE weekly SET is_done=$is_done WHERE activity_id=$activity_id AND week_number=$week_number AND day_name='$day_name'");
            
        
            $tot = $koneksi->query("SELECT COUNT(*) AS tot FROM weekly WHERE activity_id=$activity_id")->fetch_assoc()['tot'];
            $done = $koneksi->query("SELECT COUNT(*) AS donec FROM weekly WHERE activity_id=$activity_id AND is_done=1")->fetch_assoc()['donec'];
            $percent = $tot > 0 ? round(($done / $tot) * 100) : 0;
            
            $koneksi->query("UPDATE activities SET progress=$percent WHERE id=$activity_id");
            $response = ['ok' => true, 'percent' => $percent];
            break;
    }
    
    echo json_encode($response);
    exit;
}

$weeks = [];
$weeks_rs = $koneksi->query("SELECT DISTINCT week_number FROM weekly WHERE activity_id=$activity_id ORDER BY week_number ASC");
while ($r = $weeks_rs->fetch_assoc()) $weeks[] = intval($r['week_number']);
$hasWeeks = !empty($weeks);

$weeks_data = [];
if ($hasWeeks) {
    foreach ($weeks as $w) {
        $q = $koneksi->query("SELECT * FROM weekly WHERE activity_id=$activity_id AND week_number=$w ORDER BY FIELD(day_name,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu')");
        $weeks_data[$w] = $q->fetch_all(MYSQLI_ASSOC);
    }
}

function formatTanggal($date) {
    if (empty($date) || $date == '0000-00-00') return '-';
    $timestamp = strtotime($date);
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
    return $hari[date('w', $timestamp)] . ', ' . date('d', $timestamp) . ' ' . $bulan[date('n', $timestamp)-1] . ' ' . date('Y', $timestamp);
}

$activityProgress = new ActivityProgress(intval($activity['progress']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="utf-8" name="viewport" content="width=device-width,initial-scale=1">
    <title>Detail — <?= htmlspecialchars($activity['name']) ?></title>
</head>
<body>
<div class="navbar-custom">
    <h2>📘 <?= htmlspecialchars($activity['name']) ?></h2>
    <div><a href="index.php" class="btn home-btn">← Back</a></div>
</div>

<div style="max-width: 1100px; margin: 0 auto;">
    <div class="glass-card">
        <div class="activity-header">
            <div>
                <h3><?= htmlspecialchars($activity['name']) ?></h3>
                <div class="small-muted">Status: <?= $activityProgress->getStatus() ?></div>
            </div>
            <div class="progress-display">
                <div style="font-weight: 700; font-size: 24px; color: <?= $activityProgress->getColor() ?>;">
                    <?= $activityProgress->getProgress() ?>%
                </div>
                <div style="height: 10px; width: 200px; margin-top: 8px;">
                    <div class="progress-container" style="height: 10px;">
                        <div class="progress-fill" id="global-progress" 
                             style="width: <?= $activityProgress->getProgress() ?>%; 
                                    background: <?= $activityProgress->getColor() ?>;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$hasWeeks): ?>
    <div class="glass-card">
        <h3>📅 Setup</h3>
        <p class="small-muted">Select start date and number of weeks.</p>
        <div class="setup-form">
            <div class="form-group"><label>Start Date</label><input type="date" id="start-date" class="input-line" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Weeks</label><input type="number" id="initial-weeks" min="1" max="52" value="4" class="input-line"></div>
            <button id="init-weeks-btn" class="btn btn-primary">📅 Create</button>
        </div>
        <div class="preview-container">
            <div class="small-muted">Preview:</div>
            <div id="schedule-preview"></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($hasWeeks): ?>
    <div class="weeks-header">
        <div>
            <h3>📆 Weeks</h3>
            <div class="small-muted">Mark completed days</div>
        </div>
        <div class="top-actions"><button id="add-week-btn" class="btn btn-primary">➕ Add Week</button></div>
    </div>

    <div class="grid-week">
        <?php foreach ($weeks_data as $wnum => $rows): 
            $doneCount = count(array_filter($rows, fn($r) => intval($r['is_done']) === 1));
            $weekPercent = round(($doneCount / count($rows)) * 100);
            $firstDate = $rows[0]['day_date'] ?? null;
            $lastDate = $rows[6]['day_date'] ?? null;
        ?>
        <div class="week-card" data-week="<?= $wnum ?>">
            <div class="week-header">
                <div>
                    <strong>Week <?= $wnum ?></strong>
                    <?php if ($firstDate && $lastDate): ?>
                    <div class="small-muted"><?= date('d M', strtotime($firstDate)) ?> - <?= date('d M Y', strtotime($lastDate)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="week-percent"><?= $weekPercent ?>%</div>
            </div>
            
            <?php foreach ($rows as $r): 
                $isDone = intval($r['is_done']) === 1;
                $dayDate = $r['day_date'] ?? null;
            ?>
            <div class="day-card <?= $isDone ? 'done' : '' ?>" data-week="<?= $wnum ?>" data-day="<?= htmlspecialchars($r['day_name']) ?>">
                <div class="day-info">
                    <div class="day-name"><?= htmlspecialchars($r['day_name']) ?></div>
                    <?php if ($dayDate && $dayDate != '0000-00-00'): ?>
                    <div class="day-date"><?= formatTanggal($dayDate) ?></div>
                    <?php endif; ?>
                </div>
                <label class="checkbox-label">
                    <input class="day-checkbox" type="checkbox" <?= $isDone ? 'checked' : '' ?>>
                    <span><?= $isDone ? '✓' : '' ?></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
const activityId = <?= $activity_id ?>;

const updateSchedulePreview = () => {
    const startDate = document.getElementById('start-date')?.value;
    const weeks = parseInt(document.getElementById('initial-weeks')?.value || 4);
    const preview = document.getElementById('schedule-preview');
    
    if (!startDate || !preview) return;
    
    let html = '';
    for (let w = 1; w <= weeks; w++) {
        const weekStart = new Date(startDate);
        weekStart.setDate(weekStart.getDate() + ((w-1)*7));
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekEnd.getDate() + 6);
        html += `<div><strong>Week ${w}:</strong> ${weekStart.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })} - ${weekEnd.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</div>`;
    }
    preview.innerHTML = html;
};

const updateGlobalProgress = (percent) => {
    const el = document.getElementById('global-progress');
    const numeric = document.querySelector('.progress-display > div');
    
    if (el) el.style.width = percent + '%';
    if (numeric) {
        numeric.textContent = percent + '%';
        numeric.style.color = percent == 0 ? '#ff4444' : percent < 50 ? '#ffaa00' : percent < 100 ? '#44ff44' : '#00aa00';
        if (el) el.style.background = numeric.style.color;
    }
};

const updateWeekProgress = (weekCard) => {
    const checkboxes = weekCard.querySelectorAll('.day-checkbox');
    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    const weekPercent = Math.round((checkedCount / checkboxes.length) * 100);
    const weekPercentElement = weekCard.querySelector('.week-percent');
    if (weekPercentElement) weekPercentElement.textContent = weekPercent + '%';
};

const callAPI = async (action, data = {}) => {
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action, ...data})
        });
        return await response.json();
    } catch (error) {
        console.error('Error:', error);
        return {ok: false};
    }
};

document.getElementById('start-date')?.addEventListener('change', updateSchedulePreview);
document.getElementById('initial-weeks')?.addEventListener('input', updateSchedulePreview);
updateSchedulePreview();

document.getElementById('init-weeks-btn')?.addEventListener('click', async () => {
    const startDate = document.getElementById('start-date')?.value;
    const weeks = parseInt(document.getElementById('initial-weeks')?.value || 4);
    
    if (!startDate) return alert('Please select a start date');
    if (weeks < 1) return alert('Weeks must be at least 1');
    
    const result = await callAPI('init_weeks', {start_date: startDate, weeks});
    if (result.ok) location.reload();
    else alert('Failed to create weeks');
});

document.getElementById('add-week-btn')?.addEventListener('click', async () => {
    const result = await callAPI('add_week');
    if (result.ok) location.reload();
    else alert('Failed to add week');
});

document.addEventListener('click', async (e) => {
    if (!e.target.classList.contains('day-checkbox')) return;
    
    const dayCard = e.target.closest('.day-card');
    if (!dayCard) return;
    
    const weekNumber = dayCard.dataset.week;
    const dayName = dayCard.dataset.day;
    const isChecked = e.target.checked;
    
    const result = await callAPI('toggle', {
        week_number: weekNumber,
        day_name: dayName,
        is_done: isChecked ? '1' : '0'
    });
    
    if (result.ok) {
        dayCard.classList.toggle('done', isChecked);
        e.target.nextElementSibling.textContent = isChecked ? '✓' : '';
        updateGlobalProgress(result.percent);
        updateWeekProgress(dayCard.closest('.week-card'));
    } else {
        e.target.checked = !isChecked;
        alert('Failed to update status');
    }
});
</script>
</body>

</html>
