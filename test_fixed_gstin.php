<?php
/**
 * Test Fixed GSTIN Generation
 */

echo "=== TESTING FIXED GSTIN GENERATION ===\n\n";

// Test the new patterns
for ($i = 1; $i <= 5; $i++) {
    // Baseline opportunities pattern
    $baselineGSTIN = sprintf('29PERFA%03d1Z%d', $i, $i % 10);
    echo "Baseline $i: '$baselineGSTIN' (Length: " . strlen($baselineGSTIN) . ")\n";
}

echo "\n";

for ($i = 1; $i <= 5; $i++) {
    // Existing customer pattern (same as baseline)
    $customerNum = ($i % 50) + 1;
    $existingGSTIN = sprintf('29PERFA%03d1Z%d', $customerNum, $customerNum % 10);
    echo "Existing $i: '$existingGSTIN' (Length: " . strlen($existingGSTIN) . ")\n";
}

echo "\n";

for ($i = 201; $i <= 205; $i++) {
    // New customer pattern
    $newGSTIN = sprintf('29NEWAB%04d2Z%d', $i, $i % 10);
    echo "New $i: '$newGSTIN' (Length: " . strlen($newGSTIN) . ")\n";
}

echo "\n=== VALIDATION TEST ===\n";

function testGSTINValidation($gstin) {
    $pattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
    $result = preg_match($pattern, $gstin);
    echo "GSTIN: '$gstin' -> " . ($result ? 'VALID ✅' : 'INVALID ❌') . "\n";
    return $result;
}

// Test a few samples
testGSTINValidation('29PERFA0011Z1');
testGSTINValidation('29NEWAB02012Z1');
testGSTINValidation('29PERFA0501Z5');
?>