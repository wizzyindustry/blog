<?php
/**
 * Admin Dashboard - Analytics
 * Active users, registrations, sign-ins, uploads, charts and trends.
 *
 * Every query is an aggregate on an indexed date column (created_at, last_seen_at),
 * so the page stays fast with tens of thousands of users and files.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'Dashboard';

// Time range: 7, 30 or 90 days
$ranges = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];
$days = (int) input_text($_GET, 'range', '30');
$days = isset($ranges[$days]) ? $days : 30;

$since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
$prev_since = date('Y-m-d 00:00:00', strtotime('-' . (2 * $days - 1) . ' days'));

/** Run a single-value query with optional parameters. */
function scalar(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/** Daily counts as [Y-m-d => n] with every day of the range present (zeros filled in). */
function daily_series(PDO $pdo, string $sql, string $since, int $days): array
{
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $series[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':since' => $since]);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($series[$row['d']])) {
            $series[$row['d']] = (int) $row['n'];
        }
    }
    return $series;
}

/** "+12%" style change versus the previous period. */
function trend_badge(int $now, int $before): string
{
    if ($before === 0) {
        return $now > 0 ? '<span class="trend trend-up"><i class="fa-solid fa-arrow-trend-up"></i> New</span>' : '<span class="trend">&ndash;</span>';
    }
    $pct = (int) round(($now - $before) / $before * 100);
    if ($pct === 0) {
        return '<span class="trend"><i class="fa-solid fa-minus"></i> 0%</span>';
    }
    $up = $pct > 0;
    return '<span class="trend ' . ($up ? 'trend-up' : 'trend-down') . '"><i class="fa-solid fa-arrow-trend-' . ($up ? 'up' : 'down') . '"></i> '
        . ($up ? '+' : '') . $pct . '%</span>';
}

// ---------- Headline numbers ----------
$online_window = 'NOW() - INTERVAL ' . USER_ONLINE_MINUTES . ' MINUTE';

$total_users = (int) scalar($pdo, 'SELECT COUNT(*) FROM users');
$blocked_users = (int) scalar($pdo, "SELECT COUNT(*) FROM users WHERE status = 'blocked'");
$online_now = (int) scalar($pdo, "SELECT COUNT(*) FROM users WHERE last_seen_at >= ($online_window)");
$active_24h = (int) scalar($pdo, 'SELECT COUNT(*) FROM users WHERE last_seen_at >= (NOW() - INTERVAL 1 DAY)');
$active_7d = (int) scalar($pdo, 'SELECT COUNT(*) FROM users WHERE last_seen_at >= (NOW() - INTERVAL 7 DAY)');
$active_30d = (int) scalar($pdo, 'SELECT COUNT(*) FROM users WHERE last_seen_at >= (NOW() - INTERVAL 30 DAY)');

$new_users = (int) scalar($pdo, 'SELECT COUNT(*) FROM users WHERE created_at >= :s', [':s' => $since]);
$new_users_prev = (int) scalar($pdo, 'SELECT COUNT(*) FROM users WHERE created_at >= :p AND created_at < :s', [':p' => $prev_since, ':s' => $since]);

$uploads = (int) scalar($pdo, 'SELECT COUNT(*) FROM user_files WHERE created_at >= :s', [':s' => $since]);
$uploads_prev = (int) scalar($pdo, 'SELECT COUNT(*) FROM user_files WHERE created_at >= :p AND created_at < :s', [':p' => $prev_since, ':s' => $since]);

$logins = (int) scalar($pdo, 'SELECT COUNT(*) FROM user_logins WHERE created_at >= :s', [':s' => $since]);
$logins_prev = (int) scalar($pdo, 'SELECT COUNT(*) FROM user_logins WHERE created_at >= :p AND created_at < :s', [':p' => $prev_since, ':s' => $since]);

// Totals kept on the users row (no scan of user_files)
$file_totals = $pdo->query('SELECT COALESCE(SUM(file_count), 0) AS files, COALESCE(SUM(storage_used), 0) AS bytes FROM users')->fetch();
$total_posts = (int) scalar($pdo, 'SELECT COUNT(*) FROM posts');
$public_files = (int) scalar($pdo, "SELECT COUNT(*) FROM user_files WHERE visibility = 'public'");
$total_comments = (int) scalar($pdo, 'SELECT COUNT(*) FROM comments');
$total_categories = (int) scalar($pdo, 'SELECT COUNT(*) FROM categories');

