<?php
/**
 * Admin: Users list (search, status / activity filters, sorting, pagination).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth_check.php';

$page_title = 'Users';

$search = trim(input_text($_GET, 'q'));
$status = input_text($_GET, 'status');
$status = in_array($status, ['active', 'blocked'], true) ? $status : '';
$activity_filters = [
    'online' => ['Online now', 'last_seen_at >= (NOW() - INTERVAL ' . USER_ONLINE_MINUTES . ' MINUTE)'],
    '24h' => ['Active today', 'last_seen_at >= (NOW() - INTERVAL 1 DAY)'],
    '7d' => ['Active this week', 'last_seen_at >= (NOW() - INTERVAL 7 DAY)'],
    'inactive' => ['Inactive 30+ days', '(last_seen_at IS NULL OR last_seen_at < (NOW() - INTERVAL 30 DAY))'],
];
$active = input_text($_GET, 'active');
$active = isset($activity_filters[$active]) ? $active : '';
$sorts = [
    'newest' => ['Newest', 'created_at DESC, id DESC'],
    'active' => ['Last active', 'last_seen_at IS NULL, last_seen_at DESC'],
    'files' => ['Most files', 'file_count DESC, id DESC'],
    'storage' => ['Most storage', 'storage_used DESC, id DESC'],
    'name' => ['Name (A-Z)', 'full_name ASC'],
];
$sort = input_text($_GET, 'sort', 'newest');
$sort = isset($sorts[$sort]) ? $sort : 'newest';

$where = [];
$params = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = '(full_name LIKE :q1 OR username LIKE :q2 OR email LIKE :q3)';
    $params += [':q1' => $like, ':q2' => $like, ':q3' => $like];
}
if ($status !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}
if ($active !== '') {
    $where[] = $activity_filters[$active][1];
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$count = $pdo->prepare('SELECT COUNT(*) FROM users' . $where_sql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / ADMIN_ROWS_PER_PAGE));
$page = min(current_page_number(), $total_pages);

$stmt = $pdo->prepare(
    'SELECT id, full_name, username, email, avatar, status, file_count, storage_used, last_seen_at, created_at
     FROM users' . $where_sql . ' ORDER BY ' . $sorts[$sort][1] . ' LIMIT :limit OFFSET :offset'
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', ADMIN_ROWS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * ADMIN_ROWS_PER_PAGE, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$flash_success = get_flash('success');
$flash_error = get_flash('error');

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
        <h1><i class="fa-solid fa-users"></i> Users</h1>
        <p class="text-muted"><?php echo number_format($total); ?> user<?php echo $total === 1 ? '' : 's'; ?><?php echo $where ? ' match your filters' : ' registered'; ?>.</p>
      </div>
    </div>

    <form class="toolbar" method="GET" action="users.php">
      <div class="toolbar-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" class="form-control" placeholder="Search name, username or email" value="<?php echo sanitize($search); ?>" maxlength="100">
      </div>
      <select name="active" class="form-control" aria-label="Activity" onchange="this.form.submit()">
        <option value="">Any activity</option>
        <?php foreach ($activity_filters as $k => [$label]): ?>
          <option value="<?php echo $k; ?>" <?php echo $active === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-control" aria-label="Status" onchange="this.form.submit()">
        <option value="">Any status</option>
        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active accounts</option>
        <option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked accounts</option>
      </select>
      <select name="sort" class="form-control" aria-label="Sort" onchange="this.form.submit()">
        <?php foreach ($sorts as $k => [$label]): ?>
          <option value="<?php echo $k; ?>" <?php echo $sort === $k ? 'selected' : ''; ?>>Sort: <?php echo $label; ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
      <?php if ($where || $sort !== 'newest'): ?><a href="users.php" class="btn btn-ghost">Reset</a><?php endif; ?>
    </form>

    <?php if ($users): ?>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Status</th>
              <th>Files</th>
              <th>Last active</th>
              <th>Joined</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <?php $uid = (int) $u['id']; ?>
              <tr>
                <td>
                  <a href="user.php?id=<?php echo $uid; ?>" class="user-cell">
                    <?php echo user_avatar_html($u, '../', 'avatar avatar-sm'); ?>
                    <span>
                      <strong><?php echo sanitize($u['full_name']); ?></strong>
                      <span class="text-muted">@<?php echo sanitize($u['username']); ?></span>
                    </span>
                  </a>
                </td>
                <td class="text-muted"><?php echo sanitize($u['email']); ?></td>
                <td>
                  <?php if ($u['status'] === 'blocked'): ?>
                    <span class="status-pill status-blocked"><i class="fa-solid fa-ban"></i> Blocked</span>
                  <?php elseif (user_is_online($u['last_seen_at'])): ?>
                    <span class="status-pill status-online"><span class="live-dot"></span> Online</span>
                  <?php else: ?>
                    <span class="status-pill"><i class="fa-solid fa-circle-check"></i> Active</span>
                  <?php endif; ?>
                </td>
                <td><?php echo number_format((int) $u['file_count']); ?> <span class="text-muted">(<?php echo format_bytes((int) $u['storage_used']); ?>)</span></td>
                <td class="text-muted"><?php echo time_ago($u['last_seen_at']); ?></td>
                <td class="text-muted"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                <td>
                  <div class="table-actions" style="justify-content: flex-end;">
                    <a href="user.php?id=<?php echo $uid; ?>" class="btn btn-outline btn-sm" title="View"><i class="fa-solid fa-eye"></i></a>
                    <form action="user_action.php" method="POST" class="inline-form">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="id" value="<?php echo $uid; ?>">
                      <input type="hidden" name="return" value="<?php echo sanitize('users.php' . query_with([])); ?>">
                      <?php if ($u['status'] === 'blocked'): ?>
                        <button type="submit" name="action" value="unblock" class="btn btn-outline btn-sm" title="Unblock" data-loading-text="">
                          <i class="fa-solid fa-unlock"></i>
                        </button>
                      <?php else: ?>
                        <button type="submit" name="action" value="block" class="btn btn-outline btn-sm" title="Block"
                          data-confirm="Block <?php echo sanitize($u['full_name']); ?>? They will be signed out and unable to log in." data-loading-text="">
                          <i class="fa-solid fa-ban"></i>
                        </button>
                      <?php endif; ?>
                      <button type="submit" name="action" value="delete_user" class="btn btn-danger btn-sm" title="Delete"
                        data-confirm="Delete <?php echo sanitize($u['full_name']); ?> and all <?php echo (int) $u['file_count']; ?> of their files? This cannot be undone." data-loading-text="">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php echo render_simple_pagination($page, $total_pages); ?>
    <?php else: ?>
      <div class="admin-card empty-state">
        <div class="empty-icon"><i class="fa-solid fa-users"></i></div>
        <h3 class="empty-title"><?php echo $where ? 'No users match your filters' : 'No registered users yet'; ?></h3>
        <p class="empty-desc">Students can create an account at <strong>account/register.php</strong>.</p>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
