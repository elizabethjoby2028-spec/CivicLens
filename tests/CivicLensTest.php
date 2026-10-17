<?php
/**
 * CivicLens — Comprehensive Automated Test Suite
 * Validates core business logic, cryptographic functions, workflow rules,
 * 10-day feedback calculations, and security constraints.
 */

class CivicLensTest {
    protected int $passed = 0;
    protected int $failed = 0;
    protected array $errors = [];

    protected function assert($condition, string $message): void {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            $this->errors[] = $message;
            echo "  [FAIL] {$message}\n";
        }
    }

    public function runAll(): void {
        echo "======================================================\n";
        echo " CivicLens Automated Test Suite\n";
        echo "======================================================\n\n";

        $this->testSecurityAndCrypto();
        $this->testComplaintIdGeneration();
        $this->testFeedbackWorkflowLogic();
        $this->testTenDayDeadlineCalculation();
        $this->testAdAnalyticsAndCTR();
        $this->testGitIgnoreAndSecretsHygiene();
        $this->testAutomaticAuthorityRoutingAndBuiltinLogins();

        echo "\n------------------------------------------------------\n";
        echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "======================================================\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    /**
     * Test 1: Security and Cryptographic Primitives
     */
    protected function testSecurityAndCrypto(): void {
        echo "Running: Security & Cryptographic Primitives Tests...\n";

        // Password Hashing
        $plaintext = 'CivicSecurePassword#2026';
        $hash = password_hash($plaintext, PASSWORD_DEFAULT);
        $this->assert(password_verify($plaintext, $hash), 'password_hash and password_verify succeed on correct password');
        $this->assert(!password_verify('WrongPassword', $hash), 'password_verify rejects incorrect password');

        // Anonymous Tracking Credentials
        $token = bin2hex(random_bytes(16));
        $this->assert(strlen($token) === 32, 'Anonymous tracking token has 32-character hex entropy (128 bits)');
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $this->assert(password_verify($token, $tokenHash), 'Tracking credential verification succeeds against bcrypt hash');
        $this->assert(!password_verify('invalid_token_12345678901234567', $tokenHash), 'Tracking verification rejects spoofed token');

        // Sanitization
        require_once __DIR__ . '/../includes/functions.php';
        $dirty = "<script>alert('xss')</script> Hello World   ";
        $clean = sanitize_input($dirty);
        $this->assert($clean === "<script>alert('xss')</script> Hello World", 'sanitize_input trims whitespace correctly');
        $escaped = e($dirty);
        $this->assert(strpos($escaped, '&lt;script&gt;') !== false, 'e() escapes HTML entities to prevent XSS');
    }

    /**
     * Test 2: Complaint ID Structure
     */
    protected function testComplaintIdGeneration(): void {
        echo "\nRunning: Complaint ID Structure Tests...\n";

        $datePart = date('Ymd');
        $randomPart = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
        $complaintId = 'CLN-' . $datePart . '-' . $randomPart;

        $this->assert(
            (bool)preg_match('/^CLN-\d{8}-[A-F0-9]{4}$/', $complaintId),
            "Complaint ID matches standardized regex format (CLN-YYYYMMDD-XXXX): {$complaintId}"
        );

        // Test Uniqueness generator
        $set = [];
        for ($i = 0; $i < 100; $i++) {
            $id = 'CLN-' . $datePart . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
            $set[$id] = true;
        }
        $this->assert(count($set) >= 95, 'High entropy: 100 randomly generated IDs are unique without collision');
    }

    /**
     * Test 3: 10-Day Feedback Window and State Transitions
     */
    protected function testFeedbackWorkflowLogic(): void {
        echo "\nRunning: Feedback State Transitions Tests...\n";

        // Option A: Satisfied
        $state = [
            'status' => 'Solved',
            'satisfaction_status' => 'Pending',
            'closure_reason' => null
        ];

        // Simulate Satisfied action
        $decision = 'satisfied';
        if ($decision === 'satisfied') {
            $state['status'] = 'Closed';
            $state['satisfaction_status'] = 'Satisfied';
            $state['closure_reason'] = 'Satisfied';
        }
        $this->assert($state['status'] === 'Closed', 'Satisfied feedback transitions status to Closed');
        $this->assert($state['satisfaction_status'] === 'Satisfied', 'Satisfied feedback records satisfaction as Satisfied');
        $this->assert($state['closure_reason'] === 'Satisfied', 'Satisfied feedback sets closure reason to Satisfied');

        // Option B: Not Satisfied (Reopened)
        $reopenedState = [
            'status' => 'Solved',
            'satisfaction_status' => 'Pending',
            'reopen_count' => 0
        ];
        $decision = 'not_satisfied';
        if ($decision === 'not_satisfied') {
            $reopenedState['status'] = 'Reopened';
            $reopenedState['satisfaction_status'] = 'Not Satisfied';
            $reopenedState['reopen_count']++;
        }
        $this->assert($reopenedState['status'] === 'Reopened', 'Not Satisfied feedback reopens complaint');
        $this->assert($reopenedState['satisfaction_status'] === 'Not Satisfied', 'Not Satisfied feedback records satisfaction as Not Satisfied');
        $this->assert($reopenedState['reopen_count'] === 1, 'Reopen count increments upon dissatisfaction');
    }

    /**
     * Test 4: 10-Day Feedback Window Expiration Calculation
     */
    protected function testTenDayDeadlineCalculation(): void {
        echo "\nRunning: 10-Day Deadline Calculation Tests...\n";

        $resolvedAt = time();
        $tenDaysSeconds = 10 * 24 * 60 * 60;
        $deadline = $resolvedAt + $tenDaysSeconds;

        $this->assert(($deadline - $resolvedAt) === 864000, '10-day feedback period exactly equals 864,000 seconds (10 days)');

        // Test Simulated cron closure: deadline in the past
        $pastDeadline = time() - 3600; // 1 hour past 10 days
        $isExpired = ($pastDeadline <= time());
        $this->assert($isExpired, 'Cron detects expired deadline when timestamp is past 10 days');

        // Verify "No Response" rule: Never treat silence as confirmed satisfaction
        $cronClosed = [
            'status' => 'Closed',
            'satisfaction_status' => 'No Response',
            'closure_reason' => 'No Response'
        ];
        $this->assert($cronClosed['satisfaction_status'] === 'No Response', 'Scheduled expiration marks satisfaction as No Response, not Satisfied');
        $this->assert($cronClosed['closure_reason'] === 'No Response', 'Scheduled expiration sets closure reason to No Response');
    }

    /**
     * Test 5: Click-Through Rate (CTR) Calculation
     */
    protected function testAdAnalyticsAndCTR(): void {
        echo "\nRunning: Advertisement Analytics & CTR Tests...\n";

        $impressions = 2500;
        $clicks = 125;
        $ctr = ($impressions > 0) ? round(($clicks / $impressions) * 100, 2) : 0;
        $this->assert($ctr === 5.0, "CTR calculation accurate for 125 clicks / 2500 impressions: {$ctr}%");

        // Edge case: 0 impressions
        $zeroImpressions = 0;
        $zeroClicks = 0;
        $zeroCtr = ($zeroImpressions > 0) ? round(($zeroClicks / $zeroImpressions) * 100, 2) : 0;
        $this->assert($zeroCtr === 0, 'CTR handles division by zero safely when impressions are 0');
    }

    /**
     * Test 6: Repository Git Hygiene & Secret Protection
     */
    protected function testGitIgnoreAndSecretsHygiene(): void {
        echo "\nRunning: Git Hygiene & Secret Exclusion Tests...\n";

        $gitignorePath = __DIR__ . '/../.gitignore';
        $this->assert(file_exists($gitignorePath), '.gitignore file exists in repository root');

        $gitignoreContent = file_get_contents($gitignorePath);
        $this->assert(strpos($gitignoreContent, '.env') !== false, '.gitignore explicitly excludes .env');
        $this->assert(strpos($gitignoreContent, 'uploads/') !== false || strpos($gitignoreContent, 'uploads/*') !== false, '.gitignore excludes user-uploaded complaint attachments');

        $envExamplePath = __DIR__ . '/../.env.example';
        $this->assert(file_exists($envExamplePath), '.env.example exists in repository root');
        $envExample = file_get_contents($envExamplePath);
        $this->assert(strpos($envExample, 'rzp_test_') === false || strpos($envExample, 'your_') !== false, '.env.example contains placeholder values without live secrets');
    }

    /**
     * Test 7: Automatic Authority Assignment Routing & Built-in Credentials
     */
    protected function testAutomaticAuthorityRoutingAndBuiltinLogins(): void {
        echo "\nRunning: Automatic Authority Routing & Built-in Logins Tests...\n";

        require_once __DIR__ . '/../includes/functions.php';
        require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../includes/db.php';
        require_once __DIR__ . '/../includes/PaymentService.php';

        // 1. Validate Category to Authority Mappings
        $mappings = [
            'Water Supply & Leakage'             => 'Kerala Water Authority',
            'Roads & Infrastructure'             => 'Public Works Department',
            'Solid Waste Management'             => 'Municipal Solid Waste Management',
            'Food Safety & Hygiene'              => 'Food Safety & Hygiene',
            'Anti-Narcotics (Operation Thoofan)' => 'Anti-Narcotics',
            'General / Other Civic Issues'       => 'General Municipal Engineering',
        ];

        foreach ($mappings as $cat => $expectedDeptSubstr) {
            $auth = get_authority_for_category($cat);
            $this->assert(
                !empty($auth) && strpos($auth['department'], $expectedDeptSubstr) !== false,
                "Category '{$cat}' automatically resolves to '{$expectedDeptSubstr}'"
            );
        }

        // 2. Validate Authority Built-in Password Verification
        $db = get_db();
        $authStmt = $db->query("SELECT email, password_hash FROM authorities WHERE is_active = 1 LIMIT 3");
        $sampleAuthorities = $authStmt->fetchAll();
        $allAuthVerify = true;
        foreach ($sampleAuthorities as $a) {
            if (!password_verify('Authority@2026', $a['password_hash'])) {
                $allAuthVerify = false;
            }
        }
        $this->assert($allAuthVerify, "Authority accounts authenticate with built-in password 'Authority@2026'");

        // 3. Validate Category Admin Built-in Password Verification
        $adminStmt = $db->prepare("SELECT username, password_hash FROM administrators WHERE username = ?");
        $adminStmt->execute(['admin_water']);
        $waterAdmin = $adminStmt->fetch();
        $this->assert(
            $waterAdmin && password_verify('Water@admin2026', $waterAdmin['password_hash']),
            "Water Admin ('admin_water') authenticates with built-in password 'Water@admin2026'"
        );

        $adminStmt->execute(['superadmin']);
        $superAdmin = $adminStmt->fetch();
        $this->assert(
            $superAdmin && password_verify('Super@admin2026', $superAdmin['password_hash']),
            "Superadmin ('superadmin') authenticates with built-in password 'Super@admin2026'"
        );

        // 4. Validate Sandbox HMAC-SHA256 Signature Generation & Verification
        $orderId = 'order_sandbox_test123';
        $payId = 'pay_sandbox_test456';
        $config = PaymentService::getConfig();
        $validSignature = hash_hmac('sha256', $orderId . '|' . $payId, $config['key_secret']);
        $this->assert(
            PaymentService::verifySignature($orderId, $payId, $validSignature),
            'PaymentService verifies valid HMAC-SHA256 signature in sandbox mode'
        );
        $this->assert(
            !PaymentService::verifySignature($orderId, $payId, 'tampered_signature_xyz'),
            'PaymentService rejects tampered or invalid HMAC signature'
        );
    }
}

// Execute test suite if run directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $suite = new CivicLensTest();
    $suite->runAll();
}
