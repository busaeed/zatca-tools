# ZATCA Tools

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/github/license/busaeed/zatca-tools)](MIT)
[![Packagist](https://img.shields.io/packagist/v/busaeed/zatca-tools)](https://packagist.org/packages/busaeed/zatca-tools)

ZATCA Tools is a small PHP library that helps with e-invoicing tasks like signing invoices and making QR codes for ZATCA. It's easy to use and can be extended later to support more features.

---

## 🔔 Important Notes

1. **This is not an official library.** It was built independently and is not approved or maintained by ZATCA.
2. It was tested using all official ZATCA sample invoices, and results matched those from the official Fatoora SDK. However, **use it at your own risk**.
3. You should always follow ZATCA's official sample XML structures. Customizing them may cause errors or rejection.
4. If you find a bug or need help, feel free to **open a GitHub issue** or **contact me on [LinkedIn](https://www.linkedin.com/in/busaeed)**.

---

## 📋 Requirements

- PHP 8.0 or higher
- OpenSSL extension enabled

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
