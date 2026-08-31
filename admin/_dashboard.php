<?php

declare(strict_types=1);

function admin_safe_count(
    PDO $db,
    string $sql
): int {
    try {
        return (int) $db->query($sql)->fetchColumn();
    } catch (Throwable $exception) {
        error_log(
            'Llama Scout admin dashboard count error: ' .
            $exception->getMessage()
        );

        return 0;
    }
}

function admin_dashboard_stats(PDO $db): array
{
    return [
        'new_places' => admin_safe_count(
            $db,
            "SELECT COUNT(*)
             FROM place_submissions
             WHERE status IN ('pending','needs-changes')"
        ),

        'updates' => admin_safe_count(
            $db,
            "SELECT COUNT(*)
             FROM place_update_submissions
             WHERE status IN ('pending','needs-changes')"
        ),

        'reports' => admin_safe_count(
            $db,
            "SELECT COUNT(*)
             FROM place_reports
             WHERE status IN ('open','investigating')"
        ),

        'scout_reviews' => admin_safe_count(
            $db,
            "SELECT COUNT(*)
             FROM scout_profiles
             WHERE status IN (
                'application_submitted',
                'pending_approval'
             )"
        ),

        'orders' => admin_safe_count(
            $db,
            "SELECT COUNT(*)
             FROM shop_orders
             WHERE payment_status = 'paid'
               AND order_status NOT IN (
                    'shipped',
                    'delivered',
                    'cancelled',
                    'canceled',
                    'refunded'
               )"
        ),

        'places' => admin_safe_count(
            $db,
            "SELECT COUNT(*)
             FROM places
             WHERE status IN ('active','featured')"
        ),

        'members' => admin_safe_count(
            $db,
            "SELECT COUNT(*)
             FROM users
             WHERE status = 'active'"
        ),

        'paid_members' => admin_safe_count(
            $db,
            "SELECT COUNT(*)
             FROM users
             WHERE status = 'active'
               AND membership_status IN (
                    'active',
                    'trialing'
               )"
        ),
    ];
}

