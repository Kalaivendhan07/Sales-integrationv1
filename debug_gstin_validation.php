<?php
/**
 * Debug GSTIN Validation Pattern
 */

function testGSTINPattern($gstin) {
    $pattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
    $result = preg_match($pattern, $gstin);
    
    echo "GSTIN: '$gstin' (Length: " . strlen($gstin) . ")\n";
    echo "Pattern: $pattern\n";
    echo "Result: " . ($result ? 'VALID' : 'INVALID') . "\n";
    
    // Break down the GSTIN character by character
    if (strlen($gstin) >= 15) {
        echo "Breakdown:\n";
        echo "  Pos 1-2 (State): '" . substr($gstin, 0, 2) . "' - Should be digits: " . (ctype_digit(substr($gstin, 0, 2)) ? 'YES' : 'NO') . "\n";
        echo "  Pos 3-7 (PAN): '" . substr($gstin, 2, 5) . "' - Should be letters: " . (ctype_alpha(substr($gstin, 2, 5)) ? 'YES' : 'NO') . "\n";
        echo "  Pos 8-11 (Entity): '" . substr($gstin, 7, 4) . "' - Should be digits: " . (ctype_digit(substr($gstin, 7, 4)) ? 'YES' : 'NO') . "\n";
        echo "  Pos 12 (Check): '" . substr($gstin, 11, 1) . "' - Should be letter: " . (ctype_alpha(substr($gstin, 11, 1)) ? 'YES' : 'NO') . "\n";
        echo "  Pos 13 (Default): '" . substr($gstin, 12, 1) . "' - Should be 1-9 or A-Z: " . (preg_match('/[1-9A-Z]/', substr($gstin, 12, 1)) ? 'YES' : 'NO') . "\n";
        echo "  Pos 14 (Z): '" . substr($gstin, 13, 1) . "' - Should be Z: " . (substr($gstin, 13, 1) === 'Z' ? 'YES' : 'NO') . "\n";
        echo "  Pos 15 (Check): '" . substr($gstin, 14, 1) . "' - Should be digit or letter: " . (ctype_alnum(substr($gstin, 14, 1)) ? 'YES' : 'NO') . "\n";
    }
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

echo "=== GSTIN VALIDATION PATTERN DEBUG ===\n\n";

// Test the problematic GSTINs from batch performance test
$testGSTINs = array(
    '29PERF0001A1Z1',  // From batch test - failing
    '29NEW00001B2Y1',  // From batch test - failing
    '29ABCDE0001F1Z5', // From backend test - working
    '29XYZAB1234G2Z7', // Valid format
    '27AAAAA0000A1Z5', // Standard valid format
    'INVALID_GSTIN'    // Invalid format
);

foreach ($testGSTINs as $gstin) {
    testGSTINPattern($gstin);
}

echo "=== ANALYSIS ===\n";
echo "The issue appears to be in the GSTIN format generation in batch_performance_test.php\n";
echo "Let's check what format it's actually generating...\n";
?>