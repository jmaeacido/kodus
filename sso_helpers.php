<?php

require_once __DIR__ . '/env_helpers.php';
require_once __DIR__ . '/security.php';

function sso_is_configured(): bool
{
    foreach ([
        'CC_OAUTH_CLIENT_ID',
        'CC_OAUTH_AUTHORIZE_URL',
        'CC_OAUTH_TOKEN_URL',
        'CC_OAUTH_USERINFO_URL',
        'CC_OAUTH_REDIRECT_URI',
        'CC_OAUTH_SECRET',
    ] as $key) {
        $value = app_env($key);
        if ($value === null || $value === '') {
            return false;
        }
    }

    return true;
}

function sso_verify_ssl(): bool
{
    $value = strtolower((string) app_env('CC_OAUTH_VERIFY_SSL', 'false'));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function sso_config(): array
{
    $authorizeUrl = (string) app_env('CC_OAUTH_AUTHORIZE_URL', '');
    $authorizeParts = parse_url($authorizeUrl);
    $origin = '';

    if (isset($authorizeParts['scheme'], $authorizeParts['host'])) {
        $origin = $authorizeParts['scheme'] . '://' . $authorizeParts['host'];
    }

    return [
        'client_id' => (string) app_env('CC_OAUTH_CLIENT_ID', ''),
        'client_secret' => (string) app_env('CC_OAUTH_SECRET', ''),
        'authorize_url' => $authorizeUrl,
        'token_url' => (string) app_env('CC_OAUTH_TOKEN_URL', ''),
        'userinfo_url' => (string) app_env('CC_OAUTH_USERINFO_URL', ''),
        'redirect_uri' => (string) app_env('CC_OAUTH_REDIRECT_URI', ''),
        'scope' => (string) app_env('CC_OAUTH_SCOPE', 'basic'),
        'logout_url' => (string) app_env('CC_OAUTH_LOGOUT_URL', $origin !== '' ? $origin . '/api/sso/logout' : ''),
        'logout_all_url' => (string) app_env('CC_OAUTH_LOGOUT_ALL_URL', $origin !== '' ? $origin . '/api/sso/logout-all' : ''),
    ];
}

function sso_ensure_schema(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $columns = [
        'sso_subject' => "ALTER TABLE users ADD COLUMN sso_subject VARCHAR(255) NULL DEFAULT NULL AFTER email",
        'id_number' => "ALTER TABLE users ADD COLUMN id_number VARCHAR(100) NULL DEFAULT NULL AFTER sso_subject",
        'contact_number' => "ALTER TABLE users ADD COLUMN contact_number VARCHAR(50) NULL DEFAULT NULL AFTER id_number",
        'sso_avatar_url' => "ALTER TABLE users ADD COLUMN sso_avatar_url VARCHAR(2048) NULL DEFAULT NULL AFTER contact_number",
    ];

    foreach ($columns as $column => $sql) {
        $escapedColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM users LIKE '{$escapedColumn}'");
        if ($result && $result->num_rows > 0) {
            continue;
        }

        $conn->query($sql);
    }
}

function sso_build_authorize_redirect(): string
{
    $config = sso_config();
    $codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    $state = bin2hex(random_bytes(16));

    $_SESSION['sso_code_verifier'] = $codeVerifier;
    $_SESSION['sso_state'] = $state;

    return $config['authorize_url'] . '?' . http_build_query([
        'response_type' => 'code',
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'scope' => $config['scope'],
        'state' => $state,
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
    ]);
}

function sso_error_message_from_response(array $response): string
{
    $body = $response['body'] ?? null;
    if (is_array($body)) {
        $parts = [];
        foreach (['message', 'error_description', 'error', 'status'] as $key) {
            if (!empty($body[$key]) && is_scalar($body[$key])) {
                $parts[] = (string) $body[$key];
            }
        }

        if ($parts !== []) {
            return implode(' ', array_unique($parts));
        }
    }

    $raw = $response['raw'] ?? '';
    if (is_string($raw) && trim($raw) !== '') {
        return trim($raw);
    }

    return 'HTTP ' . (string) ($response['status'] ?? 0);
}

function sso_http_request(string $method, string $url, array $options = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The cURL extension is required for SSO integration.');
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize SSO request.');
    }

    $headers = $options['headers'] ?? [];
    $body = $options['body'] ?? null;
    $isForm = !empty($options['form']);

    if ($isForm && is_array($body)) {
        $body = http_build_query($body);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    } elseif (is_array($body) || $body instanceof stdClass) {
        $body = json_encode($body);
        $headers[] = 'Content-Type: application/json';
    }

    $verifySsl = sso_verify_ssl();
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    if ($body !== null && strtoupper($method) !== 'GET') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }

    $rawBody = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($rawBody === false) {
        throw new RuntimeException('SSO request failed: ' . ($error !== '' ? $error : 'unknown cURL error'));
    }

    $decoded = json_decode($rawBody, true);

    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : $rawBody,
        'raw' => $rawBody,
    ];
}

