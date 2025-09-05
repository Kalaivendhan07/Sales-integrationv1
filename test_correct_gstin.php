<?php
/**
 * Test Correct GSTIN Generation - Updated Patterns
 */

echo "=== TESTING UPDATED GSTIN GENERATION ===\n\n";

// Test the updated patterns from batch_performance_test.php
for ($i = 1; $i <= 5; $i++) {
    // Baseline opportunities pattern (updated)
    $baselineGSTIN = sprintf('29PERFA%04d1Z%d', $i, $i % 10);
    echo "Baseline $i: '$baselineGSTIN' (Length: " . strlen($baselineGSTIN) . ")\n";
}

echo "\n";

for ($i = 1; $i <= 5; $i++) {
    // Existing customer pattern (updated)
    $customerNum = ($i % 50) + 1;
    $existingGSTIN = sprintf('29PERFA%04d1Z%d', $customerNum, $customerNum % 10);
    echo "Existing $i: '$existingGSTIN' (Length: " . strlen($existingGSTIN) . ")\n";
}

echo "\n";

for ($i = 201; $i <= 205; $i++) {
    // New customer pattern (updated)
    $newGSTIN = sprintf('29NEWAB%03d12Z%d', $i, $i % 10);
    echo "New $i: '$newGSTIN' (Length: " . strlen($newGSTIN) . ")\n";
}

echo "\n=== VALIDATION TEST ===\n";

function testGSTINValidation($gstin) {
    $pattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
    $result = preg_match($pattern, $gstin);
    echo "GSTIN: '$gstin' -> " . ($result ? 'VALID ✅' : 'INVALID ❌') . "\n";
    return $result;
}

// Test the updated samples
testGSTINValidation('29PERFA00011Z1');
testGSTINValidation('29NEWAB20112Z1');
testGSTINValidation('29PERFA00051Z5');

echo "\n=== COMPARISON WITH WORKING GSTIN ===\n";
testGSTINValidation('29ABCDE0001F1Z5'); // Known working GSTIN
?>