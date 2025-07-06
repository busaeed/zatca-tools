<?php
declare(strict_types=1);

namespace Zatca\Tools;

class ZatcaInvoiceSigner
{
    private const EXCLUDED_TAGS = array(
        "//*[local-name()='Invoice']//*[local-name()='UBLExtensions']",
        "//*[local-name()='AdditionalDocumentReference'][cbc:ID[normalize-space(text()) = 'QR']]",
        "//*[local-name()='Invoice']//*[local-name()='Signature']"
    );

    private string $derPrivateKey;
    private string $derX509Certificate;
    private string $xmlInvoice;

    private array $templateValues;
    private array $qrCodeValues;

    private \OpenSSLAsymmetricKey $privateKey;
    private string $derPublicKey;

    private \DOMDocument $invoiceDom;
    private \DOMXPath $xpath;

    public function __construct(string $derPrivateKey, string $derX509Certificate, string $xmlInvoice)
    {
        $this->derPrivateKey = $derPrivateKey;
        $this->derX509Certificate = $derX509Certificate;
        $this->xmlInvoice = $xmlInvoice;
        $this->templateValues = [];
        $this->qrCodeValues = [];
    }

    public function prepareSignedInvoice(): string
    {
        $this->preparePrivateKey();

        $this->preparePublicKey();

        $this->prepareX509Certificate();

        $this->loadInvoiceToDomObject();

        $this->removeExcludedTagsFromInvoice();

        $invoiceHash = $this->hashInvoice();

        $this->encodeInvoiceHash($invoiceHash);

        $this->generateDigitalSignature($invoiceHash);

        $this->generateCertificateHash();

        $this->generateSignedPropertiesHash();

        $this->extractInvoiceInformationRequiredForQrCode();

        $this->generateQrCode();

        $this->populateAndAppendUblExtensionsTag();

        $this->populateAndAppendQrTag();

        return $this->xmlInvoice;
    }

    private function preparePrivateKey(): void
    {
        $pemPrivateKey = "-----BEGIN EC PRIVATE KEY-----\n" . wordwrap($this->derPrivateKey, 64, "\n", true) . "\n-----END EC PRIVATE KEY-----";
        $this->privateKey = openssl_pkey_get_private($pemPrivateKey);
        if (!$this->privateKey) {
            throw new \RuntimeException("Unable to parse private key.");
        }
    }

    private function preparePublicKey(): void
    {
        $publicKey = openssl_pkey_get_details($this->privateKey);
        $pemPublicKey = $publicKey["key"];
        $this->derPublicKey = preg_replace("/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/", "", $pemPublicKey);
    }

    private function prepareX509Certificate(): void
    {
        $this->templateValues["X509_CERTIFICATE"] = $this->derX509Certificate;

        $pemX509Certificate = "-----BEGIN CERTIFICATE-----\n" . wordwrap($this->derX509Certificate, 64, "\n", true) . "\n-----END CERTIFICATE-----";
        
        $x509CertificateArray = openssl_x509_parse($pemX509Certificate);

        $issuer = (array)$x509CertificateArray["issuer"];
        $this->templateValues["X509_ISSUER_NAME"] = "CN=" . $issuer["CN"] . (!empty($issuer["DC"]) ? ", " . implode(", ", array_map(fn($v) => "DC=$v", array_reverse($issuer["DC"]))) : "");
        
        $this->templateValues["X509_SERIAL_NUMBER"] = gmp_strval(gmp_init($x509CertificateArray["serialNumberHex"], 16), 10);

        $this->qrCodeValues["X509_SIGNATURE_VALUE"] = X509SignatureExtractor::extract($this->derX509Certificate);
    }

    private function loadInvoiceToDomObject(): void
    {
        $this->invoiceDom = new \DOMDocument();
        $this->invoiceDom->loadXML($this->xmlInvoice);

        $this->xpath = new \DOMXPath($this->invoiceDom);
    }

    private function removeExcludedTagsFromInvoice(): void
    {
        foreach (self::EXCLUDED_TAGS as $excludedTag) {
            $nodeList = $this->xpath->query($excludedTag);
            foreach ($nodeList as $childNode) {
                $childNode->parentNode->removeChild($childNode);
            }
        }

        $this->xmlInvoice = trim($this->invoiceDom->saveXML());
    }

    private function hashInvoice(): string
    {
        return hash("sha256", $this->invoiceDom->C14N(), true);
    }

    private function encodeInvoiceHash(string $invoiceHash): void
    {
        $this->templateValues["DOCUMENT_DIGEST_VALUE"] = base64_encode($invoiceHash);
    }

    private function generateDigitalSignature(string $invoiceHash): void
    {
        $this->templateValues["SIGNING_TIME"] = (new \DateTime())->format('Y-m-d\TH:i:s');

        $success = openssl_sign($invoiceHash, $rawSignatureValue, $this->privateKey, OPENSSL_ALGO_SHA256);
        if (!$success) {
            throw new \RuntimeException("Unable to generate digital signature.");
        }
        $this->templateValues["SIGNATURE_VALUE"] = base64_encode($rawSignatureValue);
    }