// ---------- Trend series ----------
$signups_series = daily_series($pdo, 'SELECT DATE(created_at) AS d, COUNT(*) AS n FROM users WHERE created_at >= :since GROUP BY DATE(created_at)', $since, $days);
$active_series = daily_series($pdo, 'SELECT DATE(created_at) AS d, COUNT(DISTINCT user_id) AS n FROM user_logins WHERE created_at >= :since GROUP BY DATE(created_at)', $since, $days);
$uploads_series = daily_series($pdo, 'SELECT DATE(created_at) AS d, COUNT(*) AS n FROM user_files WHERE created_at >= :since GROUP BY DATE(created_at)', $since, $days);

// Sign-ins by hour of day (busiest times)
$hours = array_fill(0, 24, 0);
$stmt = $pdo->prepare('SELECT HOUR(created_at) AS h, COUNT(*) AS n FROM user_logins WHERE created_at >= :since GROUP BY HOUR(created_at)');
$stmt->execute([':since' => $since]);
foreach ($stmt->fetchAll() as $row) {
    $hours[(int) $row['h']] = (int) $row['n'];
}

// Files by type (all time)
$kinds = [];
foreach ($pdo->query('SELECT file_kind, COUNT(*) AS n, COALESCE(SUM(file_size), 0) AS bytes FROM user_files GROUP BY file_kind')->fetchAll() as $row) {
    $kinds[$row['file_kind']] = $row;
}
$kind_rows = [];
foreach (FILE_KINDS as $k => [$label]) {
    $kind_rows[] = ['label' => $label, 'n' => (int) ($kinds[$k]['n'] ?? 0), 'bytes' => (int) ($kinds[$k]['bytes'] ?? 0)];
}
usort($kind_rows, fn($a, $b) => $b['n'] <=> $a['n']);

// Posts per category
$cat_rows = array_map(fn($c) => ['label' => $c['name'], 'n' => (int) $c['post_count']], get_categories());
usort($cat_rows, fn($a, $b) => $b['n'] <=> $a['n']);

// ---------- Lists ----------
$recently_active = $pdo->query(
    'SELECT id, full_name, username, avatar, last_seen_at FROM users
     WHERE last_seen_at IS NOT NULL ORDER BY last_seen_at DESC LIMIT 8'
)->fetchAll();
$top_uploaders = $pdo->query(
    'SELECT id, full_name, username, avatar, file_count, storage_used FROM users
     WHERE file_count > 0 ORDER BY file_count DESC LIMIT 6'
)->fetchAll();
$newest_users = $pdo->query(
    'SELECT id, full_name, username, avatar, email, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 6'
)->fetchAll();

$chart_data = [
    'days' => array_map(fn($d) => date('M j', strtotime($d)), array_keys($signups_series)),
    'signups' => array_values($signups_series),
    'active' => array_values($active_series),
    'uploads' => array_values($uploads_series),
    'hours' => $hours,
    'kinds' => $kind_rows,
    'categories' => $cat_rows,
];

$flash_success = get_flash('success');
$flash_error = get_flash('error');

$page_scripts = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.umd.min.js" integrity="sha512-Y51n9mtKTVBh3Jbx5pZSJNDDMyY+yGe77DGtBPzRlgsf/YLCh13kSZ3JmfHGzYFCmOndraf0sQgfM654b7dJ3w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>'
    // Chart data as a JSON data block (not executable, so it is allowed by the Content-Security-Policy)
    . '<script type="application/json" id="dashboard-data">' . json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';

require_once __DIR__ . '/includes/admin_header.php';
?>

