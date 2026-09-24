<?php

namespace App\Enums;

/**
 * O papel da pessoa dentro de uma agência.
 *
 * Separado de UserRole porque o fundador não tem vínculo com agência nenhuma:
 * ele existe no produto e não dentro de um perfil.
 */
enum MembershipRole: string
{
    case Owner = 'owner';
    case Invited = 'invited';
}
