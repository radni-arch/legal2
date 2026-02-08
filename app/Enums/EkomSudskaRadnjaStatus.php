<?php

namespace App\Enums;

enum EkomSudskaRadnjaStatus: string
{
    case NEAKTIVNA = 'NEAKTIVNA';
    case ODREDJENA = 'ODREDJENA';
    case ODGODJENA = 'ODGODJENA';
    case IZVRSENA = 'IZVRSENA';
}
