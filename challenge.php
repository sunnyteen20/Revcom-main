<?php
session_start();
require_once __DIR__ . '/db.php';

$message = "";
$message_class = "";
$sign_in_id = isset($_GET['sign_in_id']) ? (int)$_GET['sign_in_id'] : 0;

if (!$sign_in_id) {
    header("Location: index.php?error=invalid");
    exit;
}

$stmt = $conn->prepare("SELECT email, verification_token, verification_sent_at, is_verified, verification_attempts_sent FROM tbl_sign_in WHERE sign_in_id = ? LIMIT 1");
$stmt->bind_param("i", $sign_in_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: index.php?error=notfound");
    exit;
}

$email = $row['email'];
$challenge_token = $row['verification_token'];
$created_at = $row['verification_sent_at'];
$is_verified = $row['is_verified'];
$attempts_sent = isset($row['verification_attempts_sent']) ? (int)$row['verification_attempts_sent'] : 0;

// If already verified, redirect
if ($is_verified) {
    header("Location: index.php?error=alreadyverified");
    exit;
}

function generateMathQuestion() {
    $num1 = rand(1, 20);
    $num2 = rand(1, 20);
    $operator = rand(0, 1) ? '+' : '-';

    if ($operator === '+') {
        $correct_answer = $num1 + $num2;
    } else {
        $correct_answer = $num1 - $num2;
    }

    return [
        'question' => "$num1 $operator $num2",
        'answer' => $correct_answer
    ];
}

$verified = false;
$challenge_code = null;
$current_question = '';
$attempt_number = 0;

$stored_answer = null;
$token_sign_in = null;
// Parse existing token format "sign_in_id:answer" (plaintext) or legacy CHG-* code

// token_sign_in declared below
if ($challenge_token) {
    if (strpos($challenge_token, ':') !== false) {
        list($token_sign_in, $stored_answer) = explode(':', $challenge_token, 2);
        $token_sign_in = (int)$token_sign_in;
        $attempt_number = $attempts_sent > 0 ? $attempts_sent : 1;
    } elseif (strpos($challenge_token, 'CHG-') === 0) {
        $challenge_code = $challenge_token;
        preg_match('/CHG-(\d+)/', $challenge_code, $matches);
        $attempt_number = isset($matches[1]) ? (int)$matches[1] : 1;
    }
}

// If no stored answer exists, create a question and store token as sign_in_id:answer (plaintext)
if (empty($stored_answer)) {
    $next_attempt = max(1, $attempts_sent);
    if ($next_attempt == 0) { $next_attempt = 1; }
    $qd = generateMathQuestion();
    $answer_plain = (string)$qd['answer'];
    $token_payload = $sign_in_id . ':' . $answer_plain;
    $upd = $conn->prepare("UPDATE tbl_sign_in SET verification_token = ?, verification_sent_at = NOW(), verification_attempts_sent = ? WHERE sign_in_id = ?");
    $upd->bind_param('sii', $token_payload, $next_attempt, $sign_in_id);
    $upd->execute();
    $upd->close();
    $_SESSION['current_question_' . $sign_in_id] = $qd['question'];
    $_SESSION['current_answer_' . $sign_in_id] = $qd['answer'];
    $stored_answer = $answer_plain;
    $attempt_number = $next_attempt;
}

// Check if challenge expired (>60 seconds)
if ($created_at) {
    $elapsed = time() - strtotime($created_at);
    if ($elapsed > 60) {
        // Auto reject on timeout
        $stmt_del_user = $conn->prepare("DELETE FROM tbl_users WHERE sign_in_id = ?");
        $stmt_del_user->bind_param("i", $sign_in_id);
        $stmt_del_user->execute();
        $stmt_del_user->close();

        $stmt_del_signin = $conn->prepare("DELETE FROM tbl_sign_in WHERE sign_in_id = ?");
        $stmt_del_signin->bind_param("i", $sign_in_id);
        $stmt_del_signin->execute();
        $stmt_del_signin->close();

        header("Location: index.php?error=challengeexpired");
        exit;
    }
}

// Generate current question if not in session
if (!isset($_SESSION['current_question_' . $sign_in_id])) {
    $question_data = generateMathQuestion();
    $_SESSION['current_question_' . $sign_in_id] = $question_data['question'];
    $_SESSION['current_answer_' . $sign_in_id] = $question_data['answer'];
}

