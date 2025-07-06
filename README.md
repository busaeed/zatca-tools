# ZATCA Tools

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://www.php.net/)

ZATCA Tools is a small PHP library that helps with e-invoicing tasks like signing invoices and making QR codes for ZATCA. It's easy to use and can be extended later to support more features.

---

## 📋 Requirements

- PHP 8.0 or higher
- OpenSSL extension enabled
- Composer

---

## 📦 Installation

Use Composer to install:

```bash
composer require busaeed/zatca-tools
```

---

## 📘 Phase 1 — Generate QR Code Only

This example generates a ZATCA Phase 1 compliant QR code in Base64:

```php
use Zatca\Tools\ZatcaQrCodeBuilder;
use Zatca\Tools\ZatcaQrCode;

$qrBuilder = (new ZatcaQrCodeBuilder())
    ->setSellerName('Test Seller')
    ->setVatNumber('300000000000003')
    ->setTimestamp('2025-06-28T23:59:59')
    ->setInvoiceTotal('115.00')
    ->setVatTotal('15.00');

$qrCodeBase64 = ZatcaQrCode::generate($qrBuilder);
```

---

## 📘 Phase 2 — Sign Invoice (With QR Code Included)

This example signs a UBL XML invoice and adds the Phase 2 digital signature and QR code automatically:

```php
use Zatca\Tools\ZatcaInvoiceSigner;

$signer = new ZatcaInvoiceSigner(
    $derPrivateKeyBase64,      // Base64-encoded EC Private Key (DER format)
    $derX509CertificateBase64, // Base64-encoded X.509 Certificate (DER format)
    $unsignedXmlInvoice        // The original UBL XML invoice as a string
);

$signedXmlInvoice = $signer->prepareSignedInvoice();
```

---

## 👤 Author

Made by [MOHAMMED BU SAEED](https://github.com/busaeed)  
📦 [View on Packagist](https://packagist.org/packages/busaeed/zatca-tools)  
💬 [Contact on LinkedIn](https://www.linkedin.com/in/busaeed)

---

## 📝 License

MIT License