function admin_dashboard_queue(PDO $db): array
{
    $items = [];

    try {
        $stmt = $db->query(
            "SELECT
                ps.id,
                ps.place_name AS title,
                ps.submitted_at AS occurred_at,
                COALESCE(
                    NULLIF(u.display_name, ''),
                    NULLIF(u.username, ''),
                    'Member'
                ) AS actor
             FROM place_submissions ps
             LEFT JOIN users u
                ON u.id = ps.user_id
             WHERE ps.status IN ('pending','needs-changes')
             ORDER BY ps.submitted_at DESC
             LIMIT 8"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $items[] = [
                'type' => 'New Place',
                'icon' => 'fa-location-dot',
                'title' => (string) $row['title'],
                'meta' => 'Submitted by ' . (string) $row['actor'],
                'time' => (string) $row['occurred_at'],
                'href' => '/moderate-submission.php?id=' . (int) $row['id'],
                'action' => 'Review',
            ];
        }
    } catch (Throwable $exception) {
        error_log('Admin new-place queue error: ' . $exception->getMessage());
    }

    try {
        $stmt = $db->query(
            "SELECT
                pus.id,
                p.name AS title,
                pus.submitted_at AS occurred_at,
                COALESCE(
                    NULLIF(u.display_name, ''),
                    NULLIF(u.username, ''),
                    'Member'
                ) AS actor
             FROM place_update_submissions pus
             INNER JOIN places p
                ON p.id = pus.place_id
             LEFT JOIN users u
                ON u.id = pus.user_id
             WHERE pus.status IN ('pending','needs-changes')
             ORDER BY pus.submitted_at DESC
             LIMIT 8"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $items[] = [
                'type' => 'Place Update',
                'icon' => 'fa-pen-to-square',
                'title' => (string) $row['title'],
                'meta' => 'Submitted by ' . (string) $row['actor'],
                'time' => (string) $row['occurred_at'],
                'href' => '/moderate-update.php?id=' . (int) $row['id'],
                'action' => 'Review',
            ];
        }
    } catch (Throwable $exception) {
        error_log('Admin update queue error: ' . $exception->getMessage());
    }

    try {
        $stmt = $db->query(
            "SELECT
                pr.id,
                p.name AS title,
                pr.problem_type,
                pr.created_at AS occurred_at,
                COALESCE(
                    NULLIF(u.display_name, ''),
                    NULLIF(u.username, ''),
                    'Member'
                ) AS actor
             FROM place_reports pr
             INNER JOIN places p
                ON p.id = pr.place_id
             LEFT JOIN users u
                ON u.id = pr.user_id
             WHERE pr.status IN ('open','investigating')
             ORDER BY pr.created_at DESC
             LIMIT 8"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $problem = ucwords(
                str_replace(
                    ['-', '_'],
                    ' ',
                    (string) $row['problem_type']
                )
            );

            $items[] = [
                'type' => 'Problem Report',
                'icon' => 'fa-triangle-exclamation',
                'title' => (string) $row['title'],
                'meta' => $problem . ' · ' . (string) $row['actor'],
                'time' => (string) $row['occurred_at'],
                'href' => '/moderate-report.php?id=' . (int) $row['id'],
                'action' => 'Review',
            ];
        }
    } catch (Throwable $exception) {
        error_log('Admin report queue error: ' . $exception->getMessage());
    }

    try {
        $stmt = $db->query(
            "SELECT
                so.id,
                so.order_number,
                so.customer_name,
                so.customer_email,
                so.total_cents,
                so.currency,
                COALESCE(
                    so.paid_at,
                    so.created_at
                ) AS occurred_at
             FROM shop_orders so
             WHERE so.payment_status = 'paid'
               AND so.order_status NOT IN (
                    'shipped',
                    'delivered',
                    'cancelled',
                    'canceled',
                    'refunded'
               )
             ORDER BY occurred_at DESC
             LIMIT 8"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $customer = trim(
                (string) ($row['customer_name'] ?? '')
            );

            if ($customer === '') {
                $customer = trim(
                    (string) ($row['customer_email'] ?? '')
                );
            }

            if ($customer === '') {
                $customer = 'Customer';
            }

            $items[] = [
                'type' => 'Paid Order',
                'icon' => 'fa-box',
                'title' => (string) $row['order_number'],
                'meta' => $customer . ' · $' .
                    number_format(
                        ((int) $row['total_cents']) / 100,
                        2
                    ),
                'time' => (string) $row['occurred_at'],
                'href' => '',
                'action' => 'Orders next',
            ];
        }
    } catch (Throwable $exception) {
        error_log('Admin order queue error: ' . $exception->getMessage());
    }

    try {
        $stmt = $db->query(
            "SELECT
                sp.id,
                sp.status,
                COALESCE(
                    sp.application_submitted_at,
                    sp.updated_at
                ) AS occurred_at,
                COALESCE(
                    NULLIF(u.display_name, ''),
                    NULLIF(u.username, ''),
                    'Member'
                ) AS actor
             FROM scout_profiles sp
             INNER JOIN users u
                ON u.id = sp.user_id
             WHERE sp.status IN (
                    'application_submitted',
                    'pending_approval'
               )
             ORDER BY occurred_at DESC
             LIMIT 8"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $items[] = [
                'type' => 'Scout Review',
                'icon' => 'fa-binoculars',
                'title' => (string) $row['actor'],
                'meta' => ucwords(
                    str_replace(
                        '_',
                        ' ',
                        (string) $row['status']
                    )
                ),
                'time' => (string) $row['occurred_at'],
                'href' => '',
                'action' => 'Scouts next',
            ];
        }
    } catch (Throwable $exception) {
        error_log('Admin scout queue error: ' . $exception->getMessage());
    }

    usort(
        $items,
        static function (array $a, array $b): int {
            return strcmp(
                (string) $b['time'],
                (string) $a['time']
            );
        }
    );

    return array_slice($items, 0, 12);
}

function admin_format_datetime(
    ?string $value
): string {
    $value = trim((string) $value);

    if ($value === '') {
        return 'Unknown time';
    }

    try {
        $date = new DateTimeImmutable($value);
        return $date->format('M j, Y · g:i a');
    } catch (Throwable) {
        return $value;
    }
}
