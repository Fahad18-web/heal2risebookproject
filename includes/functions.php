<?php
/**
 * Heal2Rise Book - Common Functions
 * Utility functions used throughout the application
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Generate URL with base path
 */
function url($path = '') {
    return BASE_URL . $path;
}

/**
 * Redirect to a URL
 */
function redirect($path) {
    header('Location: ' . url($path));
    exit;
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]{10,20}$/', $phone);
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate unique case number
 */
function generateCaseNumber() {
    $prefix = 'H2R';
    $year = date('Y');
    $random = strtoupper(substr(uniqid(), -5));
    return $prefix . $year . $random;
}

/**
 * Generate unique donation number
 */
function generateDonationNumber() {
    $prefix = 'DON';
    $year = date('Y');
    $random = strtoupper(substr(uniqid(), -6));
    return $prefix . $year . $random;
}

/**
 * Format date
 */
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Get time ago string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return formatDate($datetime);
}

/**
 * Upload file with validation
 */
function uploadFile($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'], $maxSize = 5242880, $uploadDir = 'uploads/') {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed'];
    }
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large (max 5MB)'];
    }
    
    $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
    $uploadPath = __DIR__ . '/../' . $uploadDir . $newFileName;
    
    if (!is_dir(dirname($uploadPath))) {
        mkdir(dirname($uploadPath), 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $newFileName];
    }
    
    return ['success' => false, 'error' => 'Failed to move file'];
}

/**
 * Send notification
 */
function sendNotification($recipientType, $recipientId, $title, $message, $type = 'info') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_type, user_id, title, message, type) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$recipientType, $recipientId, $title, $message, $type]);
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($userType, $userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_type = ? AND user_id = ? AND is_read = 0");
    $stmt->execute([$userType, $userId]);
    return $stmt->fetchColumn();
}

/**
 * Get notifications
 */
function getNotifications($userType, $userId, $limit = 10) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_type = ? AND user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userType, $userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Auto-match user with suitable NGO
 */
function findSuitableNGO($issueCategory, $userCity = null) {
    $db = getDB();
    
    // Find verified NGOs with matching specialization and available capacity
    $query = "SELECT * FROM ngos 
              WHERE is_verified = 1 
              AND status = 'active' 
              AND FIND_IN_SET(?, specialization) > 0
              AND current_cases < capacity";
    
    $params = [$issueCategory];
    
    // Prefer NGOs in the same city
    if ($userCity) {
        $query .= " ORDER BY CASE WHEN city = ? THEN 0 ELSE 1 END, current_cases ASC";
        $params[] = $userCity;
    } else {
        $query .= " ORDER BY current_cases ASC";
    }
    
    $query .= " LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetch();
}

/**
 * Find available team member from NGO
 */
function findAvailableTeamMember($ngoId, $issueCategory = null) {
    $db = getDB();
    
    $query = "SELECT * FROM team_members 
              WHERE ngo_id = ? 
              AND is_available = 1 
              AND status = 'active' 
              AND cases_assigned < max_cases";
    
    $params = [$ngoId];
    
    // Prefer counselors/psychiatrists for mental health issues
    if (in_array($issueCategory, ['depression', 'hopelessness'])) {
        $query .= " ORDER BY CASE WHEN role IN ('psychiatrist', 'counselor') THEN 0 ELSE 1 END, cases_assigned ASC";
    } else {
        $query .= " ORDER BY CASE WHEN role = 'social_worker' THEN 0 ELSE 1 END, cases_assigned ASC";
    }
    
    $query .= " LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return $stmt->fetch();
}

/**
 * Create a new case and auto-assign
 */