    private function generateCertificateHash(): void
    {
        $this->templateValues["CERT_DIGEST_VALUE"] = base64_encode(hash("sha256", $this->derX509Certificate, false));
    }

    private function generateSignedPropertiesHash(): void
    {
        $signedPropertiesTemplate = <<<XML
<xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">
                                    <xades:SignedSignatureProperties>
                                        <xades:SigningTime>{$this->templateValues["SIGNING_TIME"]}</xades:SigningTime>
                                        <xades:SigningCertificate>
                                            <xades:Cert>
                                                <xades:CertDigest>
                                                    <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                                    <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$this->templateValues["CERT_DIGEST_VALUE"]}</ds:DigestValue>
                                                </xades:CertDigest>
                                                <xades:IssuerSerial>
                                                    <ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$this->templateValues["X509_ISSUER_NAME"]}</ds:X509IssuerName>
                                                    <ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$this->templateValues["X509_SERIAL_NUMBER"]}</ds:X509SerialNumber>
                                                </xades:IssuerSerial>
                                            </xades:Cert>
                                        </xades:SigningCertificate>
                                    </xades:SignedSignatureProperties>
                                </xades:SignedProperties>
XML;

        $signedPropertiesTemplate = str_replace("\r\n", "\n", $signedPropertiesTemplate); //normalizing

        $this->templateValues["SIGNED_PROPERTIES_DIGEST_VALUE"] = base64_encode(hash("sha256", $signedPropertiesTemplate, false));
    }

    private function extractInvoiceInformationRequiredForQrCode(): void
    {
        $registrationNameNode = $this->xpath->query("/*[local-name()='Invoice']/*[local-name()='AccountingSupplierParty']/*[local-name()='Party']/*[local-name()='PartyLegalEntity']/*[local-name()='RegistrationName']/text()")->item(0);
        $this->qrCodeValues["SELLER_NAME"] = $registrationNameNode ? $registrationNameNode->nodeValue : null;
        
        $companyIdNode = $this->xpath->query("/*[local-name()='Invoice']/*[local-name()='AccountingSupplierParty']/*[local-name()='Party']/*[local-name()='PartyTaxScheme']/*[local-name()='CompanyID']/text()")->item(0);
        $this->qrCodeValues["VAT_NUMBER"] = $companyIdNode ? $companyIdNode->nodeValue : null;

        $issueDateNode = $this->xpath->query("/*[local-name()='Invoice']/*[local-name()='IssueDate']/text()")->item(0);
        $issueTimeNode = $this->xpath->query("/*[local-name()='Invoice']/*[local-name()='IssueTime']/text()")->item(0);
        $this->qrCodeValues["ISSUE_TIMESTAMP"] = $issueDateNode && $issueTimeNode ? $issueDateNode->nodeValue . "T" . $issueTimeNode->nodeValue : null;

        $invoiceTotalNode = $this->xpath->query("/*[local-name()='Invoice']/*[local-name()='LegalMonetaryTotal']/*[local-name()='PayableAmount']/text()")->item(0);
        $this->qrCodeValues["INVOICE_TOTAL"] = $invoiceTotalNode ? $invoiceTotalNode->nodeValue : null;

        $vatTaxNode = $this->xpath->query("/*[local-name()='Invoice']/*[local-name()='TaxTotal']/*[local-name()='TaxAmount']/text()")->item(0);
        $this->qrCodeValues["VAT_AMOUNT"] = $vatTaxNode ? $vatTaxNode->nodeValue : null;

        $invoiceSubtypeNode = $this->xpath->query("/*[local-name()='Invoice']/*[local-name()='InvoiceTypeCode'][@name]/@name")->item(0);
        $this->qrCodeValues["INVOICE_SUBTYPE"] = $invoiceSubtypeNode ? $invoiceSubtypeNode->nodeValue : null;
    }

    private function generateQrCode(): void
    {
        $qrCodebuilder = (new ZatcaQrCodeBuilder())
            ->setSellerName($this->qrCodeValues["SELLER_NAME"])
            ->setVatNumber($this->qrCodeValues["VAT_NUMBER"])
            ->setTimestamp($this->qrCodeValues["ISSUE_TIMESTAMP"])
            ->setInvoiceTotal($this->qrCodeValues["INVOICE_TOTAL"])
            ->setVatTotal($this->qrCodeValues["VAT_AMOUNT"])
            ->setInvoiceHash($this->templateValues["DOCUMENT_DIGEST_VALUE"])
            ->setEcdsaSignature($this->templateValues["SIGNATURE_VALUE"])
            ->setPublicKey($this->derPublicKey);
            
        if (str_starts_with($this->qrCodeValues["INVOICE_SUBTYPE"], "02"))
        {
            $qrCodebuilder->setX509SignatureValue($this->qrCodeValues["X509_SIGNATURE_VALUE"]);
        }

        $this->templateValues["QR_CODE"] = ZatcaQrCode::generate($qrCodebuilder);
    }

    private function populateAndAppendUblExtensionsTag(): void
    {
        $ublExtensionsTag = <<<XML
<ext:UBLExtensions>
    <ext:UBLExtension>
        <ext:ExtensionURI>urn:oasis:names:specification:ubl:dsig:enveloped:xades</ext:ExtensionURI>
        <ext:ExtensionContent>
            <sig:UBLDocumentSignatures xmlns:sig="urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2" xmlns:sac="urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2" xmlns:sbc="urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2">
                <sac:SignatureInformation> 
                    <cbc:ID>urn:oasis:names:specification:ubl:signature:1</cbc:ID>
                    <sbc:ReferencedSignatureID>urn:oasis:names:specification:ubl:signature:Invoice</sbc:ReferencedSignatureID>
                    <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="signature">
                        <ds:SignedInfo>
                            <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                            <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256"/>
                            <ds:Reference Id="invoiceSignedData" URI="">
                                <ds:Transforms>
                                    <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::ext:UBLExtensions)</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::cac:Signature)</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                                        <ds:XPath>not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])</ds:XPath>
                                    </ds:Transform>
                                    <ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
                                </ds:Transforms>
                                <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                <ds:DigestValue>{$this->templateValues['DOCUMENT_DIGEST_VALUE']}</ds:DigestValue>
                            </ds:Reference>
                            <ds:Reference Type="http://www.w3.org/2000/09/xmldsig#SignatureProperties" URI="#xadesSignedProperties">
                                <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                <ds:DigestValue>{$this->templateValues['SIGNED_PROPERTIES_DIGEST_VALUE']}</ds:DigestValue>
                            </ds:Reference>
                        </ds:SignedInfo>
                        <ds:SignatureValue>{$this->templateValues['SIGNATURE_VALUE']}</ds:SignatureValue>
                        <ds:KeyInfo>
                            <ds:X509Data>
                                <ds:X509Certificate>{$this->templateValues['X509_CERTIFICATE']}</ds:X509Certificate>
                            </ds:X509Data>
                        </ds:KeyInfo>
                        <ds:Object>
                            <xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="signature">
                                <xades:SignedProperties Id="xadesSignedProperties">
                                    <xades:SignedSignatureProperties>
                                        <xades:SigningTime>{$this->templateValues['SIGNING_TIME']}</xades:SigningTime>
                                        <xades:SigningCertificate>
                                            <xades:Cert>
                                                <xades:CertDigest>
                                                    <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                                    <ds:DigestValue>{$this->templateValues['CERT_DIGEST_VALUE']}</ds:DigestValue>
                                                </xades:CertDigest>
                                                <xades:IssuerSerial>
                                                    <ds:X509IssuerName>{$this->templateValues['X509_ISSUER_NAME']}</ds:X509IssuerName>
                                                    <ds:X509SerialNumber>{$this->templateValues['X509_SERIAL_NUMBER']}</ds:X509SerialNumber>
                                                </xades:IssuerSerial>
                                            </xades:Cert>
                                        </xades:SigningCertificate>
                                    </xades:SignedSignatureProperties>
                                </xades:SignedProperties>
                            </xades:QualifyingProperties>
                        </ds:Object>
                    </ds:Signature>
                </sac:SignatureInformation>
            </sig:UBLDocumentSignatures>
        </ext:ExtensionContent>
    </ext:UBLExtension>
