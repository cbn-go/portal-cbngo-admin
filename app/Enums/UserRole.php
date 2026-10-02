<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case CONVENTION_EDITOR = 'convention_editor';
    case AUTHOR = 'author';
    case CHURCH_REPRESENTATIVE = 'church_representative';
}
