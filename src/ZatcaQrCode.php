<?php
declare(strict_types=1);

namespace Zatca\Tools;

class ZatcaQrCode {

	private function __construct() {}

	public static function generate(ZatcaQrCodeBuilder $qrCodeBuilder): string {
        $tlvString = self::convertQrCodeBuilderFieldsToTlvString($qrCodeBuilder);
        $base64String = self::convertTlvStringToBase64String($tlvString);
		return $base64String;
	}

    private static function convertQrCodeBuilderFieldsToTlvString(ZatcaQrCodeBuilder $qrCodeBuilder): string {
        $fields = [
            1 => $qrCodeBuilder->getSellerName(),
            2 => $qrCodeBuilder->getVatNumber(),
            3 => $qrCodeBuilder->getTimestamp(),
            4 => $qrCodeBuilder->getInvoiceTotal(),
            5 => $qrCodeBuilder->getVatTotal(),
            6 => $qrCodeBuilder->getInvoiceHash(),
            7 => $qrCodeBuilder->getEcdsaSignature(),
            8 => $qrCodeBuilder->getEcdsaPublicKey(),
            9 => $qrCodeBuilder->getX509SignatureValue(),
        ];

        $tlv = '';
        foreach ($fields as $tag => $value) {
            if ($value === null) continue;
            $tlv .= chr($tag) . chr(strlen($value)) . $value;
        }

        return $tlv;
    }

    private static function convertTlvStringToBase64String(string $tlvString): string {
        return base64_encode($tlvString);
    }

}