function sso_validate_callback_request(): array
{
    if (!empty($_GET['error'])) {
        $description = trim((string) ($_GET['error_description'] ?? ''));
        $message = 'SSO provider error: ' . (string) $_GET['error'];
        if ($description !== '') {
            $message .= ' (' . $description . ')';
        }
        throw new RuntimeException($message);
    }

    $code = trim((string) ($_GET['code'] ?? ''));
    $state = trim((string) ($_GET['state'] ?? ''));
    if ($code === '' || $state === '') {
        throw new RuntimeException('Missing authorization response from Caraga Connect.');
    }

    $expectedState = (string) ($_SESSION['sso_state'] ?? '');
    unset($_SESSION['sso_state']);

    if ($expectedState === '' || !hash_equals($expectedState, $state)) {
        throw new RuntimeException('Invalid SSO state. Please try again.');
    }

    return ['code' => $code];
}

function sso_exchange_code_for_tokens(string $code): array
{
    $config = sso_config();
    $codeVerifier = (string) ($_SESSION['sso_code_verifier'] ?? '');
    unset($_SESSION['sso_code_verifier']);

    if ($codeVerifier === '') {
        throw new RuntimeException('Missing PKCE verifier. Please start the SSO login again.');
    }

    $response = sso_http_request('POST', $config['token_url'], [
        'form' => true,
        'body' => [
            'grant_type' => 'authorization_code',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ],
        'headers' => ['Accept: application/json'],
    ]);

    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($response['body'])) {
        throw new RuntimeException('Token exchange failed. ' . sso_error_message_from_response($response));
    }

    return $response['body'];
}

function sso_fetch_userinfo(string $accessToken): array
{
    $response = sso_http_request('GET', sso_config()['userinfo_url'], [
        'headers' => [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);

    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($response['body'])) {
        throw new RuntimeException('Unable to fetch SSO user profile. ' . sso_error_message_from_response($response));
    }

    return $response['body'];
}

function sso_logout_remote(?string $accessToken, bool $logoutAll = false): array
{
    if (!is_string($accessToken) || $accessToken === '' || !sso_is_configured()) {
        return ['success' => true, 'skipped' => true];
    }

    $config = sso_config();
    $url = $logoutAll ? $config['logout_all_url'] : $config['logout_url'];
    if ($url === '') {
        return ['success' => true, 'skipped' => true];
    }

    try {
        $response = sso_http_request('POST', $url, [
            'headers' => [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            'body' => new stdClass(),
        ]);

        return [
            'success' => $response['status'] >= 200 && $response['status'] < 300,
            'status' => $response['status'],
            'message' => sso_error_message_from_response($response),
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'status' => 0,
            'message' => $e->getMessage(),
        ];
    }
}

function sso_normalize_username(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9._-]+/', '.', $value) ?? '';
    $value = trim($value, '.-_');

    return $value !== '' ? $value : 'sso-user';
}

function sso_generate_unique_username(mysqli $conn, string $preferred): string
{
    $base = sso_normalize_username($preferred);
    $candidate = $base;
    $suffix = 1;

    while (true) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        if (!$stmt) {
            return $candidate;
        }

        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if (!$exists) {
            return $candidate;
        }

        $suffix++;
        $candidate = $base . '.' . $suffix;
    }
}

