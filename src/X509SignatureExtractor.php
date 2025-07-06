<?php
declare(strict_types=1);

namespace Zatca\Tools;

class X509SignatureExtractor
{
    public static function extract($derX509Certificate): string
    {
        $p = 0;
        $cert = self::readTLV(0x30, base64_decode($derX509Certificate), $p);
        
        $inner = 0;
        self::readTLV(0x30, $cert['val'], $inner); // tbsCertificate
        self::readTLV(0x30, $cert['val'], $inner); // signatureAlgorithm
        $sig = self::readTLV(0x03, $cert['val'], $inner); // signatureValue
        
        return substr($sig['val'], 1); // skip unused bits byte
    }

    private static function readTLV(int $tag, string $buf, int &$pos): array
    {
        if (ord($buf[$pos++]) !== $tag) {
            throw new \RuntimeException("Invalid tag at offset " . ($pos - 1));
        }

        $len = ord($buf[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x7F;
            $len = 0;
            while ($n--) $len = ($len << 8) | ord($buf[$pos++]);
        }

        return ['val' => substr($buf, $pos, $len), 'tag' => $tag] + [1 => $pos += $len];
    }
}
