<?php
declare(strict_types=1);

require './vendor/autoload.php';

use Zatca\Tools\ZatcaInvoiceSignerTester;

// Configuration
$fatooraSignedDir = __DIR__ . '\\signed_invoices_by_fatoora';
$outputDir = __DIR__ . '\\signed_invoices_by_tester';
$privateKeyFile = 'D:\\zatca-einvoicing-sdk-Java-238-R3.4.2\\Data\\Certificates\\default\\ec-secp256k1-priv-key.pem';
$certificateFile = 'D:\\zatca-einvoicing-sdk-Java-238-R3.4.2\\Data\\Certificates\\default\\cert.pem';

// Create output directory
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Extract base64 DER from PEM files
function pemToBase64Der(string $pemContent): string {
    return preg_replace([
        '/-----BEGIN.*-----/',
        '/-----END.*-----/',
        '/\s+/'
    ], '', $pemContent);
}

// Load cryptographic materials
$privateKey = pemToBase64Der(file_get_contents($privateKeyFile));
$certificate = pemToBase64Der(file_get_contents($certificateFile));

// Process each Fatoora-signed invoice
$count = 0;
$success = 0;

echo "Processing invoices from: $fatooraSignedDir\n";
echo "Output directory: $outputDir\n\n";

foreach (new DirectoryIterator($fatooraSignedDir) as $file) {
    if ($file->isDot() || $file->getExtension() !== 'xml') continue;
    
    $count++;
    $filename = $file->getFilename();
    $signedPath = $file->getRealPath();
    
    try {
        // Load Fatoora-signed XML
        $signedXml = file_get_contents($signedPath);
        $dom = new DOMDocument();
        $dom->loadXML($signedXml);
        $xpath = new DOMXPath($dom);
        
        // Register namespaces
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');
        
        // Extract signingTime and signatureValue
        $signingTime = $xpath->evaluate('string(//xades:SigningTime)');
        $signatureValue = $xpath->evaluate('string(//ds:SignatureValue)');
        
        if (!$signingTime || !$signatureValue) {
            throw new Exception("Required elements not found in XML");
        }
        
        // Create tester instance with extracted values
        $tester = new ZatcaInvoiceSignerTester(
            $privateKey,
            $certificate,
            $signedXml,  // Using signed XML as input
            $signingTime,
            $signatureValue
        );
        
        // Generate new signed invoice
        $newSignedXml = $tester->prepareSignedInvoice();
        
        // Save result
        file_put_contents("$outputDir/$filename", $newSignedXml);
        $success++;
        
        echo "✓ Processed: $filename\n";
    } catch (Exception $e) {
        echo "✗ Failed $filename: " . $e->getMessage() . "\n";
    }
}

// Summary
echo "\nProcessed $count invoices\n";
echo "Successfully signed: $success\n";
echo "Failed: " . ($count - $success) . "\n";
echo "Output directory: $outputDir\n";
