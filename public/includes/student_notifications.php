<?php

function studentNotificationFeatureEnabled(mysqli $conn)
{
    $featureEnabled = false;
    $notificationTableCheck = $conn->query("SHOW TABLES LIKE 'notification_reads'");
    if ($notificationTableCheck) {
        $featureEnabled = $notificationTableCheck->num_rows > 0;
        $notificationTableCheck->close();
    }

    return $featureEnabled;
}

function studentFetchNotificationRows(mysqli $conn, int $userId, bool $featureEnabled, int $limit = 10)
{
    $limit = max(1, $limit);
    $rows = [];

    if ($featureEnabled) {
        $sql = "
            SELECT
                a.id,
                a.content,
                a.created_at,
                au.display_name,
                CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS is_read
            FROM announcements a
            JOIN admin_users au ON a.admin_id = au.id
            LEFT JOIN notification_reads nr
                ON nr.announcement_id = a.id
               AND nr.user_id = ?
            ORDER BY a.created_at DESC
            LIMIT {$limit}
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $row['is_read'] = (int) $row['is_read'];
                $rows[] = $row;
            }
            $stmt->close();
        }
    } else {
        $sql = "
            SELECT a.id, a.content, a.created_at, au.display_name
            FROM announcements a
            JOIN admin_users au ON a.admin_id = au.id
            ORDER BY a.created_at DESC
            LIMIT {$limit}
        ";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['is_read'] = strtotime((string) $row['created_at']) >= strtotime('-7 days') ? 0 : 1;
                $rows[] = $row;
            }
            $result->close();
        }
    }

    return $rows;
}

function studentFetchUnreadNotificationCount(mysqli $conn, int $userId, bool $featureEnabled)
{
    if ($featureEnabled) {
        $sql = "
            SELECT COUNT(*) AS unread_count
            FROM announcements a
            LEFT JOIN notification_reads nr
                ON nr.announcement_id = a.id
               AND nr.user_id = ?
            WHERE nr.id IS NULL
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int) ($row['unread_count'] ?? 0);
        }

        return 0;
    }

    $count = 0;
    $result = $conn->query("SELECT created_at FROM announcements ORDER BY created_at DESC LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (strtotime((string) $row['created_at']) >= strtotime('-7 days')) {
                $count++;
            }
        }
        $result->close();
    }

    return $count;
}

function studentHandleNotificationAjax(mysqli $conn, int $userId, bool $featureEnabled)
{
    if (isset($_GET['ajax_fetch_notifications'])) {
        header('Content-Type: application/json');

        $notifications = studentFetchNotificationRows($conn, $userId, $featureEnabled, 10);
        $unreadCount = studentFetchUnreadNotificationCount($conn, $userId, $featureEnabled);

        echo json_encode([
            'feature_enabled' => $featureEnabled,
            'announcements' => $notifications,
            'unread_count' => $unreadCount,
        ]);
        exit;
    }

    if (isset($_GET['ajax_mark_notification_read'])) {
        header('Content-Type: application/json');

        if (!$featureEnabled) {
            http_response_code(503);
            echo json_encode(['error' => 'Notification tracking is unavailable until the latest database schema is applied.']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $markAll = isset($_POST['mark_all']) && $_POST['mark_all'] === '1';

        if ($markAll) {
            $sql = "
                INSERT INTO notification_reads (user_id, announcement_id, read_at)
                SELECT ?, a.id, NOW()
                FROM announcements a
                LEFT JOIN notification_reads nr
                    ON nr.announcement_id = a.id
                   AND nr.user_id = ?
                WHERE nr.id IS NULL
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to prepare mark-all query']);
                exit;
            }

            $stmt->bind_param('ii', $userId, $userId);
            if (!$stmt->execute()) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to mark notifications as read']);
                $stmt->close();
                exit;
            }
            $stmt->close();
        } else {
            $announcementId = (int) ($_POST['announcement_id'] ?? 0);
            if ($announcementId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid announcement ID']);
                exit;
            }

            $stmt = $conn->prepare(
                'INSERT INTO notification_reads (user_id, announcement_id, read_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)'
            );
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to prepare read query']);
                exit;
            }

            $stmt->bind_param('ii', $userId, $announcementId);
            if (!$stmt->execute()) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to mark notification as read']);
                $stmt->close();
                exit;
            }
            $stmt->close();
        }

        $unreadCount = studentFetchUnreadNotificationCount($conn, $userId, true);
        echo json_encode([
            'success' => true,
            'unread_count' => $unreadCount,
        ]);
        exit;
    }
}