function createAndAssignCase($userId, $issueCategory, $description, $severity = 'medium') {
    $db = getDB();
    
    // Get user details
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'error' => 'User not found'];
    }
    
    // Find suitable NGO
    $ngo = findSuitableNGO($issueCategory, $user['city']);
    
    if (!$ngo) {
        return ['success' => false, 'error' => 'No suitable NGO available at this time'];
    }
    
    // Find available team member
    $teamMember = findAvailableTeamMember($ngo['id'], $issueCategory);
    
    // Create case
    $caseNumber = generateCaseNumber();
    
    $stmt = $db->prepare("INSERT INTO cases (case_number, user_id, ngo_id, team_member_id, issue_category, severity_level, description, status, assignment_date) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'assigned', NOW())");
    
    $result = $stmt->execute([
        $caseNumber,
        $userId,
        $ngo['id'],
        $teamMember ? $teamMember['id'] : null,
        $issueCategory,
        $severity,
        $description
    ]);
    
    if ($result) {
        $caseId = $db->lastInsertId();
        
        // Update NGO case count
        $db->prepare("UPDATE ngos SET current_cases = current_cases + 1 WHERE id = ?")->execute([$ngo['id']]);
        
        // Update team member case count if assigned
        if ($teamMember) {
            $db->prepare("UPDATE team_members SET cases_assigned = cases_assigned + 1 WHERE id = ?")->execute([$teamMember['id']]);
        }
        
        // Send notifications
        sendNotification('user', $userId, 'Case Created', "Your case #{$caseNumber} has been created and assigned to {$ngo['organization_name']}.", 'success');
        sendNotification('ngo', $ngo['id'], 'New Case Assigned', "A new case #{$caseNumber} has been assigned to your organization.", 'info');
        
        return [
            'success' => true,
            'case_id' => $caseId,
            'case_number' => $caseNumber,
            'ngo' => $ngo,
            'team_member' => $teamMember
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to create case'];
}

/**
 * Create a donation and notify involved parties
 */
function createDonation(array $data) {
    $db = getDB();

    $ngoId = intval($data['ngo_id'] ?? 0);
    $caseId = !empty($data['case_id']) ? intval($data['case_id']) : null;
    $donorUserId = !empty($data['donor_user_id']) ? intval($data['donor_user_id']) : null;
    $donorName = trim($data['donor_name'] ?? '');
    $donorEmail = trim($data['donor_email'] ?? '');
    $donorPhone = trim($data['donor_phone'] ?? '');
    $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
    $purpose = trim($data['purpose'] ?? '');
    $paymentMethod = trim($data['payment_method'] ?? 'other');
    $transactionReference = trim($data['transaction_reference'] ?? '');
    $notes = trim($data['notes'] ?? '');

    if ($ngoId <= 0) {
        return ['success' => false, 'error' => 'A valid NGO is required.'];
    }
    if ($donorName === '') {
        return ['success' => false, 'error' => 'Donor name is required.'];
    }
    if (!validateEmail($donorEmail)) {
        return ['success' => false, 'error' => 'A valid donor email is required.'];
    }
    if ($donorPhone !== '' && !validatePhone($donorPhone)) {
        return ['success' => false, 'error' => 'Please enter a valid donor phone number.'];
    }
    if ($amount <= 0) {
        return ['success' => false, 'error' => 'Donation amount must be greater than zero.'];
    }

    $allowedMethods = ['card', 'bank_transfer', 'mobile_wallet', 'cash', 'other'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        return ['success' => false, 'error' => 'Invalid payment method selected.'];
    }

    $stmt = $db->prepare("SELECT id, organization_name, is_verified, status FROM ngos WHERE id = ?");
    $stmt->execute([$ngoId]);
    $ngo = $stmt->fetch();
    if (!$ngo || $ngo['status'] !== 'active' || intval($ngo['is_verified']) !== 1) {
        return ['success' => false, 'error' => 'Selected NGO is currently not eligible to receive donations.'];
    }

    if ($caseId) {
        $stmt = $db->prepare("SELECT id, ngo_id, case_number, user_id FROM cases WHERE id = ?");
        $stmt->execute([$caseId]);
        $case = $stmt->fetch();
        if (!$case || intval($case['ngo_id']) !== $ngoId) {
            return ['success' => false, 'error' => 'Selected case does not belong to the selected NGO.'];
        }
    }

    $status = in_array($paymentMethod, ['card', 'cash'], true) ? 'completed' : 'pending';
    $donationNumber = generateDonationNumber();

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            "INSERT INTO donations (
                donation_number, ngo_id, case_id, donor_user_id, donor_name, donor_email, donor_phone,
                amount, purpose, payment_method, transaction_reference, payment_status, notes, paid_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $donationNumber,
            $ngoId,
            $caseId,
            $donorUserId,
            $donorName,
            $donorEmail,
            $donorPhone !== '' ? $donorPhone : null,
            $amount,
            $purpose !== '' ? $purpose : null,
            $paymentMethod,
            $transactionReference !== '' ? $transactionReference : null,
            $status,
            $notes !== '' ? $notes : null,
            $status === 'completed' ? date('Y-m-d H:i:s') : null
        ]);

        $donationId = $db->lastInsertId();
        $formattedAmount = number_format($amount, 2);

        $ngoMessage = "Donation {$donationNumber} of PKR {$formattedAmount} was received for your organization.";
        if ($status === 'pending') {
            $ngoMessage = "Donation {$donationNumber} of PKR {$formattedAmount} is awaiting payment verification.";
        }
        sendNotification('ngo', $ngoId, 'New Donation', $ngoMessage, $status === 'completed' ? 'success' : 'warning');

        if ($donorUserId) {
            $donorMessage = $status === 'completed'
                ? "Your donation {$donationNumber} of PKR {$formattedAmount} has been recorded successfully."
                : "Your donation {$donationNumber} of PKR {$formattedAmount} is pending verification.";
            sendNotification('user', $donorUserId, 'Donation Submitted', $donorMessage, $status === 'completed' ? 'success' : 'info');
        }

        if ($status === 'pending') {
            $adminRows = $db->query("SELECT id FROM admins")->fetchAll();
            foreach ($adminRows as $admin) {
                sendNotification('admin', $admin['id'], 'Donation Verification Required', "Donation {$donationNumber} is pending verification.", 'warning');
            }
        }

        $db->commit();
        return [
            'success' => true,
            'donation_id' => $donationId,
            'donation_number' => $donationNumber,
            'payment_status' => $status
        ];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Failed to create donation. Please try again.'];
    }
}

