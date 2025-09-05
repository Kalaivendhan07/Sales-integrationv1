<?php
/**
 * Test Final GSTIN Generation
 */

echo "=== TESTING FINAL GSTIN GENERATION ===\n\n";

// Test the final patterns
for ($i = 1; $i <= 3; $i++) {
    // Baseline opportunities pattern (final)
    $baselineGSTIN = sprintf('29PERFA%04dA1Z%d', $i, $i % 10);
    echo "Baseline $i: '$baselineGSTIN' (Length: " . strlen($baselineGSTIN) . ")\n";
}

echo "\n";

for ($i = 1; $i <= 3; $i++) {
    // Existing customer pattern (final)
    $customerNum = ($i % 50) + 1;
    $existingGSTIN = sprintf('29PERFA%04dA1Z%d', $customerNum, $customerNum % 10);
    echo "Existing $i: '$existingGSTIN' (Length: " . strlen($existingGSTIN) . ")\n";
}

echo "\n";

for ($i = 201; $i <= 203; $i++) {
    // New customer pattern (final)
    $newGSTIN = sprintf('29NEWAB%03dB2Z%d', $i, $i % 10);
    echo "New $i: '$newGSTIN' (Length: " . strlen($newGSTIN) . ")\n";
}

echo "\n=== VALIDATION TEST ===\n";

function testGSTINValidation($gstin) {
    $pattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
    $result = preg_match($pattern, $gstin);
    echo "GSTIN: '$gstin' -> " . ($result ? 'VALID ✅' : 'INVALID ❌') . "\n";
    return $result;
}

// Test the final samples
testGSTINValidation('29PERFA0001A1Z1');
testGSTINValidation('29NEWAB201B2Z1');
testGSTINValidation('29PERFA0005A1Z5');

echo "\n=== COMPARISON ===\n";
testGSTINValidation('29ABCDE0001F1Z5'); // Known working GSTIN (15 chars)
?>