</ext:UBLExtensions>
XML;

    $ublExtensionsTag = str_replace("\r\n", "\n", $ublExtensionsTag); //normalizing

    $this->xmlInvoice = preg_replace('/(<Invoice\b[^>]*>)/', '$1' . $ublExtensionsTag, $this->xmlInvoice, 1);
    }

    private function populateAndAppendQrTag(): void
    {
        $qrCodeTag = <<<XML
<cac:AdditionalDocumentReference>
        <cbc:ID>QR</cbc:ID>
        <cac:Attachment>
            <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">{$this->templateValues["QR_CODE"]}</cbc:EmbeddedDocumentBinaryObject>
        </cac:Attachment>
</cac:AdditionalDocumentReference><cac:Signature>
      <cbc:ID>urn:oasis:names:specification:ubl:signature:Invoice</cbc:ID>
      <cbc:SignatureMethod>urn:oasis:names:specification:ubl:dsig:enveloped:xades</cbc:SignatureMethod>
</cac:Signature>
XML;

    $qrCodeTag = str_replace("\r\n", "\n", $qrCodeTag); //normalizing

    $this->xmlInvoice = preg_replace('/(<cac:AccountingSupplierParty\b[^>]*>)/', $qrCodeTag . '$1', $this->xmlInvoice, 1);
    }
}