/**
 * Update donation payment status
 */
function updateDonationPaymentStatus($donationId, $newStatus, $adminId = null, $notes = '') {
    $db = getDB();
    $allowedStatuses = ['pending', 'completed', 'failed', 'refunded'];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        return ['success' => false, 'error' => 'Invalid donation status.'];
    }

    $stmt = $db->prepare("SELECT * FROM donations WHERE id = ?");
    $stmt->execute([$donationId]);
    $donation = $stmt->fetch();
    if (!$donation) {
        return ['success' => false, 'error' => 'Donation not found.'];
    }

    $db->beginTransaction();
    try {
        $appendNotes = trim($notes);
        if ($appendNotes !== '') {
            $appendNotes = date('Y-m-d H:i') . ': ' . $appendNotes;
            $stmt = $db->prepare("UPDATE donations SET payment_status = ?, paid_at = CASE WHEN ? = 'completed' THEN NOW() ELSE paid_at END, notes = CONCAT(IFNULL(notes, ''), CASE WHEN IFNULL(notes, '') = '' THEN '' ELSE '\n' END, ?) WHERE id = ?");
            $stmt->execute([$newStatus, $newStatus, $appendNotes, $donationId]);
        } else {
            $stmt = $db->prepare("UPDATE donations SET payment_status = ?, paid_at = CASE WHEN ? = 'completed' THEN NOW() ELSE paid_at END WHERE id = ?");
            $stmt->execute([$newStatus, $newStatus, $donationId]);
        }

        $amount = number_format(floatval($donation['amount']), 2);
        $statusLabel = ucfirst($newStatus);
        sendNotification('ngo', $donation['ngo_id'], 'Donation Status Updated', "Donation {$donation['donation_number']} (PKR {$amount}) is now {$statusLabel}.", $newStatus === 'completed' ? 'success' : ($newStatus === 'failed' ? 'danger' : 'info'));

        if (!empty($donation['donor_user_id'])) {
            sendNotification('user', $donation['donor_user_id'], 'Donation Status Updated', "Your donation {$donation['donation_number']} is now {$statusLabel}.", $newStatus === 'completed' ? 'success' : ($newStatus === 'failed' ? 'danger' : 'info'));
        }

        if ($adminId) {
            sendNotification('admin', $adminId, 'Donation Updated', "Donation {$donation['donation_number']} status changed to {$statusLabel}.", 'info');
        }

        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Failed to update donation status.'];
    }
}

/**
 * Get donation summary for an NGO
 */
