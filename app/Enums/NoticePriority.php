<?php

namespace App\Enums;

enum NoticePriority: string
{
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';
}
