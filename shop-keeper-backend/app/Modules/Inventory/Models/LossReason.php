<?php

namespace App\Modules\Inventory\Models;

enum LossReason: string
{
    case Broken = 'broken';
    case Expired = 'expired';
    case Stolen = 'stolen';
    case OwnUse = 'own_use';
    case Other = 'other';
}