function getNGODonationSummary($ngoId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT
            COUNT(*) AS total_donations,
            COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) AS total_received,
            COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END), 0) AS total_pending,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_count
        FROM donations
        WHERE ngo_id = ?");
    $stmt->execute([$ngoId]);
    return $stmt->fetch();
}

/**
 * Get donations for an NGO
 */
function getNGODonations($ngoId, $limit = 50) {
    $db = getDB();
    $limit = max(1, intval($limit));
    $stmt = $db->prepare("SELECT d.*, c.case_number
        FROM donations d
        LEFT JOIN cases c ON d.case_id = c.id
        WHERE d.ngo_id = ?
        ORDER BY d.created_at DESC
        LIMIT {$limit}");
    $stmt->execute([$ngoId]);
    return $stmt->fetchAll();
}

/**
 * Get donations for admin with optional status filter
 */
function getAllDonations($status = null, $limit = 100) {
    $db = getDB();
    $limit = max(1, intval($limit));

    if ($status && in_array($status, ['pending', 'completed', 'failed', 'refunded'], true)) {
        $stmt = $db->prepare("SELECT d.*, n.organization_name, c.case_number
            FROM donations d
            JOIN ngos n ON d.ngo_id = n.id
            LEFT JOIN cases c ON d.case_id = c.id
            WHERE d.payment_status = ?
            ORDER BY d.created_at DESC
            LIMIT {$limit}");
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    $stmt = $db->query("SELECT d.*, n.organization_name, c.case_number
        FROM donations d
        JOIN ngos n ON d.ngo_id = n.id
        LEFT JOIN cases c ON d.case_id = c.id
        ORDER BY d.created_at DESC
        LIMIT {$limit}");
    return $stmt->fetchAll();
}

/**
 * Get status badge class
 */
function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'assigned' => 'warning',
        'in_progress' => 'warning',
        'counseling' => 'info',
        'rehabilitation' => 'info',
        'skill_development' => 'info',
        'follow_up' => 'warning',
        'closed' => 'success',
        'cancelled' => 'danger',
        'active' => 'success',
        'inactive' => 'secondary',
        'recovered' => 'success',
        'scheduled' => 'warning',
        'completed' => 'success',
        'no_show' => 'danger',
        'recommended' => 'info',
        'enrolled' => 'primary',
        'dropped' => 'danger',
        'failed' => 'danger',
        'refunded' => 'secondary'
    ];
    
    return $badges[$status] ?? 'secondary';
}

/**
 * Paginate results
 */
function paginate($total, $perPage = 10, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Admin assigns an NGO to a case
 */
function adminAssignNGO($caseId, $ngoId) {
    $db = getDB();
    
    // Verify NGO exists and is active
    $stmt = $db->prepare("SELECT * FROM ngos WHERE id = ? AND is_verified = 1 AND status = 'active'");
    $stmt->execute([$ngoId]);
    $ngo = $stmt->fetch();
    if (!$ngo) return ['success' => false, 'error' => 'NGO not found or not active.'];
    
    // Get case
    $stmt = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case) return ['success' => false, 'error' => 'Case not found.'];
    
    $db->beginTransaction();
    try {
        // Decrement old NGO count if reassigning
        if ($case['ngo_id']) {
            $db->prepare("UPDATE ngos SET current_cases = GREATEST(current_cases - 1, 0) WHERE id = ?")->execute([$case['ngo_id']]);
        }
        
        $stmt = $db->prepare("UPDATE cases SET ngo_id = ?, status = 'assigned', assignment_date = NOW() WHERE id = ?");
        $stmt->execute([$ngoId, $caseId]);
        
        $db->prepare("UPDATE ngos SET current_cases = current_cases + 1 WHERE id = ?")->execute([$ngoId]);
        
        // Progress entry
        $db->prepare("INSERT INTO case_progress (case_id, status, notes) VALUES (?, 'assigned', ?)")
           ->execute([$caseId, "Case assigned to NGO: {$ngo['organization_name']}"]);
        
        sendNotification('ngo', $ngoId, 'New Case Assigned', "Case #{$case['case_number']} has been assigned to your organization.", 'info');
        sendNotification('user', $case['user_id'], 'Case Updated', "Your case has been assigned to {$ngo['organization_name']}.", 'success');
        
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Failed to assign NGO.'];
    }
}

/**
 * Admin assigns a team member to a case
 */
function adminAssignTeamMember($caseId, $teamMemberId) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ? AND status = 'active'");
    $stmt->execute([$teamMemberId]);
    $member = $stmt->fetch();
    if (!$member) return ['success' => false, 'error' => 'Team member not found or inactive.'];
    
    $stmt = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case) return ['success' => false, 'error' => 'Case not found.'];
    
    $db->beginTransaction();
    try {
        // Decrement old member's assigned count
        if ($case['team_member_id']) {
            $db->prepare("UPDATE team_members SET cases_assigned = GREATEST(cases_assigned - 1, 0) WHERE id = ?")->execute([$case['team_member_id']]);
        }
        
        $stmt = $db->prepare("UPDATE cases SET team_member_id = ?, status = CASE WHEN status = 'pending' THEN 'assigned' ELSE status END WHERE id = ?");
        $stmt->execute([$teamMemberId, $caseId]);
        
        $db->prepare("UPDATE team_members SET cases_assigned = cases_assigned + 1 WHERE id = ?")->execute([$teamMemberId]);
        
        $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by) VALUES (?, 'assigned', ?, ?)")
           ->execute([$caseId, "Team member assigned: {$member['full_name']} ({$member['role']})", $teamMemberId]);
        
        sendNotification('user', $case['user_id'], 'Team Member Assigned', "A {$member['role']} ({$member['full_name']}) has been assigned to your case.", 'info');
        if ($case['ngo_id']) {
            sendNotification('ngo', $case['ngo_id'], 'Team Assignment', "{$member['full_name']} has been assigned to case #{$case['case_number']}.", 'info');
        }
        
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Failed to assign team member.'];
    }
}

