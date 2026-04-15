<?php

require_once __DIR__ . '/helpers.php';

function studentNavLinkClass($activePage, $targetPage)
{
    return $activePage === $targetPage ? 'nav-link active' : 'nav-link';
}

function renderStudentNavbar($activePage, $newAnnCount)
{
    $safeCount = max(0, (int) $newAnnCount);
    $currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? 'student_dashboard.php'));
    ?>
    <nav class="academic-ledger-navbar">
        <div class="nav-container">
            <div class="brand">
                <h1 class="brand-title">College of Computer Studies Sit-in Monitoring System</h1>
            </div>
            <div class="nav-links">
                <div class="student-notification" id="studentNotificationRoot">
                    <button
                        type="button"
                        class="student-notification-trigger"
                        id="studentNotifTrigger"
                        aria-label="Open notifications"
                        aria-expanded="false"
                        aria-haspopup="true"
                        aria-controls="studentNotifDropdown">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="student-notification-badge<?= $safeCount > 0 ? '' : ' is-hidden' ?>" id="studentNotifBadge"><?= $safeCount > 99 ? '99+' : $safeCount ?></span>
                    </button>

                    <div class="student-notification-dropdown" id="studentNotifDropdown" role="menu" aria-label="Announcement notifications">
                        <div class="student-notification-header">
                            <h3>Announcements</h3>
                            <span class="student-notification-chip" id="studentNotifUnreadChip"><?= $safeCount ?> unread</span>
                        </div>

                        <div class="student-notification-list" id="studentNotifList"></div>

                        <div class="student-notification-footer">
                            <button type="button" class="student-notification-mark-all" id="studentNotifMarkAll">Mark all as read</button>
                        </div>
                    </div>
                </div>

                <a class="<?= esc(studentNavLinkClass($activePage, 'home')) ?>" href="student_dashboard.php">Home</a>
                <a class="<?= esc(studentNavLinkClass($activePage, 'profile')) ?>" href="edit_profile.php">Edit Profile</a>
                <a class="<?= esc(studentNavLinkClass($activePage, 'reservations')) ?>" href="reservations.php">Reservations</a>
                <a class="<?= esc(studentNavLinkClass($activePage, 'history')) ?>" href="sit_in_history_student.php">Sit-in History</a>
                <a class="nav-logout" href="<?= esc($currentPage) ?>?logout=1">Log out</a>
            </div>
        </div>
    </nav>
    <?php
}

