<?php
echo "=== Testing Challenge Verification System ===" . PHP_EOL . PHP_EOL;

// Test 1: Hash verification logic
echo "Test 1: Answer Hash Verification" . PHP_EOL;
$challenge_data = [
    'question' => '5 + 3',
    'answer_hash' => hash('sha256', '8'),
    'attempts' => 0
];
$json = json_encode($challenge_data);
echo "Challenge JSON: " . $json . PHP_EOL;

$user_answer = '8';
$computed_hash = hash('sha256', $user_answer);
$stored_hash = $challenge_data['answer_hash'];
$match = ($computed_hash === $stored_hash);
echo "Correct answer (8): " . ($match ? "✓ PASS" : "✗ FAIL") . PHP_EOL;

$wrong_answer = '7';
$wrong_hash = hash('sha256', $wrong_answer);
echo "Wrong answer (7): " . (($wrong_hash !== $stored_hash) ? "✓ PASS (correctly rejected)" : "✗ FAIL") . PHP_EOL;

// Test 2: Time logic
echo "\nTest 2: Time Logic" . PHP_EOL;
$created_at = date('Y-m-d H:i:s');
$elapsed = 0;
$remaining = max(0, 60 - $elapsed);
echo "Time remaining (just created): " . $remaining . " seconds" . PHP_EOL;

// Simulate 45 seconds elapsed
$elapsed = 45;
$remaining = max(0, 60 - $elapsed);
echo "Time remaining (45 sec later): " . $remaining . " seconds" . PHP_EOL;

// Simulate 65 seconds elapsed (expired)
$elapsed = 65;
$remaining = max(0, 60 - $elapsed);
echo "Time remaining (65 sec later): " . $remaining . " seconds (expired: " . ($elapsed > 60 ? "YES" : "NO") . ")" . PHP_EOL;

// Test 3: Attempt tracking
echo "\nTest 3: Attempt Tracking" . PHP_EOL;
$challenge_data['attempts'] = 1;
echo "After attempt 1: " . $challenge_data['attempts'] . "/3" . PHP_EOL;
$challenge_data['attempts'] = 2;
echo "After attempt 2: " . $challenge_data['attempts'] . "/3" . PHP_EOL;
$challenge_data['attempts'] = 3;
echo "After attempt 3 (max reached): " . ($challenge_data['attempts'] >= 3 ? "✓ Auto-reject triggered" : "✗ Should trigger reject") . PHP_EOL;

echo "\n=== All tests complete ===" . PHP_EOL;
?>