/**
 * Assign a counselor to a case (recommended by team member)
 */
function assignCounselor($caseId, $counselorId, $recommendedBy = null) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM team_members WHERE id = ? AND role IN ('counselor', 'psychiatrist') AND status = 'active'");
    $stmt->execute([$counselorId]);
    $counselor = $stmt->fetch();
    if (!$counselor) return ['success' => false, 'error' => 'Counselor not found or not eligible.'];
    
    $stmt = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case) return ['success' => false, 'error' => 'Case not found.'];
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE cases SET counselor_id = ?, status = 'counseling' WHERE id = ?");
        $stmt->execute([$counselorId, $caseId]);
        
        $notes = "Counselor assigned: {$counselor['full_name']}";
        $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by) VALUES (?, 'counseling', ?, ?)")
           ->execute([$caseId, $notes, $recommendedBy ?? $counselorId]);
        
        sendNotification('user', $case['user_id'], 'Counselor Assigned', "A counselor ({$counselor['full_name']}) has been assigned. Counseling sessions will begin soon.", 'success');
        
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Failed to assign counselor.'];
    }
}

/**
 * Create a counseling session
 */
function createCounselingSession($caseId, $counselorId, $sessionDate, $sessionTime, $sessionType = 'regular', $duration = 60) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO counseling_sessions (case_id, counselor_id, session_date, session_time, duration_minutes, session_type) VALUES (?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$caseId, $counselorId, $sessionDate, $sessionTime, $duration, $sessionType]);
    
    if ($result) {
        $stmt = $db->prepare("SELECT user_id FROM cases WHERE id = ?");
        $stmt->execute([$caseId]);
        $userId = $stmt->fetchColumn();
        sendNotification('user', $userId, 'Session Scheduled', "A counseling session has been scheduled for " . formatDate($sessionDate) . ".", 'info');
        return ['success' => true, 'session_id' => $db->lastInsertId()];
    }
    return ['success' => false, 'error' => 'Failed to create session.'];
}

/**
 * Complete a counseling session with notes
 */
function completeCounselingSession($sessionId, $notes, $recommendations = '', $moodRating = null, $nextSessionDate = null) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE counseling_sessions SET status = 'completed', notes = ?, recommendations = ?, mood_rating = ?, next_session_date = ? WHERE id = ?");
    return $stmt->execute([$notes, $recommendations, $moodRating, $nextSessionDate, $sessionId]);
}

