<?php
$inputDir = 'D:\\zatca-einvoicing-sdk-Java-238-R3.4.2\\Data\\Samples';
$outputDir = __DIR__ . '\\signed_invoices_by_fatoora';
$excludedFolder = 'PDF-A3';

// Create output directory
is_dir($outputDir) || mkdir($outputDir, 0777, true);

// Initialize counters
$total = $success = $errors = 0;

echo "Starting signing process...\n\n";

// Process all XML files recursively
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($inputDir),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($files as $file) {
    // Skip directories, excluded folder, and non-XML files
    if ($file->isDir() || 
        stripos($file->getPath(), $excludedFolder) !== false || 
        $file->getExtension() !== 'xml') {
        continue;
    }

    $total++;
    $outputFile = $outputDir . '\\' . $file->getFilename();
    $inputPath = $file->getRealPath();

    // Execute Fatoora command
    exec(
        "fatoora -sign -invoice \"$inputPath\" -signedInvoice \"$outputFile\"",
        $output,
        $returnCode
    );

    // Handle results
    if ($returnCode === 0 && file_exists($outputFile)) {
        $success++;
        echo "✓ Signed: {$file->getFilename()}\n";
    } else {
        $errors++;
        echo "✗ Failed: {$file->getFilename()} (Code: $returnCode)\n";
    }
}

// Final report
echo "\nProcessed $total invoices\n";
echo "Successfully signed: $success\n";
echo "Failed: $errors\n";
echo "Output directory: $outputDir\n";
