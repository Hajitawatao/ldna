<?php
/**
 * One place to point the LDNA system at the employee portal.
 *
 * driver 'http'  -> calls a portal API (the staging mock, or the real portal's API)
 * driver 'mysql' -> reads the portal's database directly (see lib/portal.php)
 *
 * Nothing else in the codebase needs to change when you switch.
 */
return [
    'portal' => [
        'driver' => getenv('PORTAL_DRIVER') ?: 'http',

        // driver = http
        'base_url' => getenv('PORTAL_URL') ?: 'http://127.0.0.1:8100/api',
        'timeout' => 5,
        'auth_header' => getenv('PORTAL_AUTH') ?: null,   // e.g. 'Bearer xxxx'

        // driver = mysql (fill in when the portal database is available)
        'dsn' => getenv('PORTAL_DSN') ?: 'mysql:host=127.0.0.1;dbname=employee_portal;charset=utf8mb4',
        'user' => getenv('PORTAL_DB_USER') ?: '',
        'password' => getenv('PORTAL_DB_PASS') ?: '',
        'query' => 'SELECT employee_id, surname, first_name, middle_initial, employment_status, appointment,
                           position_title, division_code, division_name, area_name,
                           plantilla_item_id, plantilla_item_name, plantilla_slot,
                           salary_grade, photo_url
                    FROM vw_ldna_employees',
    ],
];