function renderStudentNotificationScript($notificationFeatureEnabled)
{
    ?>
    <script>
        (function () {
            const notificationFeatureEnabled = <?= $notificationFeatureEnabled ? 'true' : 'false' ?>;
            const notificationEndpoint = window.location.pathname.split('/').pop() || 'student_dashboard.php';
            const root = document.getElementById('studentNotificationRoot');
            const trigger = document.getElementById('studentNotifTrigger');
            const dropdown = document.getElementById('studentNotifDropdown');
            const badge = document.getElementById('studentNotifBadge');
            const unreadChip = document.getElementById('studentNotifUnreadChip');
            const list = document.getElementById('studentNotifList');
            const markAllBtn = document.getElementById('studentNotifMarkAll');

            if (!root || !trigger || !dropdown || !badge || !unreadChip || !list || !markAllBtn) {
                return;
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function formatDate(value) {
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return new Intl.DateTimeFormat('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                }).format(date);
            }

            function setBadgeCount(count) {
                const safeCount = Math.max(0, Number(count) || 0);
                unreadChip.textContent = safeCount + ' unread';

                if (safeCount > 0) {
                    badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
                    badge.classList.remove('is-hidden');
                    trigger.classList.add('has-unread');
                } else {
                    badge.textContent = '0';
                    badge.classList.add('is-hidden');
                    trigger.classList.remove('has-unread');
                }
            }

            function highlightAnnouncement(announcementId) {
                const card = document.querySelector('.dh-announcement-item[data-announcement-id="' + announcementId + '"]');
                if (!card) {
                    return;
                }

                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.classList.add('dh-announcement-item-highlight');
                window.setTimeout(function () {
                    card.classList.remove('dh-announcement-item-highlight');
                }, 1800);
            }

            function closeDropdown() {
                dropdown.classList.remove('active');
                trigger.setAttribute('aria-expanded', 'false');
            }

            function openDropdown() {
                dropdown.classList.add('active');
                trigger.setAttribute('aria-expanded', 'true');
            }

            function renderNotifications(items) {
                if (!Array.isArray(items) || items.length === 0) {
                    list.innerHTML = '<p class="student-notification-empty">No announcements yet.</p>';
                    markAllBtn.disabled = true;
                    return;
                }

                list.innerHTML = items.map(function (item) {
                    const id = Number(item.id) || 0;
                    const isUnread = Number(item.is_read) === 0;
                    const preview = String(item.content || '').trim();

                    return (
                        '<button type="button" class="student-notification-item ' + (isUnread ? 'unread' : '') + '" data-announcement-id="' + id + '">' +
                            '<div class="student-notification-item-top">' +
                                '<span class="student-notification-author">' + escapeHtml(item.display_name || 'Admin') + '</span>' +
                                '<span class="student-notification-date">' + escapeHtml(formatDate(item.created_at || '')) + '</span>' +
                            '</div>' +
                            '<p class="student-notification-message">' + escapeHtml(preview) + '</p>' +
                        '</button>'
                    );
                }).join('');

                markAllBtn.disabled = items.every(function (item) {
                    return Number(item.is_read) !== 0;
                });
            }

            async function loadNotifications() {
                try {
                    const response = await fetch(notificationEndpoint + '?ajax_fetch_notifications=1', {
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!response.ok) {
                        throw new Error('Unable to fetch notifications');
                    }

                    const payload = await response.json();
                    setBadgeCount(payload.unread_count || 0);
                    renderNotifications(payload.announcements || []);

                    if (!payload.feature_enabled) {
                        markAllBtn.disabled = true;
                        markAllBtn.textContent = 'Read tracking unavailable';
                    }
                } catch (error) {
                    list.innerHTML = '<p class="student-notification-empty">Unable to load notifications right now.</p>';
                    markAllBtn.disabled = true;
                }
            }

            async function markOneAsRead(announcementId) {
                if (!notificationFeatureEnabled || announcementId <= 0) {
                    return;
                }

                const body = new URLSearchParams();
                body.set('announcement_id', String(announcementId));

                const response = await fetch(notificationEndpoint + '?ajax_mark_notification_read=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: body.toString()
                });

                if (!response.ok) {
                    throw new Error('Unable to update notification');
                }
            }

            async function markAllAsRead() {
                if (!notificationFeatureEnabled) {
                    return;
                }

                const body = new URLSearchParams();
                body.set('mark_all', '1');

                const response = await fetch(notificationEndpoint + '?ajax_mark_notification_read=1', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json'
                    },
                    body: body.toString()
                });

                if (!response.ok) {
                    throw new Error('Unable to update notifications');
                }
            }

            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                if (dropdown.classList.contains('active')) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            });

            document.addEventListener('click', function (event) {
                if (!root.contains(event.target)) {
                    closeDropdown();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeDropdown();
                }
            });

            list.addEventListener('click', async function (event) {
                const row = event.target.closest('.student-notification-item');
                if (!row) {
                    return;
                }

                const announcementId = Number(row.dataset.announcementId || '0');

                try {
                    await markOneAsRead(announcementId);
                    closeDropdown();
                    highlightAnnouncement(announcementId);
                    await loadNotifications();
                } catch (error) {
                    closeDropdown();
                }
            });

            markAllBtn.addEventListener('click', async function () {
                try {
                    await markAllAsRead();
                    closeDropdown();
                    await loadNotifications();
                } catch (error) {
                    // Keep UI usable and let the next poll refresh state.
                }
            });

            loadNotifications();
            if (notificationFeatureEnabled) {
                window.setInterval(loadNotifications, 60000);
            }
        })();
    </script>
    <?php
}
