<?php
declare(strict_types=1);

namespace Zatca\Tools;

class ZatcaQrCodeBuilder
{
    // ── Phase-1 fields ──────────────────────────────
    private string $sellerName;
    private string $vatNumber;
    private string $timestamp;     // ISO-8601 string
    private string $invoiceTotal;
    private string $vatTotal;

    // ── Phase-2 fields ──────────────────────────────
    private string $invoiceHash;
    private string $ecdsaSignature;
    private string $ecdsaPublicKey;
    private string $x509SignatureValue;

    public function setSellerName(string $name): self
    {
        $this->sellerName = $name;
        return $this;
    }

    public function setVatNumber(string $vat): self
    {
        $this->vatNumber = $vat;
        return $this;
    }

    public function setTimestamp(string $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function setInvoiceTotal(string $total): self
    {
        $this->invoiceTotal = $total;
        return $this;
    }

    public function setVatTotal(string $vatTotal): self
    {
        $this->vatTotal = $vatTotal;
        return $this;
    }

    public function setInvoiceHash(string $invoiceHash): self
    {
        $this->invoiceHash = $invoiceHash;
        return $this;
    }

    public function setEcdsaSignature(string $ecdsaSignature): self
    {
        $this->ecdsaSignature = $ecdsaSignature;
        return $this;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->ecdsaPublicKey = base64_decode($publicKey);
        return $this;
    }

    public function setX509SignatureValue(string $x509SignatureValue): self
    {
        $this->x509SignatureValue = $x509SignatureValue;
        return $this;
    }

    public function getSellerName():          string { return $this->sellerName; }
    public function getVatNumber():           string { return $this->vatNumber; }
    public function getTimestamp():           string { return $this->timestamp; }
    public function getInvoiceTotal():        string { return $this->invoiceTotal; }
    public function getVatTotal():            string { return $this->vatTotal; }
    public function getInvoiceHash():        ?string { return $this->invoiceHash ?? null; }
    public function getEcdsaSignature():     ?string {return $this->ecdsaSignature ?? null; }
    public function getEcdsaPublicKey():     ?string {return $this->ecdsaPublicKey ?? null; }
    public function getX509SignatureValue(): ?string {return $this->x509SignatureValue ?? null; }
}
