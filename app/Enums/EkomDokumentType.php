<?php

namespace App\Enums;

enum EkomDokumentType: string
{
    case PODNESAK = 'PODNESAK';
    case PRILOG = 'PRILOG';
    case ODLUKA = 'ODLUKA';
    case ZAPISNIK = 'ZAPISNIK';
    case POZIV = 'POZIV';
    case UMETNUTI_PREDMET = 'UMETNUTI_PREDMET';
}