/**
 * Get counseling sessions for a case
 */
function getCounselingSessions($caseId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT cs.*, tm.full_name as counselor_name, tm.role as counselor_role
        FROM counseling_sessions cs
        JOIN team_members tm ON cs.counselor_id = tm.id
        WHERE cs.case_id = ?
        ORDER BY cs.session_date DESC, cs.session_time DESC
    ");
    $stmt->execute([$caseId]);
    return $stmt->fetchAll();
}

/**
 * Recommend a program (rehabilitation / skill development)
 */
function recommendProgram($caseId, $programType, $programName, $description, $recommendedBy, $startDate = null, $endDate = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO programs (case_id, program_type, program_name, description, recommended_by, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$caseId, $programType, $programName, $description, $recommendedBy, $startDate, $endDate]);
    
    if ($result) {
        $stmt = $db->prepare("SELECT c.user_id, c.case_number, c.ngo_id FROM cases c WHERE c.id = ?");
        $stmt->execute([$caseId]);
        $case = $stmt->fetch();
        
        $label = $programType === 'rehabilitation' ? 'Rehabilitation' : 'Skill Development';
        sendNotification('user', $case['user_id'], "{$label} Program", "A {$label} program ({$programName}) has been recommended for your case.", 'info');
        
        // Update case status 
        $newStatus = $programType === 'rehabilitation' ? 'rehabilitation' : 'skill_development';
        $db->prepare("UPDATE cases SET status = ? WHERE id = ?")->execute([$newStatus, $caseId]);
        $db->prepare("INSERT INTO case_progress (case_id, status, notes, updated_by) VALUES (?, ?, ?, ?)")
           ->execute([$caseId, $newStatus, "{$label} program recommended: {$programName}", $recommendedBy]);
        
        return ['success' => true, 'program_id' => $db->lastInsertId()];
    }
    return ['success' => false, 'error' => 'Failed to create program.'];
}

/**
 * Get programs for a case
 */
function getCasePrograms($caseId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, tm.full_name as recommended_by_name
        FROM programs p
        LEFT JOIN team_members tm ON p.recommended_by = tm.id
        WHERE p.case_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$caseId]);
    return $stmt->fetchAll();
}

/**
 * Admin closes a case after recovery
 */
function adminCloseCase($caseId, $closureRemarks, $adminId) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case) return ['success' => false, 'error' => 'Case not found.'];
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE cases SET status = 'closed', actual_end_date = CURDATE(), closure_remarks = ?, closed_by = 'admin' WHERE id = ?");
        $stmt->execute([$closureRemarks, $caseId]);
        
        // Decrement NGO case count
        if ($case['ngo_id']) {
            $db->prepare("UPDATE ngos SET current_cases = GREATEST(current_cases - 1, 0) WHERE id = ?")->execute([$case['ngo_id']]);
        }
        // Decrement team member case count
        if ($case['team_member_id']) {
            $db->prepare("UPDATE team_members SET cases_assigned = GREATEST(cases_assigned - 1, 0) WHERE id = ?")->execute([$case['team_member_id']]);
        }
        
        // Update user status to recovered
        $db->prepare("UPDATE users SET status = 'recovered' WHERE id = ?")->execute([$case['user_id']]);
        
        $db->prepare("INSERT INTO case_progress (case_id, status, notes) VALUES (?, 'closed', ?)")
           ->execute([$caseId, "Case closed by Admin. Remarks: {$closureRemarks}"]);
        
        sendNotification('user', $case['user_id'], 'Case Closed', "Your case #{$case['case_number']} has been closed. We wish you well on your journey!", 'success');
        if ($case['ngo_id']) {
            sendNotification('ngo', $case['ngo_id'], 'Case Closed', "Case #{$case['case_number']} has been closed by the administrator.", 'info');
        }
        
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Failed to close case.'];
    }
}

/**
 * Update program status
 */
function updateProgramStatus($programId, $status, $notes = '') {
    $db = getDB();
    $stmt = $db->prepare("UPDATE programs SET status = ?, progress_notes = CONCAT(IFNULL(progress_notes, ''), '\n', ?) WHERE id = ?");
    return $stmt->execute([$status, date('Y-m-d') . ': ' . $notes, $programId]);
}
?>
