<?php
/**
 * WaMark - Admin Profile
 */
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/app.php';

Auth::requireAuth();
Middleware::run();

$pageTitle = 'My Profile';
$user = Auth::user();

if (request_method() === 'POST') {
    verify_csrf();
    $action = $_POST['form_action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $data = [
            'name' => trim($_POST['name']),
            'phone' => trim($_POST['phone'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'timezone' => $_POST['timezone'] ?? 'UTC',
        ];
        
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $result = upload_file($_FILES['avatar'], 'avatars', ALLOWED_IMAGE_TYPES);
            if (isset($result['success'])) {
                $data['avatar'] = $result['url'];
            }
        }
        
        $db->update('users', $data, 'id = ?', [Auth::id()]);
        flash('success', 'Profile updated successfully.');
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (!verify_password($current, $user['password'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'Passwords do not match.');
        } else {
            $db->update('users', ['password' => hash_password($new)], 'id = ?', [Auth::id()]);
            flash('success', 'Password changed successfully.');
        }
    }
    redirect(ADMIN_URL . '/profile.php');
}

include ASSETS_PATH . 'templates/header.php';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold">Profile Information</h6></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input type="text" class="form-control" name="name" value="<?= sanitize($user['name']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= sanitize($user['email']) ?>" disabled></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone" value="<?= sanitize($user['phone'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Company</label><input type="text" class="form-control" name="company_name" value="<?= sanitize($user['company_name'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Timezone</label><select class="form-select" name="timezone"><option value="UTC">UTC</option><option value="America/New_York" <?= ($user['timezone']??'')==='America/New_York'?'selected':'' ?>>Eastern Time</option><option value="Asia/Kolkata" <?= ($user['timezone']??'')==='Asia/Kolkata'?'selected':'' ?>>India (IST)</option><option value="Europe/London" <?= ($user['timezone']??'')==='Europe/London'?'selected':'' ?>>London (GMT)</option></select></div>
                        <div class="col-md-6"><label class="form-label">Avatar</label><input type="file" class="form-control" name="avatar" accept="image/*"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary">Update Profile</button></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent"><h6 class="mb-0 fw-bold">Change Password</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="change_password">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                        <div class="col-md-6"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" minlength="8" required></div>
                        <div class="col-md-6"><label class="form-label">Confirm Password</label><input type="password" class="form-control" name="confirm_password" required></div>
                        <div class="col-12"><button type="submit" class="btn btn-warning">Change Password</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="avatar-lg mx-auto mb-3"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                <h5><?= sanitize($user['name']) ?></h5>
                <p class="text-muted"><?= sanitize($user['email']) ?></p>
                <span class="badge bg-primary"><?= ucfirst(str_replace('_',' ', $user['role'])) ?></span>
                <hr>
                <div class="text-start small">
                    <p><strong>Joined:</strong> <?= format_date($user['created_at']) ?></p>
                    <p><strong>Last Login:</strong> <?= $user['last_login'] ? format_datetime($user['last_login']) : 'Never' ?></p>
                    <p><strong>Last IP:</strong> <?= $user['last_ip'] ?? 'N/A' ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include ASSETS_PATH . 'templates/footer.php'; ?>
