<?php
/**
 * REST API endpoints for nanoPost verification callbacks.
 */

defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    // Domain verification - proves plugin is installed at this domain
    register_rest_route('nanopost/v1', '/verify', [
        'methods' => 'GET',
        'callback' => 'nanopost_handle_domain_verification',
        'permission_callback' => '__return_true',
    ]);

    // Recipient verification - HMAC protected
    register_rest_route('nanopost/v1', '/verify-recipient', [
        'methods' => 'GET',
        'callback' => 'nanopost_handle_recipient_verification',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Handle domain verification callback.
 * Echoes back the challenge to prove plugin is installed.
 */
function nanopost_handle_domain_verification($request) {
    $challenge = $request->get_param('challenge');

    if (empty($challenge)) {
        return new WP_Error('missing_challenge', 'Challenge required', ['status' => 400]);
    }

    // Prevent caching of verification responses
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    return [
        'challenge' => $challenge,
        'site_url' => site_url(),
    ];
}

/**
 * Handle recipient verification callback.
 * Validates HMAC signature, then checks if email is a WP user.
 */
function nanopost_handle_recipient_verification($request) {
    $email = $request->get_param('email');
    $timestamp = $request->get_param('timestamp');
    $signature = $request->get_param('signature');
    $site_secret = get_option('nanopost_site_secret');

    nanopost_debug("verify-recipient called for: {$email}");

    // Prevent caching
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    // Verify signature exists
    if (empty($signature) || empty($timestamp) || empty($site_secret)) {
        nanopost_debug("verify-recipient: missing signature, timestamp, or site_secret");
        return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
    }

    // Check timestamp (within 5 minutes)
    if (abs(time() - intval($timestamp)) > 300) {
        nanopost_debug("verify-recipient: timestamp expired (diff=" . abs(time() - intval($timestamp)) . "s)");
        return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
    }

    // Verify HMAC signature
    $expected = hash_hmac('sha256', $email . $timestamp, $site_secret);
    if (!hash_equals($expected, $signature)) {
        nanopost_debug("verify-recipient: HMAC signature mismatch");
        return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
    }

    nanopost_debug("verify-recipient: signature valid, checking recipient");

    // Signature valid - now check recipient
    if (empty($email) || !is_email($email)) {
        nanopost_debug("verify-recipient: invalid email format");
        return ['allowed' => false, 'reason' => 'Invalid email'];
    }

    // Check if email belongs to a WP user
    if (email_exists($email)) {
        nanopost_debug("verify-recipient: {$email} is a WordPress user - ALLOWED");
        return ['allowed' => true, 'reason' => 'WordPress user'];
    }

    // Check if it's the admin email
    if ($email === get_option('admin_email')) {
        nanopost_debug("verify-recipient: {$email} is admin email - ALLOWED");
        return ['allowed' => true, 'reason' => 'Admin email'];
    }

    nanopost_debug("verify-recipient: {$email} not a WordPress user - DENIED");
    return ['allowed' => false, 'reason' => 'Not a WordPress user'];
}