function sso_parse_name_parts(array $profile): array
{
    $firstName = trim((string) ($profile['fname'] ?? $profile['first_name'] ?? $profile['firstname'] ?? $profile['given_name'] ?? ''));
    $lastName = trim((string) ($profile['sname'] ?? $profile['last_name'] ?? $profile['lastname'] ?? $profile['family_name'] ?? $profile['surname'] ?? ''));
    $middleName = trim((string) ($profile['mname'] ?? $profile['middle_name'] ?? $profile['middlename'] ?? ''));
    $suffix = trim((string) ($profile['suffix'] ?? $profile['ext'] ?? $profile['extension_name'] ?? ''));

    if ($firstName !== '' || $lastName !== '') {
        return [$firstName !== '' ? $firstName : 'SSO', $middleName, $lastName !== '' ? $lastName : 'User', $suffix];
    }

    $fullName = trim((string) ($profile['name'] ?? ''));
    if ($fullName === '') {
        return ['SSO', '', 'User', ''];
    }

    $parts = preg_split('/\s+/', $fullName) ?: [];
    if (count($parts) === 1) {
        return [$parts[0], '', 'User', ''];
    }

    $lastName = (string) array_pop($parts);
    $firstName = (string) array_shift($parts);
    $middleName = implode(' ', $parts);

    return [$firstName, $middleName, $lastName, $suffix];
}

function sso_extract_avatar_url(array $profile): string
{
    $candidates = [
        $profile['avatar_url'] ?? null,
        $profile['picture'] ?? null,
        $profile['photo'] ?? null,
        $profile['photo_url'] ?? null,
        $profile['profile_photo'] ?? null,
        $profile['profile_photo_url'] ?? null,
        $profile['image'] ?? null,
        $profile['image_url'] ?? null,
        $profile['profile_image'] ?? null,
        $profile['profile_image_url'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            continue;
        }

        if (preg_match('#^https?://#i', $candidate)) {
            return $candidate;
        }
    }

    return '';
}

function sso_extract_position_parts(array $profile): array
{
    $position = trim((string) ($profile['position'] ?? $profile['job_title'] ?? $profile['title'] ?? ''));
    $positionAbr = trim((string) ($profile['positionAbr'] ?? $profile['position_abr'] ?? $profile['position_abbr'] ?? $profile['job_title_abbr'] ?? ''));
    $area = trim((string) ($profile['area'] ?? $profile['office'] ?? $profile['department'] ?? $profile['assignment_area'] ?? ''));

    return [$position, $positionAbr, $area];
}

function sso_get_existing_user(mysqli $conn, array $profile): ?array
{
    $candidates = [];

    if (!empty($profile['sub'])) {
        $candidates[] = ['column' => 'sso_subject', 'value' => (string) $profile['sub']];
    }
    if (!empty($profile['id_number'])) {
        $candidates[] = ['column' => 'id_number', 'value' => (string) $profile['id_number']];
    }
    if (!empty($profile['preferred_username'])) {
        $candidates[] = ['column' => 'username', 'value' => (string) $profile['preferred_username']];
    }

    foreach ($candidates as $candidate) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE {$candidate['column']} = ? LIMIT 1");
        if (!$stmt) {
            continue;
        }

        $stmt->bind_param('s', $candidate['value']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$user) {
            continue;
        }

        if (!empty($user['deleted_at'])) {
            throw new RuntimeException('The matching KODUS account is deactivated. Please contact an administrator.');
        }

        return $user;
    }

    return null;
}