$current_question = $_SESSION['current_question_' . $sign_in_id];

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer'])) {
    $submitted_answer = trim($_POST['answer']);

    $has_expected = isset($_SESSION['current_answer_' . $sign_in_id]) || $stored_answer !== null;
    if ($submitted_answer === '' || !$has_expected) {
        $message = "Invalid submission. Please try again.";
        $message_class = "warning";
    } else {
            // Prefer the session-stored correct answer (more reliable), fall back to token-stored
            $expected_answer = $_SESSION['current_answer_' . $sign_in_id] ?? $stored_answer;
            // Compare numerically to tolerate formatting differences
            if ((int)$expected_answer === (int)$submitted_answer) {
                // Correct answer - verify the account and store token as sign_in_id:answer
            $verified_token = $sign_in_id . ':' . (int)$expected_answer;
            $stmt_verify = $conn->prepare("UPDATE tbl_sign_in SET is_verified = 1, verified_at = NOW(), verification_token = ? WHERE sign_in_id = ?");
            $stmt_verify->bind_param("si", $verified_token, $sign_in_id);
            $stmt_verify->execute();
            $stmt_verify->close();

            unset($_SESSION['current_question_' . $sign_in_id]);
            unset($_SESSION['current_answer_' . $sign_in_id]);

            $message = "✓ Correct! Your account is now verified.";
            $message_class = "success";
            $verified = true;
        } else {
            // Wrong answer - check attempts
            if ($attempt_number >= 3) {
                // Auto-reject: delete the account
                $stmt_del_user = $conn->prepare("DELETE FROM tbl_users WHERE sign_in_id = ?");
                $stmt_del_user->bind_param("i", $sign_in_id);
                $stmt_del_user->execute();
                $stmt_del_user->close();

                $stmt_del_signin = $conn->prepare("DELETE FROM tbl_sign_in WHERE sign_in_id = ?");
                $stmt_del_signin->bind_param("i", $sign_in_id);
                $stmt_del_signin->execute();
                $stmt_del_signin->close();

                unset($_SESSION['current_question_' . $sign_in_id]);
                unset($_SESSION['current_answer_' . $sign_in_id]);

                header("Location: index.php?error=challengefailed");
                exit;
            } else {
                $next_attempt = $attempt_number + 1;

                $qd = generateMathQuestion();
                $answer_plain = (string)$qd['answer'];
                $token_payload = $sign_in_id . ':' . $answer_plain;

                $stmt_update = $conn->prepare("UPDATE tbl_sign_in SET verification_token = ?, verification_sent_at = NOW(), verification_attempts_sent = ? WHERE sign_in_id = ?");
                $stmt_update->bind_param("sii", $token_payload, $next_attempt, $sign_in_id);
                $stmt_update->execute();
                $stmt_update->close();

                $_SESSION['current_question_' . $sign_in_id] = $qd['question'];
                $_SESSION['current_answer_' . $sign_in_id] = $qd['answer'];
                $current_question = $qd['question'];
                $attempt_number = $next_attempt;

                $message = "✗ Incorrect answer. Attempt " . $attempt_number . " of 3. Try again.";
                $message_class = "warning";
            }
        }
    }
}

$time_elapsed = $created_at ? time() - strtotime($created_at) : 0;
$time_remaining = max(0, 60 - $time_elapsed);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification Challenge - REVCOM</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,800" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f5; }
        .challenge-container { background: rgba(255,255,255,0.95); padding: 40px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); max-width: 500px; text-align: center; }
        .challenge-container h2 { color: #dd353d; margin-bottom: 10px; }
        .challenge-code { color: #7f8c8d; font-size: 12px; font-weight: bold; letter-spacing: 1px; margin-bottom: 20px; }
        .challenge-container p { color: #333; font-size: 16px; margin: 15px 0; }
        .timer { font-size: 18px; font-weight: bold; color: #dd353d; margin: 20px 0; }
        .timer.warning { color: #f44336; }
        .question { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; font-size: 24px; font-weight: bold; color: #333; }
        input[type="number"] { width: 80px; padding: 10px; font-size: 18px; text-align: center; border: 2px solid #dd353d; border-radius: 8px; }
        button { background-color: #dd353d; color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #b8272a; }
        .message { padding: 15px; border-radius: 8px; margin: 15px 0; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; }
        .warning { background-color: #fff3cd; color: #856404; }
        .attempts { color: #dd353d; font-weight: bold; }
        .verified-link { margin-top: 20px; }
        .verified-link a { color: #dd353d; text-decoration: none; font-weight: bold; }
        .verified-link a:hover { text-decoration: underline; }
        .resend-link { margin-top: 15px; font-size: 13px; }
        .resend-link a { color: #7f8c8d; text-decoration: none; }
        .resend-link a:hover { color: #dd353d; text-decoration: underline; }
    </style>
</head>
<body>
<div class="challenge-container">
    <h2>Account Verification Challenge</h2>
    <div class="challenge-code">Challenge Code: <?= htmlspecialchars($challenge_code) ?></div>
    <p>Email: <strong><?= htmlspecialchars($email) ?></strong></p>
    
    <?php if (!empty($message)): ?>
        <div class="message <?= $message_class ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$verified && $time_remaining > 0): ?>
        <div class="timer <?= $time_remaining < 15 ? 'warning' : '' ?>">
            Time remaining: <span id="timer"><?= $time_remaining ?></span> seconds
        </div>
        
        <p>Answer the math question to verify your account:</p>
        <div class="question">
            <?= htmlspecialchars($current_question) ?>
        </div>
        
        <form method="POST">
            <input type="number" name="answer" placeholder="Your answer" required autofocus>
            <button type="submit" style="display: block; margin: 15px auto;">Submit Answer</button>
        </form>
        
        <p class="attempts">Attempt <?= $attempt_number ?>/3</p>
        
        <div class="resend-link">
            <a href="resend_verification.php?sign_in_id=<?= urlencode($sign_in_id) ?>">Can't complete this? Request a new challenge</a>
        </div>
    <?php elseif ($verified): ?>
        <div class="verified-link">
            <p>✓ Your account has been verified successfully!</p>
            <a href="index.php">→ Go to Sign In</a>
        </div>
    <?php else: ?>
        <div class="message warning">
            ✗ Time expired. Your verification request has been cancelled.
        </div>
        <div class="verified-link">
            <a href="signup.php">← Sign up again</a>
        </div>
    <?php endif; ?>
</div>

<script>
    <?php if ($time_remaining > 0 && !$verified): ?>
    let timeLeft = <?= $time_remaining ?>;
    setInterval(function() {
        timeLeft--;
        document.getElementById('timer').textContent = timeLeft;
        if (timeLeft <= 0) {
            window.location.reload();
        }
    }, 1000);
    <?php endif; ?>
</script>
</body>
</html>
<?php $conn->close(); ?>
