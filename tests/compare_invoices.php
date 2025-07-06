<?php
declare(strict_types=1);

// Configuration
$fatooraDir = __DIR__ . '\\signed_invoices_by_fatoora';
$testerDir = __DIR__ . '\\signed_invoices_by_tester';

echo "Starting invoice comparison...\n";
echo "Fatoora directory: $fatooraDir\n";
echo "Tester directory: $testerDir\n\n";

$total = 0;
$identical = 0;
$different = 0;
$missing = 0;

// Process files
foreach (new DirectoryIterator($fatooraDir) as $file) {
    if ($file->isDot() || $file->getExtension() !== 'xml') continue;
    
    $total++;
    $filename = $file->getFilename();
    $fatooraPath = $file->getRealPath();
    $testerPath = $testerDir . '\\' . $filename;
    
    $status = 'Missing';
    $fatooraSize = filesize($fatooraPath);
    $testerSize = 'N/A';
    $details = '';
    
    if (!file_exists($testerPath)) {
        $missing++;
        $details = 'File missing in tester directory';
    } else {
        $testerSize = filesize($testerPath);
        $fatooraContent = file_get_contents($fatooraPath);
        $testerContent = file_get_contents($testerPath);
        
        // Normalize XML for comparison
        $normalize = function($xml) {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;
            @$dom->loadXML($xml);
            return $dom->saveXML();
        };
        
        $normalizedFatoora = $normalize($fatooraContent);
        $normalizedTester = $normalize($testerContent);
        
        if ($normalizedFatoora === $normalizedTester) {
            $status = 'Identical';
            $identical++;
        } else {
            $status = 'Different';
            $different++;
            $details = 'XML content differs after normalization';
        }
    }
    
    // Console output
    echo str_pad($filename, 40) . " [$status]\n";
}

// Summary
echo "\nComparison complete!\n";
echo "===============================\n";
echo "Total files checked: $total\n";
echo "Identical files:    $identical\n";
echo "Different files:    $different\n";
echo "Missing files:      $missing\n";