<main class="main-content">
  <div class="container">

    <?php if ($flash_success !== ''): ?>
      <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo sanitize($flash_success); ?></div>
    <?php endif; ?>
    <?php if ($flash_error !== ''): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo sanitize($flash_error); ?></div>
    <?php endif; ?>

    <div class="page-heading">
      <div>
        <h1><i class="fa-solid fa-gauge-high"></i> Dashboard</h1>
        <p class="text-muted">Users, activity and content at a glance. Updated <?php echo date('M j, Y H:i'); ?>.</p>
      </div>
      <!-- Time range filter -->
      <nav class="segmented" aria-label="Time range">
        <?php foreach ($ranges as $value => $label): ?>
          <a href="?range=<?php echo $value; ?>" class="<?php echo $days === $value ? 'active' : ''; ?>"
            <?php echo $days === $value ? 'aria-current="true"' : ''; ?>><?php echo $label; ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <!-- Active users -->
    <section class="kpi-grid">
      <a href="users.php?active=online" class="stat-card stat-card-link kpi-live">
        <div class="stat-icon"><i class="fa-solid fa-signal"></i></div>
        <div class="stat-info">
          <div class="stat-number"><?php echo number_format($online_now); ?> <span class="live-dot" aria-hidden="true"></span></div>
          <div class="stat-label">Online Now</div>
          <div class="stat-sub">Active in the last <?php echo USER_ONLINE_MINUTES; ?> minutes</div>
        </div>
      </a>
      <a href="users.php?active=24h" class="stat-card stat-card-link">
        <div class="stat-icon"><i class="fa-solid fa-user-clock"></i></div>
        <div class="stat-info">
          <div class="stat-number"><?php echo number_format($active_24h); ?></div>
          <div class="stat-label">Active Today</div>
          <div class="stat-sub"><?php echo number_format($active_7d); ?> this week &middot; <?php echo number_format($active_30d); ?> this month</div>
        </div>
      </a>
      <a href="users.php" class="stat-card stat-card-link">
        <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
        <div class="stat-info">
          <div class="stat-number"><?php echo number_format($total_users); ?></div>
          <div class="stat-label">Registered Users</div>
          <div class="stat-sub"><?php echo number_format($new_users); ?> new <?php echo trend_badge($new_users, $new_users_prev); ?></div>
        </div>
      </a>
      <a href="user_files.php" class="stat-card stat-card-link">
        <div class="stat-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <div class="stat-info">
          <div class="stat-number"><?php echo number_format((int) $file_totals['files']); ?></div>
          <div class="stat-label">User Files</div>
          <div class="stat-sub"><?php echo number_format($uploads); ?> new <?php echo trend_badge($uploads, $uploads_prev); ?></div>
        </div>
      </a>
    </section>

    <section class="kpi-grid kpi-grid-compact">
      <div class="mini-stat"><i class="fa-solid fa-right-to-bracket"></i><div><strong><?php echo number_format($logins); ?></strong> sign-ins <?php echo trend_badge($logins, $logins_prev); ?></div></div>
      <a class="mini-stat" href="user_files.php?visibility=public"><i class="fa-solid fa-earth-africa"></i><div><strong><?php echo number_format($public_files); ?></strong> shared publicly</div></a>
      <div class="mini-stat"><i class="fa-solid fa-hard-drive"></i><div><strong><?php echo format_bytes((int) $file_totals['bytes']); ?></strong> stored</div></div>
      <a class="mini-stat" href="posts.php"><i class="fa-solid fa-newspaper"></i><div><strong><?php echo number_format($total_posts); ?></strong> posts &middot; <?php echo number_format($total_comments); ?> comments</div></a>
      <a class="mini-stat" href="categories.php"><i class="fa-solid fa-folder-tree"></i><div><strong><?php echo number_format($total_categories); ?></strong> categories</div></a>
      <a class="mini-stat" href="users.php?status=blocked"><i class="fa-solid fa-user-slash"></i><div><strong><?php echo number_format($blocked_users); ?></strong> blocked</div></a>
    </section>

    <!-- Trend charts -->
    <div class="chart-grid">
      <section class="admin-card chart-card chart-card-wide">
        <div class="chart-head">
          <h2>New vs. active users</h2>
          <p class="text-muted">Registrations and unique users who signed in, per day &middot; <?php echo strtolower($ranges[$days]); ?></p>
        </div>
        <div class="chart-legend">
          <span><i class="legend-swatch" style="background:#2a78d6"></i> Active users</span>
          <span><i class="legend-swatch" style="background:#eb6834"></i> New registrations</span>
        </div>
        <div class="chart-box"><canvas id="chart-users" role="img" aria-label="Line chart of new and active users per day"></canvas></div>
      </section>

      <section class="admin-card chart-card">
        <div class="chart-head">
          <h2>Uploads per day</h2>
          <p class="text-muted"><?php echo number_format($uploads); ?> files &middot; <?php echo strtolower($ranges[$days]); ?></p>
        </div>
        <div class="chart-box"><canvas id="chart-uploads" role="img" aria-label="Bar chart of files uploaded per day"></canvas></div>
      </section>

      <section class="admin-card chart-card">
        <div class="chart-head">
          <h2>Busiest hours</h2>
          <p class="text-muted">Sign-ins by hour of day (EAT)</p>
        </div>
        <div class="chart-box"><canvas id="chart-hours" role="img" aria-label="Bar chart of sign-ins by hour of day"></canvas></div>
      </section>

      <section class="admin-card chart-card">
        <div class="chart-head">
          <h2>Files by type</h2>
          <p class="text-muted">All user uploads</p>
        </div>
        <div class="chart-box"><canvas id="chart-kinds" role="img" aria-label="Bar chart of user files by type"></canvas></div>
      </section>

      <section class="admin-card chart-card">
        <div class="chart-head">
          <h2>Posts by category</h2>
          <p class="text-muted">Published materials</p>
        </div>
        <div class="chart-box"><canvas id="chart-categories" role="img" aria-label="Bar chart of posts per category"></canvas></div>
      </section>
    </div>

    <!-- Table view of the trend data (accessible alternative to the charts) -->
    <details class="admin-card chart-table">
      <summary><i class="fa-solid fa-table"></i> View daily numbers as a table</summary>
      <div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>Date</th><th>New registrations</th><th>Active users</th><th>Uploads</th></tr></thead>
          <tbody>
            <?php foreach (array_reverse(array_keys($signups_series)) as $d): ?>
              <tr>
                <td><?php echo date('D, M j', strtotime($d)); ?></td>
                <td><?php echo $signups_series[$d]; ?></td>
                <td><?php echo $active_series[$d]; ?></td>
                <td><?php echo $uploads_series[$d]; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>

    <!-- People -->
    <div class="chart-grid">
      <section class="admin-card">
        <div class="card-header-flex">
          <h2><i class="fa-solid fa-signal"></i> Recently active</h2>
          <a href="users.php?sort=active" class="btn btn-outline btn-sm">All users</a>
        </div>
        <?php if ($recently_active): ?>
          <ul class="people-list">
            <?php foreach ($recently_active as $u): ?>
              <li>
                <?php echo user_avatar_html($u, '../', 'avatar avatar-sm'); ?>
                <div class="people-info">
                  <a href="user.php?id=<?php echo (int) $u['id']; ?>"><?php echo sanitize($u['full_name']); ?></a>
                  <span class="text-muted">@<?php echo sanitize($u['username']); ?></span>
                </div>
                <?php if (user_is_online($u['last_seen_at'])): ?>
                  <span class="status-pill status-online"><span class="live-dot"></span> Online</span>
                <?php else: ?>
                  <span class="text-muted people-meta"><?php echo time_ago($u['last_seen_at']); ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">No user activity yet.</p>
        <?php endif; ?>
      </section>

      <section class="admin-card">
        <div class="card-header-flex">
          <h2><i class="fa-solid fa-trophy"></i> Top uploaders</h2>
          <a href="users.php?sort=files" class="btn btn-outline btn-sm">See more</a>
        </div>
        <?php if ($top_uploaders): ?>
          <ul class="people-list">
            <?php foreach ($top_uploaders as $u): ?>
              <li>
                <?php echo user_avatar_html($u, '../', 'avatar avatar-sm'); ?>
                <div class="people-info">
                  <a href="user.php?id=<?php echo (int) $u['id']; ?>"><?php echo sanitize($u['full_name']); ?></a>
                  <span class="text-muted"><?php echo format_bytes((int) $u['storage_used']); ?></span>
                </div>
                <span class="people-meta"><strong><?php echo number_format((int) $u['file_count']); ?></strong> files</span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">No uploads yet.</p>
        <?php endif; ?>
      </section>

      <section class="admin-card">
        <div class="card-header-flex">
          <h2><i class="fa-solid fa-user-plus"></i> Newest members</h2>
          <a href="users.php" class="btn btn-outline btn-sm">See more</a>
        </div>
        <?php if ($newest_users): ?>
          <ul class="people-list">
            <?php foreach ($newest_users as $u): ?>
              <li>
                <?php echo user_avatar_html($u, '../', 'avatar avatar-sm'); ?>
                <div class="people-info">
                  <a href="user.php?id=<?php echo (int) $u['id']; ?>"><?php echo sanitize($u['full_name']); ?></a>
                  <span class="text-muted"><?php echo sanitize($u['email']); ?></span>
                </div>
                <span class="text-muted people-meta"><?php echo time_ago($u['created_at']); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">No registered users yet.</p>
        <?php endif; ?>
      </section>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