function sso_create_or_update_user(mysqli $conn, array $profile): array
{
    sso_ensure_schema($conn);

    $subject = trim((string) ($profile['sub'] ?? ''));
    if ($subject === '') {
        throw new RuntimeException('The SSO provider did not return a unique subject identifier.');
    }

    $email = trim((string) ($profile['email'] ?? ''));
    $preferredUsername = trim((string) ($profile['preferred_username'] ?? ''));
    $idNumber = trim((string) ($profile['id_number'] ?? ''));
    $contactNumber = trim((string) ($profile['contact_number'] ?? ''));
    $ssoAvatarUrl = sso_extract_avatar_url($profile);
    [$firstName, $middleName, $lastName, $suffix] = sso_parse_name_parts($profile);
    [$position, $positionAbr, $area] = sso_extract_position_parts($profile);

    if ($email === '') {
        $email = $subject . '@caraga-connect.local';
    }

    $existingUser = sso_get_existing_user($conn, $profile);
    if ($existingUser) {
        $userId = (int) $existingUser['id'];
        $username = trim((string) ($existingUser['username'] ?? ''));
        $email = trim((string) ($existingUser['email'] ?? ''));
        if ($username === '') {
            $usernameSeed = $preferredUsername !== '' ? $preferredUsername : $email;
            $username = sso_generate_unique_username($conn, $usernameSeed);
        }

        if ($email === '') {
            $email = trim((string) ($profile['email'] ?? ''));
            if ($email === '') {
                $email = $subject . '@caraga-connect.local';
            }
        }

        $firstName = trim((string) ($existingUser['first_name'] ?? '')) !== '' ? (string) $existingUser['first_name'] : $firstName;
        $middleName = trim((string) ($existingUser['middle_name'] ?? '')) !== '' ? (string) $existingUser['middle_name'] : $middleName;
        $lastName = trim((string) ($existingUser['last_name'] ?? '')) !== '' ? (string) $existingUser['last_name'] : $lastName;
        $suffix = trim((string) ($existingUser['ext'] ?? '')) !== '' ? (string) $existingUser['ext'] : $suffix;
        $position = trim((string) ($existingUser['position'] ?? '')) !== '' ? (string) $existingUser['position'] : $position;
        $positionAbr = trim((string) ($existingUser['positionAbr'] ?? '')) !== '' ? (string) $existingUser['positionAbr'] : $positionAbr;
        $area = trim((string) ($existingUser['area'] ?? '')) !== '' ? (string) $existingUser['area'] : $area;
        if ($ssoAvatarUrl === '') {
            $ssoAvatarUrl = trim((string) ($existingUser['sso_avatar_url'] ?? ''));
        }

        $stmt = $conn->prepare(
            'UPDATE users
             SET username = ?, email = ?, first_name = ?, middle_name = ?, last_name = ?, ext = ?, position = ?, positionAbr = ?, area = ?, sso_subject = ?, id_number = ?, contact_number = ?, sso_avatar_url = ?
             WHERE id = ?'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to update the local SSO account.');
        }

        $stmt->bind_param(
            'sssssssssssssi',
            $username,
            $email,
            $firstName,
            $middleName,
            $lastName,
            $suffix,
            $position,
            $positionAbr,
            $area,
            $subject,
            $idNumber,
            $contactNumber,
            $ssoAvatarUrl,
            $userId
        );
        $stmt->execute();
        $stmt->close();

        $result = $conn->query('SELECT * FROM users WHERE id = ' . $userId . ' LIMIT 1');
        $user = $result ? $result->fetch_assoc() : null;
        if (!$user) {
            throw new RuntimeException('Unable to reload the SSO account after update.');
        }

        return $user;
    }

    $usernameSeed = $preferredUsername !== '' ? $preferredUsername : ((strstr($email, '@', true) ?: $email) ?: $subject);
    $username = sso_generate_unique_username($conn, $usernameSeed);
    $password = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $picture = 'default.webp';
    if ($position === '') {
        $position = 'SSO User';
    }
    if ($positionAbr === '') {
        $positionAbr = 'SSO';
    }
    if ($area === '') {
        $area = 'Caraga Connect';
    }
    $userType = 'user';
    $registeredAt = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        'INSERT INTO users (
            username, password, last_name, first_name, middle_name, ext, email, sso_subject, id_number, contact_number, sso_avatar_url,
            position, positionAbr, area, picture, userType, date_registered
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to create the local SSO account.');
    }

    $stmt->bind_param(
        'sssssssssssssssss',
        $username,
        $password,
        $lastName,
        $firstName,
        $middleName,
        $suffix,
        $email,
        $subject,
        $idNumber,
        $contactNumber,
        $ssoAvatarUrl,
        $position,
        $positionAbr,
        $area,
        $picture,
        $userType,
        $registeredAt
    );
    $stmt->execute();
    $userId = (int) $stmt->insert_id;
    $stmt->close();

    $result = $conn->query('SELECT * FROM users WHERE id = ' . $userId . ' LIMIT 1');
    $user = $result ? $result->fetch_assoc() : null;
    if (!$user) {
        throw new RuntimeException('Unable to load the new SSO account.');
    }

    return $user;
}

function sso_handle_callback_error(string $message): void
{
    unset($_SESSION['sso_code_verifier'], $_SESSION['sso_state']);
    $_SESSION['login_error'] = $message;
    header('Location: ../');
    exit;
}
