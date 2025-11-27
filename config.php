<?php
session_start();

// KONFIGURASI DATABASE
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bank_sampah');
define('DB_CHARSET', 'utf8mb4');

// KONFIGURASI EMAIL - DEVELOPMENT MODE
define('EMAIL_FROM', 'no-reply@banksampah.com');
define('EMAIL_FROM_NAME', 'Bank Sampah');
define('EMAIL_SUBJECT_VERIFICATION', 'Verifikasi Akun Bank Sampah');
define('EMAIL_DEVELOPMENT_MODE', true);

// KONFIGURASI KEAMANAN
define('SESSION_TIMEOUT', 1800);
define('CSRF_TOKEN_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);

// BUAT KONEKSI DATABASE
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    error_log("KONEKSI DATABASE GAGAL: " . $e->getMessage());
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}

// ===== FUNGSI HELPER DASAR =====
function redirect($url, $statusCode = 302) {
    header("Location: " . $url, true, $statusCode);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['mitra_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isUser() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

function isMitra() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'mitra';
}

function isVerified() {
    return isset($_SESSION['is_verified']) && $_SESSION['is_verified'] === 'yes';
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ===== FUNGSI VALIDASI =====
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{10,13}$/', $phone);
}

function validateNIK($nik) {
    return preg_match('/^[0-9]{16}$/', $nik);
}

function validatePassword($password) {
    return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
}

// ===== FUNGSI KEAMANAN =====
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if ((time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_LIFETIME) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function checkLoginAttempts($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'time' => time()];
    }
    
    $attempts = $_SESSION[$key];
    
    if (time() - $attempts['time'] > LOCKOUT_TIME) {
        $_SESSION[$key] = ['count' => 1, 'time' => time()];
        return true;
    }
    
    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

function resetLoginAttempts($identifier) {
    $key = 'login_attempts_' . md5($identifier);
    unset($_SESSION[$key]);
}

// ===== FUNGSI VERIFIKASI EMAIL =====
function generateVerificationCode() {
    return sprintf("%06d", random_int(1, 999999));
}

function sendVerificationEmail($email, $nama, $verification_code) {
    if (EMAIL_DEVELOPMENT_MODE) {
        $_SESSION['dev_verification'] = [
            'code' => $verification_code,
            'email' => $email,
            'nama' => $nama,
            'time' => time()
        ];
        
        error_log("DEVELOPMENT MODE: Verification code for $email: $verification_code");
        return true;
    }
    
    $subject = EMAIL_SUBJECT_VERIFICATION;
    $message = createVerificationEmailTemplate($nama, $verification_code);
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
        'Reply-To: ' . EMAIL_FROM,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($email, $subject, $message, implode("\r\n", $headers));
}

function createVerificationEmailTemplate($nama, $verification_code) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; }
            .code { font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Bank Sampah</h1>
            </div>
            <div class='content'>
                <h2>Halo, $nama!</h2>
                <p>Kode verifikasi Anda:</p>
                <div class='code'>$verification_code</div>
                <p>Masukkan kode ini untuk verifikasi akun Anda.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function getDevelopmentVerificationCode() {
    return $_SESSION['dev_verification'] ?? null;
}

function clearDevelopmentVerificationCode() {
    unset($_SESSION['dev_verification']);
}

function verifyUserAccount($username, $verification_code) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET is_verified = 'yes', 
                verification_code = NULL, 
                status = 'active'
            WHERE username = ? AND verification_code = ?
        ");
        
        $stmt->execute([$username, $verification_code]);
        
        if ($stmt->rowCount() > 0) {
            clearDevelopmentVerificationCode();
            return true;
        }
        
        if (EMAIL_DEVELOPMENT_MODE) {
            $dev_code = getDevelopmentVerificationCode();
            if ($dev_code && $dev_code['code'] === $verification_code) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET is_verified = 'yes', 
                        verification_code = NULL,
                        status = 'active'
                    WHERE username = ?
                ");
                
                if ($stmt->execute([$username]) && $stmt->rowCount() > 0) {
                    clearDevelopmentVerificationCode();
                    return true;
                }
            }
        }
        
        return false;
        
    } catch(PDOException $e) {
        error_log("VERIFICATION ERROR: " . $e->getMessage());
        return false;
    }
}

// ===== FUNGSI AUTHENTIKASI =====
function loginUser($username, $password) {
    global $pdo;
    
    if (!checkLoginAttempts($username)) {
        return ['success' => false, 'error' => 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            if ($user['is_verified'] !== 'yes') {
                return ['success' => false, 'error' => 'Akun belum diverifikasi. Silakan cek email Anda.'];
            }
            
            if ($user['status'] !== 'active') {
                return ['success' => false, 'error' => 'Akun tidak aktif. Silakan hubungi admin.'];
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['saldo'] = $user['saldo'];
            $_SESSION['is_verified'] = $user['is_verified'];
            $_SESSION['login_time'] = time();
            
            resetLoginAttempts($username);
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Username atau password salah.'];
        
    } catch(PDOException $e) {
        error_log("LOGIN ERROR: " . $e->getMessage());
        return ['success' => false, 'error' => 'Terjadi kesalahan sistem.'];
    }
}

function loginMitra($username, $password) {
    global $pdo;
    
    if (!checkLoginAttempts('mitra_' . $username)) {
        return ['success' => false, 'error' => 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM mitra WHERE username = ? AND status_verifikasi = 'verified'");
        $stmt->execute([$username]);
        $mitra = $stmt->fetch();
        
        if ($mitra && verifyPassword($password, $mitra['password'])) {
            $_SESSION['mitra_id'] = $mitra['id'];
            $_SESSION['mitra_nama'] = $mitra['nama_mitra'];
            $_SESSION['username'] = $mitra['username'];
            $_SESSION['role'] = 'mitra';
            $_SESSION['is_verified'] = 'yes';
            $_SESSION['login_time'] = time();
            
            resetLoginAttempts('mitra_' . $username);
            
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Login gagal! Periksa username/password atau status verifikasi.'];
        
    } catch(PDOException $e) {
        error_log("MITRA LOGIN ERROR: " . $e->getMessage());
        return ['success' => false, 'error' => 'Terjadi kesalahan sistem.'];
    }
}

// ===== FUNGSI FLASH MESSAGE =====
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'time' => time()
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        if (time() - $message['time'] > 10) {
            return null;
        }
        
        return $message;
    }
    return null;
}

// ===== SESSION MANAGEMENT =====
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
}

// Handle logout yang aman
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $previous_role = $_SESSION['role'] ?? 'user';
    session_destroy();
    
    switch($previous_role) {
        case 'admin':
            redirect('login.php?type=admin');
            break;
        case 'mitra':
            redirect('login-mitra.php');
            break;
        default:
            redirect('login.php?type=user');
    }
    exit;
}

// Auto display flash messages
$flashMessage = getFlashMessage();

?>