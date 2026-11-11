<?php

namespace App\Enums;

enum CertificateType: string
{
    case Shm = 'shm';
    case Hgb = 'hgb';
    case Girik = 'girik';

    public function label(): string
    {
        return match ($this) {
            self::Shm => 'SHM (Hak Milik)',
            self::Hgb => 'HGB (Hak Guna Bangunan)',
            self::Girik => 'Girik',
        };
    }
}
