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
 * Calculate time passed since a datetime
 */
function timeAgo($datetime) {
    $time_ago = strtotime($datetime);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    $minutes      = round($seconds / 60);           // value 60 is seconds
    $hours        = round($seconds / 3600);         // value 3600 is 60 minutes * 60 sec
    $days         = round($seconds / 86400);        // value 86400 is 24 hours * 60 * 60;
    $weeks        = round($seconds / 604800);       // value 604800 is 7 days * 24 hours * 60 * 60;
    $months       = round($seconds / 2629440);      // value 2629440 is ((365+365+365+365+366)/5/12) * 24 * 60 * 60
    $years        = round($seconds / 31553280);     // value 31553280 is ((365+365+365+365+366)/5) * 24 * 60 * 60
    
    if ($seconds <= 60) {
        return "Just now";
    } else if ($minutes <= 60) {
        return "$minutes min ago";
    } else if ($hours <= 24) {
        return "$hours hrs ago";
    } else if ($days <= 7) {
        return "$days days ago";
    } else if ($weeks <= 4.3) {
        return "$weeks weeks ago";
    } else if ($months <= 12) {
        return "$months months ago";
    } else {
        return "$years years ago";
    }
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
 * Request satisfaction check for 100% progress
 */
function requestSatisfactionCheck($caseId, $teamMemberId) {
    $db = getDB();
    
    // Check if request already exists
    $stmt = $db->prepare("SELECT id FROM satisfaction_requests WHERE case_id = ?");
    $stmt->execute([$caseId]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Satisfaction request already exists for this case'];
    }
    
    // Insert request
    $stmt = $db->prepare("INSERT INTO satisfaction_requests (case_id, requested_by_team_member_id) VALUES (?, ?)");
    if ($stmt->execute([$caseId, $teamMemberId])) {
        // Get case to find user and NGO
        $stmtCase = $db->prepare("SELECT user_id, ngo_id FROM cases WHERE id = ?");
        $stmtCase->execute([$caseId]);
        $case = $stmtCase->fetch();
        
        if ($case) {
            sendNotification('user', $case['user_id'], 'Case Progress at 100%', 'Your case progress is 100%. Are you satisfied with the support received?', 'info');
            sendNotification('ngo', $case['ngo_id'], 'Case Progress at 100%', "Case #{$caseId} has reached 100% progress. Please confirm satisfaction.", 'info');
        }
        return ['success' => true];
    }
    
    return ['success' => false, 'error' => 'Failed to create satisfaction request'];
}

/**
 * Check if both user and NGO are satisfied
 */
function checkBothSatisfied($caseId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT user_response, ngo_response FROM satisfaction_requests WHERE case_id = ?");
    $stmt->execute([$caseId]);
    $request = $stmt->fetch();
    
    if ($request && $request['user_response'] === 'satisfied' && $request['ngo_response'] === 'satisfied') {
        return true;
    }
    return false;
}

/**
 * Notify admin to close the case
 */
function notifyAdminForClosure($caseId, $teamMemberId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE satisfaction_requests SET closure_request_sent = 1 WHERE case_id = ?");
    if ($stmt->execute([$caseId])) {
        // Notify ALL admins — never assume ID=1 is the only admin
        $admins = $db->query("SELECT id FROM admins")->fetchAll();
        foreach ($admins as $admin) {
            sendNotification(
                'admin',
                $admin['id'],
                'Case Closure Request',
                "Case #{$caseId} — Both user and NGO are satisfied. Team member requesting case closure.",
                'warning'
            );
        }
        return true;
    }
    return false;
}

/**
 * Send a chat message
 */
function sendChatMessage($senderType, $senderId, $receiverType, $receiverId, $message, $caseId = null) {
    $db = getDB();

    // Store the message as-is (plain text). Callers must NOT pre-sanitize.
    // htmlspecialchars is applied only at display time to avoid double-encoding.
    $cleanMessage = trim($message);

    if (empty($cleanMessage)) {
        return ['success' => false, 'error' => 'Message cannot be empty.'];
    }

    $stmt = $db->prepare("INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message, case_id) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$senderType, $senderId, $receiverType, $receiverId, $cleanMessage, $caseId])) {
        $messageId = $db->lastInsertId();

        // Build a direct link to the chat page based on receiver type
        $chatLinks = [
            'user'        => '/user/chat-reply.php?with=' . $senderType . '&id=' . $senderId,
            'ngo'         => '/ngo/chat-reply.php?with=' . $senderType . '&id=' . $senderId,
            'team_member' => '/team/chat-thread.php?with=' . $senderType . '&id=' . $senderId,
            'admin'       => '/admin/chat.php?with=' . $senderType . '&id=' . $senderId,
        ];
        $chatLink = isset($chatLinks[$receiverType]) ? url($chatLinks[$receiverType]) : '#';

        sendNotification(
            $receiverType,
            $receiverId,
            'New Message',
            'You have a new message. <a href="' . $chatLink . '" class="alert-link">Click to view &rarr;</a>',
            'info'
        );

        return ['success' => true, 'message_id' => $messageId];
    }
    return ['success' => false, 'error' => 'Failed to send message'];
}

/**
 * Get unread message count
 */
function getUnreadMessageCount($userType, $userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = ? AND receiver_id = ? AND is_read = 0");
    $stmt->execute([$userType, $userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get chat thread between two users
 */
function getChatThread($type1, $id1, $type2, $id2, $caseId = null) {
    $db = getDB();
    $query = "SELECT * FROM messages 
              WHERE ((sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?) 
              OR (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?))";
    $params = [$type1, $id1, $type2, $id2, $type2, $id2, $type1, $id1];
    
    if ($caseId !== null) {
        $query .= " AND case_id = ?";
        $params[] = $caseId;
    }
    
    $query .= " ORDER BY created_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get available team member by category
 */
/**
 * Internal helper — find least-loaded active member in a given category.
 * Workload is counted in real-time from the cases table (not a stored counter)
 * so it is always accurate even if cases are closed/cancelled.
 * Returns the member row (with a current_load key) or null if none available.
 */
function findMemberByCategory($db, $category) {
    $stmt = $db->prepare("
        SELECT tm.*,
               COUNT(c.id)                    AS current_load,
               COALESCE(tm.max_cases, 10)     AS effective_max
        FROM   team_members tm
        LEFT JOIN cases c
               ON  c.team_member_id = tm.id
               AND c.status NOT IN ('closed', 'cancelled')
        WHERE  tm.category = ?
          AND  tm.status   = 'active'
        GROUP  BY tm.id
        HAVING current_load < COALESCE(tm.max_cases, 10)
        ORDER  BY current_load ASC
        LIMIT  1
    ");
    $stmt->execute([$category]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get available team member by category (public alias of findMemberByCategory).
 * Replaces the old version that had no workload check.
 */
function getAvailableTeamMemberByCategory($category) {
    return findMemberByCategory(getDB(), $category);
}

/**
 * Find the best available team member for a given issue category.
 *
 * Matching order:
 *   1. Specialist for the exact issue category (e.g. social_worker for family_issues)
 *   2. mental_health_counselor as first generic fallback
 *   3. Any active member with remaining capacity — least loaded first
 *   4. null — no one is available right now
 */
function findAvailableTeamMember($issueCategory) {
    $db = getDB();

    // Maps every user-facing issue category to a team member category.
    // 'other' maps to mental_health_counselor — most versatile role.
    $categoryMap = [
        'depression'        => 'mental_health_counselor',
        'hopelessness'      => 'mental_health_counselor',
        'anxiety'           => 'mental_health_counselor',
        'psychiatric'       => 'psychiatrist',
        'family_issues'     => 'social_worker',
        'marital_issues'    => 'social_worker',
        'rehabilitation'    => 'rehabilitation_specialist',
        'skill_development' => 'skill_development_trainer',
        'other'             => 'mental_health_counselor',
    ];

    $targetCategory = $categoryMap[$issueCategory] ?? 'mental_health_counselor';

    // --- Step 1: exact category match with capacity ---
    $member = findMemberByCategory($db, $targetCategory);
    if ($member) return $member;

    // --- Step 2: generic counselor fallback (only if step 1 used a specialist) ---
    if ($targetCategory !== 'mental_health_counselor') {
        $member = findMemberByCategory($db, 'mental_health_counselor');
        if ($member) return $member;
    }

    // --- Step 3: any active member with remaining capacity, least loaded first ---
    $stmt = $db->prepare("
        SELECT tm.*,
               COUNT(c.id) AS current_load
        FROM   team_members tm
        LEFT JOIN cases c
               ON  c.team_member_id = tm.id
               AND c.status NOT IN ('closed', 'cancelled')
        WHERE  tm.status = 'active'
        GROUP  BY tm.id
        HAVING current_load < COALESCE(tm.max_cases, 10)
        ORDER  BY current_load ASC
        LIMIT  1
    ");
    $stmt->execute();
    $member = $stmt->fetch();
    if ($member) return $member;

    // --- Step 4: nobody available ---
    return null;
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
    
    // Find the best available team member (may be null if all are at capacity)
    $teamMember = findAvailableTeamMember($issueCategory);

    // Case status depends on whether a team member was auto-assigned
    $caseStatus = $teamMember ? 'assigned' : 'pending';

    // Create case
    $caseNumber = generateCaseNumber();

    $stmt = $db->prepare("
        INSERT INTO cases
            (case_number, user_id, ngo_id, team_member_id,
             issue_category, severity_level, description, status, assignment_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $result = $stmt->execute([
        $caseNumber,
        $userId,
        $ngo['id'],
        $teamMember ? $teamMember['id'] : null,
        $issueCategory,
        $severity,
        $description,
        $caseStatus,
    ]);

    if ($result) {
        $caseId = $db->lastInsertId();

        // Update NGO active case count
        $db->prepare("UPDATE ngos SET current_cases = current_cases + 1 WHERE id = ?")
           ->execute([$ngo['id']]);

        // Notify user
        $userMsg = $teamMember
            ? "Your case #{$caseNumber} has been created and assigned to {$ngo['organization_name']}."
            : "Your case #{$caseNumber} has been created. A team member will be assigned shortly.";
        sendNotification('user', $userId, 'Case Created', $userMsg, 'success');

        // Notify NGO
        sendNotification('ngo', $ngo['id'], 'New Case Assigned',
            "A new case #{$caseNumber} has been assigned to your organization.", 'info');

        // Notify the assigned team member directly
        if ($teamMember) {
            sendNotification('team_member', $teamMember['id'], 'New Case Assigned to You',
                "Case #{$caseNumber} ({$issueCategory}) has been assigned to you.", 'info');
        }

        // If no team member found, alert admin to assign one manually
        if (!$teamMember) {
            $adminRows = $db->query("SELECT id FROM admins")->fetchAll();
            foreach ($adminRows as $admin) {
                sendNotification('admin', $admin['id'], 'Manual Assignment Needed',
                    "Case #{$caseNumber} was created but no available team member was found. Please assign one manually.", 'warning');
            }
        }

        return [
            'success'     => true,
            'case_id'     => $caseId,
            'case_number' => $caseNumber,
            'ngo'         => $ngo,
            'team_member' => $teamMember,
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
        
        $stmt = $db->prepare("UPDATE cases SET team_member_id = ?, status = CASE WHEN status = 'pending' THEN 'assigned' ELSE status END WHERE id = ?");
        $stmt->execute([$teamMemberId, $caseId]);
        
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
function adminCloseCase($caseId, $closureRemarks, $adminId, $markRecovered = false) {
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
        
        // Only mark recovered when admin explicitly confirms
        if ($markRecovered) {
            $db->prepare("UPDATE users SET status = 'recovered' WHERE id = ?")->execute([$case['user_id']]);
        }
        
        $db->prepare("INSERT INTO case_progress (case_id, status, notes) VALUES (?, 'closed', ?)")
           ->execute([$caseId, "Case closed by Admin. Remarks: {$closureRemarks}"]);
        
        $userMsg = $markRecovered
            ? "Your case #{$case['case_number']} has been closed. We're glad you're on the path to recovery!"
            : "Your case #{$case['case_number']} has been closed by the administrator.";
        sendNotification('user', $case['user_id'], 'Case Closed', $userMsg, 'success');
        if ($case['ngo_id']) {
            sendNotification('ngo', $case['ngo_id'], 'Case Closed', "Case #{$case['case_number']} has been closed by the administrator.", 'info');
        }
        
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Failed to close case: ' . $e->getMessage()];
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